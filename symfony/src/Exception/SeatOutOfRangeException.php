<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Levée quand le numéro de siège demandé dépasse la capacité du bus.
 * Correspond à une réponse HTTP 422 Unprocessable Entity.
 */
final class SeatOutOfRangeException extends \DomainException
{
    public function __construct(int $seatNumber, int $totalSeats, int $tripId)
    {
        parent::__construct(
            sprintf(
                'Seat %d is out of range for trip %d (bus capacity: %d seats).',
                $seatNumber,
                $tripId,
                $totalSeats,
            ),
        );
    }
}
