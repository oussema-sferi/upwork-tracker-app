<?php

namespace App\Controller;

use App\Service\UpworkApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/oauth/upwork')]
#[IsGranted('ROLE_USER')]
class UpworkOAuthController extends AbstractController
{
    public function __construct(
        private readonly UpworkApiService $upworkApiService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/authorize', name: 'upwork_oauth_authorize', methods: ['GET'])]
    public function authorize(): Response
    {
        try {
            $authUrl = $this->upworkApiService->getAuthorizationUrl();
            return $this->redirect($authUrl);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to initiate Upwork authorization: ' . $e->getMessage());
            return $this->redirectToRoute('app_settings');
        }
    }

    #[Route('/callback', name: 'upwork_oauth_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $code = $request->query->get('code');
        $error = $request->query->get('error');
        $errorDescription = $request->query->get('error_description');

        // Debug logging
        error_log('Upwork OAuth Callback - Code: ' . ($code ? 'present' : 'missing'));
        error_log('Upwork OAuth Callback - Error: ' . ($error ?: 'none'));
        error_log('Upwork OAuth Callback - Error Description: ' . ($errorDescription ?: 'none'));

        if ($error) {
            $this->addFlash('error', 'Upwork authorization failed: ' . $error . ($errorDescription ? ' - ' . $errorDescription : ''));
            return $this->redirectToRoute('app_settings');
        }

        if (!$code) {
            $this->addFlash('error', 'No authorization code received from Upwork');
            return $this->redirectToRoute('app_settings');
        }

        try {
            error_log('Attempting to exchange code for token...');
            $accessToken = $this->upworkApiService->exchangeCodeForToken($code);
            error_log('Token received: ' . substr($accessToken, 0, 20) . '...');
            
            $user = $this->getUser();
            if ($user) {
                error_log('User found: ' . $user->getEmail());
                $user->setUpworkAccessToken($accessToken);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                error_log('Token saved to user');
                
                $this->addFlash('success', 'Successfully connected to Upwork! You can now fetch jobs.');
            } else {
                error_log('No user found in session');
                $this->addFlash('error', 'You must be logged in to connect to Upwork');
                return $this->redirectToRoute('app_login');
            }
            
            return $this->redirectToRoute('app_settings');
        } catch (\Exception $e) {
            error_log('Exception in callback: ' . $e->getMessage());
            $this->addFlash('error', 'Failed to complete Upwork authorization: ' . $e->getMessage());
            return $this->redirectToRoute('app_settings');
        }
    }

    #[Route('/disconnect', name: 'upwork_oauth_disconnect', methods: ['POST'])]
    public function disconnect(): Response
    {
        try {
            $user = $this->getUser();
            $user->setUpworkAccessToken(null);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->addFlash('success', 'Disconnected from Upwork');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to disconnect from Upwork: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_settings');
    }

    #[Route('/test-api', name: 'upwork_oauth_test_api', methods: ['GET'])]
    public function testApi(): Response
    {
        $user = $this->getUser();
        
        if (!$user || !$user->getUpworkAccessToken()) {
            $this->addFlash('error', 'You must be connected to Upwork to test the API');
            return $this->redirectToRoute('app_settings');
        }

        try {
            $result = $this->upworkApiService->fetchUserProfile($user->getUpworkAccessToken());
            
            $this->addFlash('success', 'API test successful! Upwork API connection is working. You can now fetch jobs and other data from Upwork.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'API test failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_settings');
    }
}
