<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\Client;
use App\Entity\Job;
use App\Entity\Settings;
use App\Entity\User;
use App\Repository\AlertRepository;
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
        private readonly ClientRepository $clientRepository,
        private readonly AlertRepository $alertRepository,
        private readonly ?OpenAiService $openAiService = null,
        private readonly ?TelegramService $telegramService = null
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
        
        // Send Telegram notifications for jobs with proposals
        if ($this->telegramService) {
            $this->sendTelegramNotifications($savedJobs);
        }
        
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
            $job->setSkills($jobData['skills'] ?? null);
            $job->setUser($user);

            // Set contract terms
            $job->setContractType($jobData['contractType'] ?? null);
            $job->setFixedPriceAmount($jobData['fixedPriceAmount'] ?? null);
            $job->setHourlyRateMin($jobData['hourlyRateMin'] ?? null);
            $job->setHourlyRateMax($jobData['hourlyRateMax'] ?? null);

            // Handle client creation
            if (isset($jobData['client']) && is_array($jobData['client'])) {
                $client = $this->findOrCreateClient($jobData['client']);
                $job->setClient($client);
            }

            // Generate proposal if job is hourly and min rate >= $18
            if ($this->shouldGenerateProposal($job)) {
                //dd($job);
                error_log('Generating proposal for job: ' . $job->getTitle());
                $proposal = $this->generateProposalForJob($job);
                if ($proposal) {
                    $job->setSuggestedProposal($proposal);
                    error_log('Proposal generated successfully');
                } else {
                    error_log('Failed to generate proposal');
                }
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

    private function shouldGenerateProposal(Job $job): bool
    {
        // Check if job is hourly and OpenAI service is available
        if (!$this->openAiService) {
            return false;
        }
        
        // Check if job is hourly type
        if ($job->getContractType() !== 'HOURLY') {
            return false;
        }
        
        // Check if minimum hourly rate is >= $18
        $hourlyRateMin = $job->getHourlyRateMin();
        if ($hourlyRateMin === null || (float)$hourlyRateMin < 11.0) {
            return false;
        }
        return true;
    }

    private function generateProposalForJob(Job $job): ?string
    {
        if (!$this->openAiService) {
            return null;
        }

        try {
            return $this->openAiService->generateProposal(
                $job->getTitle(),
                $job->getDescription()
            );
        } catch (\Exception $e) {
            error_log('Error generating proposal: ' . $e->getMessage());
            return null;
        }
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

    private function sendTelegramNotifications(array $jobs): void
    {
        foreach ($jobs as $job) {
            if ($job->getSuggestedProposal()) {
                $message = $this->formatTelegramMessage($job);
                if ($message) {
                    $sent = $this->telegramService->sendMessage($message);
                    
                    // Save alert to database
                    $alert = new Alert();
                    $alert->setJob($job);
                    $alert->setType('TELEGRAM');
                    $alert->setIsSent($sent);
                    if ($sent) {
                        $alert->setSentAt(new \DateTimeImmutable());
                    }
                    
                    $this->entityManager->persist($alert);
                    $this->entityManager->flush();
                    
                    // Small delay to avoid rate limiting
                    usleep(500000); // 0.5 seconds
                }
            }
        }
    }

    private function formatTelegramMessage(Job $job): ?string
    {
        try {
            // Build message parts without description first
            $messageParts = [
                "<b>🎯 New Job Opportunity</b>\n\n",
                "<b>📋 Title:</b>\n" . htmlspecialchars($job->getTitle()) . "\n\n",
            ];
            
            // Contract Type and Payment
            $paymentText = "<b>💰 Payment:</b>\n";
            if ($job->getContractType() === 'FIXED_PRICE') {
                $amount = $job->getFixedPriceAmount();
                if ($amount) {
                    $paymentText .= "Fixed Price: $" . number_format((float)$amount, 2) . "\n";
                } else {
                    $paymentText .= "Fixed Price: Not specified\n";
                }
            } elseif ($job->getContractType() === 'HOURLY') {
                $min = $job->getHourlyRateMin();
                $max = $job->getHourlyRateMax();
                if ($min && $max) {
                    $paymentText .= "Hourly: $" . number_format((float)$min, 2) . " - $" . number_format((float)$max, 2) . "/hr\n";
                } elseif ($min) {
                    $paymentText .= "Hourly: From $" . number_format((float)$min, 2) . "/hr\n";
                } elseif ($max) {
                    $paymentText .= "Hourly: Up to $" . number_format((float)$max, 2) . "/hr\n";
                } else {
                    $paymentText .= "Hourly: Rate not specified\n";
                }
            } else {
                $paymentText .= "Not specified\n";
            }
            $messageParts[] = $paymentText . "\n";
            
            // Client Location and Verification
            if ($job->getClient()) {
                $client = $job->getClient();
                $locationText = "<b>📍 Location:</b>\n" . htmlspecialchars($client->getLocation()) . "\n";
                if ($client->isVerified()) {
                    $locationText .= "✅ <b>Verified Client</b>\n";
                }
                $messageParts[] = $locationText . "\n";
            }
            
            // Job Link
            $messageParts[] = "<b>🔗 Job Link:</b>\n<a href=\"" . htmlspecialchars($job->getUrl()) . "\">View on Upwork</a>\n\n";
            
            // Suggested Proposal
            $messageParts[] = "<b>💡 AI-Generated Proposal:</b>\n<i>" . htmlspecialchars($job->getSuggestedProposal()) . "</i>\n";
            
            // Calculate available space for description
            $baseMessageLength = mb_strlen(implode('', $messageParts));
            $descriptionHeader = "<b>📝 Description:</b>\n";
            $maxDescriptionLength = 4096 - $baseMessageLength - mb_strlen($descriptionHeader) - 100; // 100 chars buffer
            
            // Truncate description if needed
            $description = $job->getDescription();
            if (mb_strlen($description) > $maxDescriptionLength) {
                $description = mb_substr($description, 0, $maxDescriptionLength) . '...';
            }
            
            // Build final message with description inserted
            $message = $messageParts[0] . $messageParts[1]; // Header + Title
            $message .= $descriptionHeader . htmlspecialchars($description) . "\n\n"; // Description
            $message .= implode('', array_slice($messageParts, 2)); // Rest of the parts
            
            return $message;
        } catch (\Exception $e) {
            error_log('Error formatting Telegram message: ' . $e->getMessage());
            return null;
        }
    }
}
