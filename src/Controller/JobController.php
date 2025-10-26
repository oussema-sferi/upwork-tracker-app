<?php

namespace App\Controller;

use App\Service\JobMonitoringService;
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
        private readonly JobMonitoringService $jobMonitoringService
    ) {
    }

    #[Route('/', name: 'app_jobs_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $jobs = $this->jobMonitoringService->getJobsForUser($user);

        return $this->render('jobs/index.html.twig', [
            'jobs' => $jobs,
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
}
