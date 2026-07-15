<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Levée quand la méthode de paiement fournie n'est pas acceptée.
 * Correspond à une réponse HTTP 422 Unprocessable Entity.
 */
final class InvalidPaymentMethodException extends \DomainException
{
    public function __construct(string $methode)
    {
        parent::__construct(
            sprintf(
                'La méthode de paiement "%s" n\'est pas acceptée. '
                .'Méthodes valides : CARD, CASH, TRANSFER.',
                $methode,
            ),
        );
    }
}
