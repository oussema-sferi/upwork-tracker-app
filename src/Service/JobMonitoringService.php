<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Job;
use App\Entity\Settings;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\JobRepository;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

class JobMonitoringService
{
    public function __construct(
        private readonly UpworkApiService $upworkApiService,
        private readonly EntityManagerInterface $entityManager,
        private readonly JobRepository $jobRepository,
        private readonly SettingsRepository $settingsRepository,
        private readonly ClientRepository $clientRepository
    ) {
    }

    public function fetchJobsForUser(User $user): array
    {
        error_log('=== JOB MONITORING DEBUG START ===');
        error_log('User: ' . $user->getEmail());
        
        // Get user settings
        $settings = $this->settingsRepository->findOrCreateByUser($user);
        error_log('Settings found: ' . ($settings ? 'Yes' : 'No'));
        
        if (!$user->getUpworkAccessToken()) {
            error_log('ERROR: User not connected to Upwork');
            throw new \Exception('User is not connected to Upwork');
        }

        if (!$settings->getKeywords()) {
            error_log('ERROR: No keywords configured');
            throw new \Exception('No keywords configured for job search');
        }

        error_log('Keywords: ' . $settings->getKeywords());
        error_log('Min Proposals: ' . ($settings->getMinProposals() ?? 'null'));
        error_log('Max Proposals: ' . ($settings->getMaxProposals() ?? 'null'));
        error_log('Excluded Countries: ' . ($settings->getExcludedCountries() ?? 'null'));

        // Fetch jobs from Upwork API
        $jobs = $this->fetchJobsFromUpwork($user, $settings);
        error_log('Total jobs fetched from API: ' . count($jobs));
        
        // Filter and save jobs
        $savedJobs = [];
        $filteredCount = 0;
        foreach ($jobs as $jobData) {
            error_log('Processing job: ' . $jobData['title']);
            if ($this->shouldSaveJob($jobData, $settings)) {
                error_log('Job passed filters, creating...');
                $job = $this->createJobFromData($jobData, $user);
                if ($job) {
                    $savedJobs[] = $job;
                    error_log('Job saved successfully: ' . $job->getTitle());
                }
            } else {
                $filteredCount++;
                error_log('Job filtered out: ' . $jobData['title']);
            }
        }

        error_log('Jobs filtered out: ' . $filteredCount);
        error_log('Jobs saved: ' . count($savedJobs));
        error_log('=== JOB MONITORING DEBUG END ===');

        return $savedJobs;
    }

    private function fetchJobsFromUpwork(User $user, Settings $settings): array
    {
        $keywords = explode(',', $settings->getKeywords());
        $allJobs = [];
        
        error_log('Keywords to search: ' . json_encode($keywords));

        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) {
                error_log('Skipping empty keyword');
                continue;
            }

            error_log('Searching for keyword: ' . $keyword);
            try {
                $jobs = $this->upworkApiService->searchJobs(
                    $user->getUpworkAccessToken(),
                    $keyword
                );
                error_log('Found ' . count($jobs) . ' jobs for keyword: ' . $keyword);
                $allJobs = array_merge($allJobs, $jobs);
            } catch (\Exception $e) {
                error_log("Failed to fetch jobs for keyword '{$keyword}': " . $e->getMessage());
            }
        }

        error_log('Total unique jobs found: ' . count($allJobs));
        return $allJobs;
    }

    private function shouldSaveJob(array $jobData, Settings $settings): bool
    {
        error_log('Checking job: ' . $jobData['title']);
        error_log('Job data: ' . json_encode($jobData));
        
        // Check if job already exists
        $existingJob = $this->jobRepository->findOneBy(['upworkId' => $jobData['id']]);
        if ($existingJob) {
            error_log('Job already exists, skipping: ' . $jobData['title']);
            return false;
        }

        // Check if job matches any of the user's keywords
        $keywords = array_map('trim', explode(',', $settings->getKeywords()));
        $jobMatchesKeyword = false;
        
        foreach ($keywords as $keyword) {
            if (empty($keyword)) continue;
            
            // Check if keyword appears in title or description (case insensitive)
            if (stripos($jobData['title'], $keyword) !== false || 
                stripos($jobData['description'], $keyword) !== false) {
                $jobMatchesKeyword = true;
                error_log('Job matches keyword "' . $keyword . '": ' . $jobData['title']);
                break;
            }
        }
        
        if (!$jobMatchesKeyword) {
            error_log('Job filtered out - does not match any keywords: ' . $jobData['title']);
            return false;
        }

        // Check proposal limits if set
        if ($settings->getMinProposals() !== null && $jobData['proposals'] < $settings->getMinProposals()) {
            error_log('Job filtered out - proposals too low: ' . $jobData['proposals'] . ' < ' . $settings->getMinProposals());
            return false;
        }

        if ($settings->getMaxProposals() !== null && $jobData['proposals'] > $settings->getMaxProposals()) {
            error_log('Job filtered out - proposals too high: ' . $jobData['proposals'] . ' > ' . $settings->getMaxProposals());
            return false;
        }

        // Check excluded countries
        if ($settings->getExcludedCountries()) {
            $excludedCountries = array_map('trim', explode(',', $settings->getExcludedCountries()));
            if (in_array($jobData['country'], $excludedCountries)) {
                error_log('Job filtered out - country excluded: ' . $jobData['country']);
                return false;
            }
        }

        error_log('Job passed all filters: ' . $jobData['title']);
        return true;
    }

    private function createJobFromData(array $jobData, User $user): ?Job
    {
        try {
            $job = new Job();
            $job->setUpworkId($jobData['id']);
            $job->setTitle($jobData['title']);
            $job->setDescription($jobData['description']);
            $job->setUrl($jobData['url']);
            $job->setPostedAt(new \DateTimeImmutable($jobData['postedAt']));
            $job->setBudget($jobData['budget'] ?? null);
            $job->setSkills($jobData['skills'] ?? null);
            $job->setUser($user);

            // Handle client creation
            if (isset($jobData['client']) && is_array($jobData['client'])) {
                $client = $this->findOrCreateClient($jobData['client']);
                $job->setClient($client);
            }

            $this->entityManager->persist($job);
            $this->entityManager->flush();

            return $job;
        } catch (\Exception $e) {
            error_log("Failed to create job: " . $e->getMessage());
            return null;
        }
    }

    private function findOrCreateClient(array $clientData): ?Client
    {
        // Try to find existing client by country and city
        $existingClient = $this->clientRepository->findOneBy([
            'country' => $clientData['country'],
            'city' => $clientData['city']
        ]);

        if ($existingClient) {
            return $existingClient;
        }

        // Create new client
        $client = new Client();
        $client->setCountry($clientData['country']);
        $client->setCity($clientData['city']);
        $client->setState($clientData['state']);
        $client->setVerificationStatus($clientData['verificationStatus']);

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return $client;
    }

    public function getJobsForUser(User $user, int $limit = 50): array
    {
        return $this->jobRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            $limit
        );
    }

    public function getJobById(int $id, User $user): ?Job
    {
        return $this->jobRepository->findOneBy(['id' => $id, 'user' => $user]);
    }

    public function getJobsForUserPaginated(User $user, int $page = 1, int $perPage = 20, ?string $search = null): array
    {
        $offset = ($page - 1) * $perPage;
        
        // Build query with search if provided
        $qb = $this->jobRepository->createQueryBuilder('j')
            ->where('j.user = :user')
            ->setParameter('user', $user);
        
        if ($search) {
            $qb->andWhere('j.title LIKE :search OR j.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        // Get total count
        $totalJobs = $qb->select('COUNT(j.id)')
                       ->getQuery()
                       ->getSingleScalarResult();
        
        // Get jobs for current page
        $jobs = $qb->select('j')
                   ->orderBy('j.createdAt', 'DESC')
                   ->setMaxResults($perPage)
                   ->setFirstResult($offset)
                   ->getQuery()
                   ->getResult();
        
        // Calculate pagination info
        $totalPages = ceil($totalJobs / $perPage);
        
        return [
            'jobs' => $jobs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalJobs,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
                'previous_page' => $page > 1 ? $page - 1 : null,
                'next_page' => $page < $totalPages ? $page + 1 : null,
            ]
        ];
    }
}
