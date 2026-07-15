<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Levée quand un siège est déjà réservé sur un trajet donné.
 * Correspond à une réponse HTTP 409 Conflict.
 */
final class BookingConflictException extends \RuntimeException
{
    public function __construct(int $seatNumber, int $tripId)
    {
        parent::__construct(
            sprintf('Seat %d on trip %d is already booked.', $seatNumber, $tripId)
        );
    }
}
