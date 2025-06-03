<?php

namespace App\Controller;

use App\Repository\AlertRepository;
use App\Repository\JobRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(JobRepository $jobRepository, UserRepository $userRepository, AlertRepository $alertRepository): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'totalJobs' => $jobRepository->countAllJobs(),
            'totalUsers' => $userRepository->count([]),
            'pendingAlerts' => $alertRepository->countPendingAlerts(), // Placeholder For now ==> To update later
        ]);
    }
}
