<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Gestion du profil de l'utilisateur connecté.
 *
 * GET  /api/profile  — lit le profil courant
 * PATCH /api/profile — met à jour nom, téléphone et/ou mot de passe
 */
#[AsController]
#[Route('/api/profile', name: 'api_profile_')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }


    #[OA\Get(
        path: '/api/profile',
        summary: 'Lire le profil connecté',
        tags: ['Profile'],
        responses: [
            new OA\Response(response: 200, description: 'Profil retourné'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    #[Route('', name: 'get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->userToArray($user));
    }


    #[OA\Patch(
        path: '/api/profile',
        summary: 'Mettre à jour le profil',
        description: 'Met à jour fullName, phone et/ou mot de passe. Tous les champs sont optionnels.',
        tags: ['Profile'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'fullName', type: 'string', example: 'Youssef Alami'),
                    new OA\Property(property: 'phone', type: 'string', example: '0661234567'),
                    new OA\Property(property: 'currentPassword', type: 'string', example: 'AncienMotDePasse1'),
                    new OA\Property(property: 'newPassword', type: 'string', example: 'NouveauMotDePasse1'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 401, description: 'Mot de passe actuel incorrect'),
        ]
    )]
    #[Route('', name: 'update', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return $this->json(['message' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $errors = [];

        // ── Nom complet ──────────────────────────────────────────────────────
        if (isset($data['fullName'])) {
            $fullName = trim((string) $data['fullName']);
            $violations = $this->validator->validate($fullName, [
                new Assert\NotBlank(message: 'Le nom complet ne peut pas être vide.'),
                new Assert\Length(min: 2, max: 100, minMessage: 'Minimum 2 caractères.'),
            ]);
            if (\count($violations) > 0) {
                $errors['fullName'] = $violations[0]->getMessage();
            } else {
                $user->setFullName($fullName);
            }
        }

        // ── Téléphone ────────────────────────────────────────────────────────
        if (array_key_exists('phone', $data)) {
            $phone = null === $data['phone'] ? null : trim((string) $data['phone']);
            if (null !== $phone && '' !== $phone) {
                $violations = $this->validator->validate($phone, [
                    new Assert\Length(min: 8, max: 20, minMessage: 'Numéro de téléphone trop court.'),
                    new Assert\Regex(pattern: '/^[0-9+\s()\-]+$/', message: 'Format de téléphone invalide.'),
                ]);
                if (\count($violations) > 0) {
                    $errors['phone'] = $violations[0]->getMessage();
                }
            }
            if (!isset($errors['phone'])) {
                $user->setPhone('' === $phone ? null : $phone);
            }
        }

        // ── CIN ──────────────────────────────────────────────────────────────
        if (array_key_exists('cin', $data)) {
            $cin = null === $data['cin'] ? null : strtoupper(trim((string) $data['cin']));
            if (null !== $cin && '' !== $cin) {
                $violations = $this->validator->validate($cin, [
                    new Assert\Length(min: 8, max: 10, minMessage: 'Le CIN doit contenir au moins {{ limit }} caractères.'),
                    new Assert\Regex(pattern: '/^[A-Z]{1,2}[0-9]{5,8}$/', message: 'Format CIN invalide (ex: AB123456).'),
                ]);
                if (\count($violations) > 0) {
                    $errors['cin'] = $violations[0]->getMessage();
                }
            }
            if (!isset($errors['cin'])) {
                $user->setCin('' === $cin ? null : $cin);
            }
        }

        // ── Changement de mot de passe ───────────────────────────────────────
        if (isset($data['newPassword'])) {
            if (empty($data['currentPassword'])) {
                $errors['currentPassword'] = 'Le mot de passe actuel est requis pour changer de mot de passe.';
            } elseif (!$this->passwordHasher->isPasswordValid($user, (string) $data['currentPassword'])) {
                $errors['currentPassword'] = 'Mot de passe actuel incorrect.';
            } else {
                $newPassword = (string) $data['newPassword'];
                $violations = $this->validator->validate($newPassword, [
                    new Assert\NotBlank(message: 'Le nouveau mot de passe ne peut pas être vide.'),
                    new Assert\Length(min: 8, minMessage: 'Minimum 8 caractères.'),
                    new Assert\Regex(pattern: '/[A-Z]/', message: 'Au moins une lettre majuscule.'),
                    new Assert\Regex(pattern: '/[0-9]/', message: 'Au moins un chiffre.'),
                ]);
                if (\count($violations) > 0) {
                    $errors['newPassword'] = $violations[0]->getMessage();
                } else {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
                }
            }
        }

        if ([] !== $errors) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();

        return $this->json($this->userToArray($user));
    }


    /** @return array<string, mixed> */
    private function userToArray(User $user): array
    {
        return [
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),
            'fullName'  => $user->getFullName(),
            'phone'     => $user->getPhone(),
            'cin'       => $user->getCin(),
            'role'      => $user->getRole(),
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
