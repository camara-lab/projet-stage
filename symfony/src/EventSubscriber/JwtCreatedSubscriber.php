<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Ajoute les données du profil (fullName, role) dans le payload JWT
 * afin que le frontend puisse afficher le prénom sans appel API supplémentaire.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
final class JwtCreatedSubscriber
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $event->setData(array_merge($event->getData(), [
            'fullName' => $user->getFullName(),
            'role'     => $user->getRole(),
        ]));
    }
}
