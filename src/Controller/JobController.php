<?php

namespace App\Controller;

use App\Service\JobMonitoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/jobs')]
#[IsGranted('ROLE_USER')]
class JobController extends AbstractController
{
    public function __construct(
        private readonly JobMonitoringService $jobMonitoringService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/', name: 'app_jobs_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        
        // Get pagination parameters
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = (int) $request->query->get('per_page', 20);
        $search = $request->query->get('search');
        
        // Validate per_page value
        $allowedPerPage = [10, 20, 30, 50];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 20;
        }
        
        // Get paginated jobs with search
        $paginationData = $this->jobMonitoringService->getJobsForUserPaginated($user, $page, $perPage, $search);

        return $this->render('jobs/index.html.twig', [
            'jobs' => $paginationData['jobs'],
            'pagination' => $paginationData['pagination'],
            'currentPage' => $page,
            'perPage' => $perPage,
            'allowedPerPage' => $allowedPerPage,
            'search' => $search,
        ]);
    }

    #[Route('/search', name: 'app_jobs_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $user = $this->getUser();
        
        // Get search parameters
        $search = $request->query->get('q', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = (int) $request->query->get('per_page', 20);
        
        // Validate per_page value
        $allowedPerPage = [10, 20, 30, 50];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 20;
        }
        
        // Get search results
        $paginationData = $this->jobMonitoringService->getJobsForUserPaginated($user, $page, $perPage, $search);

        // Return JSON response for AJAX
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'jobs' => array_map(function($job) {
                    return [
                        'id' => $job->getId(),
                        'title' => $job->getTitle(),
                        'description' => $job->getDescription(),
                        'budget' => $job->getBudget(),
                        'client' => $job->getClient(),
                        'country' => $job->getCountry(),
                        'skills' => $job->getSkills(),
                        'createdAt' => $job->getCreatedAt() ? $job->getCreatedAt()->format('Y-m-d H:i:s') : null,
                        'url' => $job->getUrl(),
                    ];
                }, $paginationData['jobs']),
                'pagination' => $paginationData['pagination'],
                'search' => $search,
            ]);
        }

        return $this->render('jobs/index.html.twig', [
            'jobs' => $paginationData['jobs'],
            'pagination' => $paginationData['pagination'],
            'currentPage' => $page,
            'perPage' => $perPage,
            'allowedPerPage' => $allowedPerPage,
            'search' => $search,
        ]);
    }

    #[Route('/{id}', name: 'app_jobs_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $user = $this->getUser();
        $job = $this->jobMonitoringService->getJobById($id, $user);

        if (!$job) {
            throw $this->createNotFoundException('Job not found');
        }

        return $this->render('jobs/show.html.twig', [
            'job' => $job,
        ]);
    }

    #[Route('/fetch', name: 'app_jobs_fetch', methods: ['POST'])]
    public function fetch(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$user->getUpworkAccessToken()) {
            $this->addFlash('error', 'You must be connected to Upwork to fetch jobs');
            return $this->redirectToRoute('app_settings');
        }

        try {
            $savedJobs = $this->jobMonitoringService->fetchJobsForUser($user);
            $count = count($savedJobs);
            
            if ($count > 0) {
                $this->addFlash('success', "Successfully fetched {$count} new jobs from Upwork!");
            } else {
                $this->addFlash('info', 'No new jobs found matching your criteria');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to fetch jobs: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_jobs_index');
    }

    #[Route('/{id}/delete', name: 'app_jobs_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        error_log("Attempting to delete job ID: {$id} for user: {$user->getId()}");
        
        $job = $this->jobMonitoringService->getJobById($id, $user);

        if (!$job) {
            error_log("Job not found for ID: {$id}");
            $this->addFlash('error', 'Job not found');
            return $this->redirectToRoute('app_jobs_index');
        }

        $jobTitle = $job->getTitle();
        error_log("Deleting job: {$jobTitle}");
        
        $this->entityManager->remove($job);
        $this->entityManager->flush();

        $this->addFlash('success', "Job '{$jobTitle}' has been deleted successfully");
        return $this->redirectToRoute('app_jobs_index');
    }

    #[Route('/bulk-delete', name: 'app_jobs_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $jobIds = $request->request->all('job_ids');
        
        error_log("Bulk delete request for user: {$user->getId()}");
        error_log("Job IDs received: " . json_encode($jobIds));

        if (empty($jobIds)) {
            error_log("No job IDs provided for bulk delete");
            $this->addFlash('error', 'No jobs selected for deletion');
            return $this->redirectToRoute('app_jobs_index');
        }

        $deletedCount = 0;
        foreach ($jobIds as $jobId) {
            error_log("Processing job ID for deletion: {$jobId}");
            $job = $this->jobMonitoringService->getJobById((int)$jobId, $user);
            if ($job) {
                error_log("Found job to delete: {$job->getTitle()}");
                $this->entityManager->remove($job);
                $deletedCount++;
            } else {
                error_log("Job not found for ID: {$jobId}");
            }
        }

        $this->entityManager->flush();
        error_log("Bulk delete completed. Deleted count: {$deletedCount}");

        $this->addFlash('success', "Successfully deleted {$deletedCount} jobs");
        return $this->redirectToRoute('app_jobs_index');
    }
}
