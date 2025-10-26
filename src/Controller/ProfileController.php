<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private UserPasswordHasherInterface $passwordHasher,
        private string $profilePicturesDirectory
    ) {}

    #[Route('/', name: 'app_profile_show', methods: ['GET'])]
    public function show(): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('profile/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $firstName = $request->request->get('firstName');
            $lastName = $request->request->get('lastName');
            $profilePictureFile = $request->files->get('profilePicture');
            
            // Password change fields
            $currentPassword = $request->request->get('currentPassword');
            $newPassword = $request->request->get('newPassword');
            $confirmPassword = $request->request->get('confirmPassword');

            // Update user data
            $user->setFirstName($firstName);
            $user->setLastName($lastName);

            // Handle password change if provided
            if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
                if (empty($currentPassword)) {
                    $this->addFlash('error', 'Current password is required to change password.');
                    return $this->render('profile/edit.html.twig', ['user' => $user]);
                }
                
                if (empty($newPassword)) {
                    $this->addFlash('error', 'New password is required.');
                    return $this->render('profile/edit.html.twig', ['user' => $user]);
                }
                
                if ($newPassword !== $confirmPassword) {
                    $this->addFlash('error', 'New password and confirmation do not match.');
                    return $this->render('profile/edit.html.twig', ['user' => $user]);
                }
                
                if (strlen($newPassword) < 6) {
                    $this->addFlash('error', 'New password must be at least 6 characters long.');
                    return $this->render('profile/edit.html.twig', ['user' => $user]);
                }
                
                // Verify current password
                if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('error', 'Current password is incorrect.');
                    return $this->render('profile/edit.html.twig', ['user' => $user]);
                }
                
                // Hash and set new password
                $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
            }

            // Handle profile picture upload
            if ($profilePictureFile) {
                $originalFilename = pathinfo($profilePictureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $profilePictureFile->guessExtension();

                try {
                    $profilePictureFile->move($this->profilePicturesDirectory, $newFilename);
                    
                    // Delete old profile picture if exists
                    if ($user->getProfilePicture()) {
                        $oldPicturePath = $this->profilePicturesDirectory . '/' . $user->getProfilePicture();
                        if (file_exists($oldPicturePath)) {
                            unlink($oldPicturePath);
                        }
                    }
                    
                    $user->setProfilePicture($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload profile picture: ' . $e->getMessage());
                    return $this->render('profile/edit.html.twig', ['user' => $user]);
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'Profile updated successfully!');
            
            return $this->redirectToRoute('app_profile_show');
        }

        return $this->render('profile/edit.html.twig', [
            'user' => $user,
        ]);
    }
}
