<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Levée quand on tente de payer une réservation annulée (statut CANCELLED).
 * Correspond à une réponse HTTP 400 Bad Request.
 */
final class PaymentCancelledException extends \DomainException
{
    public function __construct(int $bookingId)
    {
        parent::__construct(
            sprintf('La réservation n°%d est annulée et ne peut plus être payée.', $bookingId),
        );
    }
}
