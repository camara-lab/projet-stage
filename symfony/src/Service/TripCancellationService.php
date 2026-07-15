<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Trip;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Annule un trajet et cascade l'annulation sur toutes ses réservations.
 *
 * Règles métier :
 *  - Le trajet passe à CANCELLED.
 *  - Chaque réservation PENDING      → CANCELLED.
 *  - Chaque réservation PAID         → CANCELLED + paiement associé → REFUNDED.
 *  - Les réservations déjà CANCELLED ou REFUNDED sont ignorées.
 *
 * Tout est exécuté dans une seule transaction SQL.
 */
final class TripCancellationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return array{nbReservations: int, nbRembourses: int}
     */
    public function cancelTrip(Trip $trip): array
    {
        $this->em->beginTransaction();

        try {
            $trip->setStatus('CANCELLED');

            $nbReservations = 0;
            $nbRembourses   = 0;

            foreach ($trip->getBookings() as $booking) {
                $status = $booking->getStatus();

                if (\in_array($status, ['CANCELLED', 'REFUNDED'], true)) {
                    continue;
                }

                $nbReservations++;

                if ($status === 'PAID' && null !== $booking->getPayment()) {
                    $booking->getPayment()->setPaymentStatus('REFUNDED');
                    $nbRembourses++;
                }

                $booking->setStatus('CANCELLED');
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw new \RuntimeException('Erreur lors de l\'annulation du trajet : '.$e->getMessage(), 0, $e);
        }

        return [
            'nbReservations' => $nbReservations,
            'nbRembourses'   => $nbRembourses,
        ];
    }
}
