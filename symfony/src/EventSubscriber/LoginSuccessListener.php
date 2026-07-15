<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * À chaque connexion réussie, génère un refresh token, le persiste en base
 * et le place dans un cookie HttpOnly Secure — jamais dans le corps JSON.
 *
 * Cela élimine la vulnérabilité XSS liée au stockage du refresh token
 * en localStorage : aucun script JS ne peut lire le cookie.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
final class LoginSuccessListener
{
    private const COOKIE_NAME = 'busgo_rt';
    private const TTL_SECONDS = 2_592_000; // 30 jours

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly bool $cookieSecure,
    ) {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        // Générer un token aléatoire cryptographiquement sûr
        $tokenValue = bin2hex(random_bytes(64));
        $expiresAt  = new \DateTime(sprintf('+%d seconds', self::TTL_SECONDS));

        // Persister le refresh token en base
        $refreshToken = new RefreshToken($user, $tokenValue, $expiresAt);
        $this->em->persist($refreshToken);
        $this->em->flush();

        // Placer le refresh token dans un cookie HttpOnly — inaccessible à JavaScript
        $cookie = new Cookie(
            name:     self::COOKIE_NAME,
            value:    $tokenValue,
            expire:   $expiresAt,
            path:     '/api/auth',      // ← limité aux endpoints auth seulement
            domain:   null,
            secure:   $this->cookieSecure,
            httpOnly: true,
            raw:      false,
            sameSite: Cookie::SAMESITE_STRICT,
        );

        $event->getResponse()->headers->setCookie($cookie);

        // Supprimer le refresh token du corps JSON (il ne doit jamais transiter en clair)
        $data = $event->getData();
        unset($data['refresh_token'], $data['refresh_token_expires_at']);
        $event->setData($data);
    }
}
