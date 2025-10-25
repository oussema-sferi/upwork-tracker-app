<?php

namespace App\Controller;

use App\Entity\Settings;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/settings')]
#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'app_settings', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $settings = $this->settingsRepository->findOrCreateByUser($user);

        if ($request->isMethod('POST')) {
            $this->handleFormSubmission($request, $settings);
            return $this->redirectToRoute('app_settings');
        }

        return $this->render('settings/index.html.twig', [
            'settings' => $settings,
        ]);
    }

    private function handleFormSubmission(Request $request, Settings $settings): void
    {
        // Get form data
        $keywords = $request->request->get('keywords');
        $minProposals = $request->request->get('min_proposals');
        $maxProposals = $request->request->get('max_proposals');
        $excludedCountries = $request->request->get('excluded_countries');
        $emailNotifications = $request->request->getBoolean('email_notifications');
        $telegramNotifications = $request->request->getBoolean('telegram_notifications');
        $emailAddress = $request->request->get('email_address');
        $telegramChatId = $request->request->get('telegram_chat_id');

        // Update settings
        $settings->setKeywords($keywords);
        $settings->setMinProposals($minProposals ? (int) $minProposals : null);
        $settings->setMaxProposals($maxProposals ? (int) $maxProposals : null);
        $settings->setExcludedCountries($excludedCountries);
        $settings->setEmailNotifications($emailNotifications);
        $settings->setTelegramNotifications($telegramNotifications);
        $settings->setEmailAddress($emailAddress);
        $settings->setTelegramChatId($telegramChatId);
        $settings->updateTimestamp();

        // Save to database
        $this->entityManager->persist($settings);
        $this->entityManager->flush();

        $this->addFlash('success', 'Settings updated successfully!');
    }
}
