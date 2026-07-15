<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Booking;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Contrôle l'accès aux réservations.
 *
 * Règles :
 *  - VIEW   : le propriétaire de la réservation ou un ADMIN peut la consulter.
 *  - PAY    : le propriétaire ou un ADMIN peut initier le paiement.
 *  - CANCEL : le propriétaire ou un ADMIN peut annuler la réservation.
 *
 * @extends Voter<'VIEW'|'PAY'|'CANCEL', Booking>
 */
final class BookingVoter extends Voter
{
    /** Attribut pour consulter une réservation */
    public const string VIEW = 'VIEW';

    /** Attribut pour payer une réservation */
    public const string PAY = 'PAY';

    /** Attribut pour annuler une réservation */
    public const string CANCEL = 'CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::PAY, self::CANCEL], true)
            && $subject instanceof Booking;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /* @var Booking $subject */
        return $subject->getUser()->getId() === $user->getId()
            || \in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
