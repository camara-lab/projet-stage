<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Levée quand on tente de réserver sur un trajet CANCELLED ou COMPLETED.
 * Correspond à une réponse HTTP 422 Unprocessable Entity.
 */
final class TripNotAvailableException extends \RuntimeException
{
    public function __construct(int $tripId, string $status)
    {
        parent::__construct(
            sprintf('Trip %d is not available for booking (status: %s).', $tripId, $status)
        );
    }
}
