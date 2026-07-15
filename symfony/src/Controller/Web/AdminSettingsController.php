<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/settings', name: 'admin_settings_')]
final class AdminSettingsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        /** @var User $admin */
        $admin  = $this->getUser();
        $errors = [];

        if ($request->isMethod('POST')) {
            $section = $request->request->get('section');

            if (!$this->isCsrfTokenValid('admin-settings', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
                return $this->redirectToRoute('admin_settings_index');
            }

            // ── Section : informations du profil ──────────────────────
            if ($section === 'profile') {
                $fullName = trim((string) $request->request->get('full_name', ''));
                $phone    = trim((string) $request->request->get('phone', ''));

                if ($fullName === '') {
                    $errors['full_name'] = 'Le nom complet est obligatoire.';
                } else {
                    $admin->setFullName($fullName);
                    if ($phone !== '') {
                        $admin->setPhone($phone);
                    }
                    $em->flush();
                    $this->addFlash('success', 'Profil mis à jour.');

                    return $this->redirectToRoute('admin_settings_index');
                }
            }

            // ── Section : changement de mot de passe ──────────────────
            if ($section === 'password') {
                $current  = (string) $request->request->get('current_password', '');
                $new      = (string) $request->request->get('new_password', '');
                $confirm  = (string) $request->request->get('confirm_password', '');

                if (!$hasher->isPasswordValid($admin, $current)) {
                    $errors['current_password'] = 'Mot de passe actuel incorrect.';
                } elseif (strlen($new) < 8) {
                    $errors['new_password'] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
                } elseif ($new !== $confirm) {
                    $errors['confirm_password'] = 'Les mots de passe ne correspondent pas.';
                } else {
                    $admin->setPassword($hasher->hashPassword($admin, $new));
                    $em->flush();
                    $this->addFlash('success', 'Mot de passe modifié avec succès.');

                    return $this->redirectToRoute('admin_settings_index');
                }
            }
        }

        return $this->render('admin/settings/index.html.twig', [
            'admin'  => $admin,
            'errors' => $errors,
        ]);
    }
}
