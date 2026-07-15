<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegisterRequest;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Contrôleur d'authentification.
 *
 * POST /api/auth/register  → Crée un compte utilisateur.
 * POST /api/auth/login     → Géré par Lexik JWT + LoginSuccessListener (cookie HttpOnly).
 * POST /api/auth/refresh   → Lit le cookie busgo_rt, retourne un nouvel access token.
 * POST /api/auth/logout    → Révoque le refresh token et supprime le cookie.
 *
 * Sécurité refresh token :
 *   Le refresh token n'est jamais exposé dans le corps JSON.
 *   Il voyage uniquement dans un cookie HttpOnly Secure SameSite=Strict,
 *   ce qui le rend inaccessible aux scripts JavaScript (protection XSS).
 */
final class AuthController extends AbstractController
{
    private const COOKIE_NAME   = 'busgo_rt';
    private const COOKIE_PATH   = '/api/auth';
    private const TTL_SECONDS   = 2_592_000; // 30 jours

    public function __construct(
        private readonly EntityManagerInterface       $em,
        private readonly UserPasswordHasherInterface  $passwordHasher,
        private readonly ValidatorInterface           $validator,
        private readonly UserRepository               $userRepository,
        private readonly RefreshTokenRepository       $refreshTokenRepository,
        private readonly JWTTokenManagerInterface     $jwtManager,
        private readonly bool                         $cookieSecure,
    ) {
    }

    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Créer un compte',
        tags: ['Auth'],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'fullName'],
                properties: [
                    new OA\Property(property: 'email',    type: 'string', format: 'email', example: 'nouveau@test.ma'),
                    new OA\Property(property: 'password', type: 'string', example: 'MonMotDePasse1'),
                    new OA\Property(property: 'fullName', type: 'string', example: 'Youssef Alami'),
                    new OA\Property(property: 'phone',    type: 'string', example: '0661234567'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé'),
            new OA\Response(response: 409, description: 'Email déjà utilisé'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ]
    )]
    #[Route('/api/auth/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return new JsonResponse(['message' =>'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $dto = new RegisterRequest(
            email:    (string) ($data['email']    ?? ''),
            password: (string) ($data['password'] ?? ''),
            fullName: (string) ($data['fullName'] ?? ''),
            phone:    isset($data['phone']) ? (string) $data['phone'] : null,
            cin:      isset($data['cin'])   ? (string) $data['cin']   : null,
        );

        $violations = $this->validator->validate($dto);

        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $v) {
                $errors[$v->getPropertyPath()] = $v->getMessage();
            }
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null !== $this->userRepository->findOneBy(['email' => $dto->email])) {
            return new JsonResponse(
                ['errors' => ['email' => 'Cette adresse e-mail est déjà utilisée.']],
                Response::HTTP_CONFLICT,
            );
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setFullName($dto->fullName);
        $user->setPhone($dto->phone);
        $user->setCin($dto->cin);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));

        $this->em->persist($user);
        $this->em->flush();

        return new JsonResponse(
            ['id' => $user->getId(), 'email' => $user->getEmail(), 'fullName' => $user->getFullName()],
            Response::HTTP_CREATED,
        );
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        summary: 'Renouveler l\'access token via cookie',
        description: 'Le refresh token est lu depuis le cookie HttpOnly `busgo_rt`. Aucun body requis.',
        tags: ['Auth'],
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Nouvel access token JWT'),
            new OA\Response(response: 401, description: 'Cookie absent ou refresh token invalide/expiré'),
        ]
    )]
    #[Route('/api/auth/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $tokenValue = $request->cookies->get(self::COOKIE_NAME, '');

        if ('' === $tokenValue) {
            return $this->unauthorizedWithClearedCookie('Refresh token manquant.');
        }

        $refreshToken = $this->refreshTokenRepository->findValidByToken($tokenValue);

        if (null === $refreshToken) {
            return $this->unauthorizedWithClearedCookie('Refresh token invalide ou expiré.');
        }

        $user = $refreshToken->getUser();

        // Rotation : révoquer l'ancien token
        $this->em->remove($refreshToken);

        // Créer un nouveau refresh token
        $newValue   = bin2hex(random_bytes(64));
        $expiresAt  = new \DateTime(sprintf('+%d seconds', self::TTL_SECONDS));

        $newRefreshToken = new RefreshToken($user, $newValue, $expiresAt);
        $this->em->persist($newRefreshToken);
        $this->em->flush();

        // Nouveau access token JWT
        $accessToken = $this->jwtManager->create($user);

        $response = new JsonResponse(['token' => $accessToken]);
        $response->headers->setCookie($this->buildCookie($newValue, $expiresAt));

        return $response;
    }

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Se déconnecter',
        description: 'Révoque le refresh token du cookie et le supprime. L\'access token JWT expire naturellement (TTL 1h).',
        tags: ['Auth'],
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Déconnexion réussie'),
        ]
    )]
    #[Route('/api/auth/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $tokenValue = $request->cookies->get(self::COOKIE_NAME, '');

        if ('' !== $tokenValue) {
            $refreshToken = $this->refreshTokenRepository->findOneBy(['token' => $tokenValue]);
            if (null !== $refreshToken) {
                $this->em->remove($refreshToken);
                $this->em->flush();
            }
        }

        $response = new JsonResponse(['message' => 'Déconnexion réussie.']);
        $response->headers->clearCookie(self::COOKIE_NAME, self::COOKIE_PATH, null, $this->cookieSecure, true);

        return $response;
    }

    #[Route('/api/auth/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent(), true);
        $email = trim((string) ($data['email'] ?? ''));

        if ('' === $email) {
            return new JsonResponse(['message' => 'Email requis.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        // Réponse identique que l'utilisateur existe ou non (sécurité anti-énumération)
        $genericResponse = new JsonResponse([
            'message' => 'Si cet email est enregistré, un lien de réinitialisation a été envoyé.',
        ]);

        if (null === $user) {
            return $genericResponse;
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $user->setResetToken($token);
        $user->setResetTokenExpiresAt($expiresAt);
        $this->em->flush();

        // En prod : envoyer un email avec le lien. Ici : retour du token pour dev.
        return new JsonResponse([
            'message'   => 'Si cet email est enregistré, un lien de réinitialisation a été envoyé.',
            '_dev_token' => $token, // à retirer en production
        ]);
    }

    #[Route('/api/auth/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data     = json_decode($request->getContent(), true);
        $token    = trim((string) ($data['token']    ?? ''));
        $password = trim((string) ($data['password'] ?? ''));

        if ('' === $token || '' === $password) {
            return new JsonResponse(['message' => 'Token et nouveau mot de passe requis.'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8) {
            return new JsonResponse(['message' => 'Le mot de passe doit contenir au moins 8 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->userRepository->findOneBy(['resetToken' => $token]);

        if (null === $user) {
            return new JsonResponse(['message' => 'Lien invalide ou expiré.'], Response::HTTP_BAD_REQUEST);
        }

        if ($user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $this->em->flush();
            return new JsonResponse(['message' => 'Lien expiré. Veuillez refaire une demande.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $this->em->flush();

        return new JsonResponse(['message' => 'Mot de passe modifié avec succès.']);
    }

    private function buildCookie(string $value, \DateTime $expiresAt): Cookie
    {
        return new Cookie(
            name:     self::COOKIE_NAME,
            value:    $value,
            expire:   $expiresAt,
            path:     self::COOKIE_PATH,
            domain:   null,
            secure:   $this->cookieSecure,
            httpOnly: true,
            raw:      false,
            sameSite: Cookie::SAMESITE_STRICT,
        );
    }

    private function unauthorizedWithClearedCookie(string $message): JsonResponse
    {
        $response = new JsonResponse(['message' => $message], Response::HTTP_UNAUTHORIZED);
        $response->headers->clearCookie(self::COOKIE_NAME, self::COOKIE_PATH, null, $this->cookieSecure, true);
        return $response;
    }
}
