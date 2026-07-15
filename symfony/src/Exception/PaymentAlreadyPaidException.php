<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Levée quand on tente de payer une réservation déjà payée (statut PAID).
 * Correspond à une réponse HTTP 400 Bad Request.
 */
final class PaymentAlreadyPaidException extends \DomainException
{
    public function __construct(int $bookingId)
    {
        parent::__construct(
            sprintf('La réservation n°%d a déjà été payée.', $bookingId),
        );
    }
}
