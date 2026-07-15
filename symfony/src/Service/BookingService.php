<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use App\Exception\BookingConflictException;
use App\Exception\SeatOutOfRangeException;
use App\Exception\TripNotAvailableException;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Service métier responsable de la création des réservations.
 *
 * Vérifie dans l'ordre :
 *  1. Le trajet est disponible (statut SCHEDULED).
 *  2. Le numéro de siège est dans la capacité du bus.
 *  3. Le siège n'est pas déjà réservé (pas de double réservation).
 */
final class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookingRepository $bookingRepository,
        private readonly Security $security,
    ) {
    }

    /**
     * Crée une réservation pour un utilisateur explicitement fourni.
     *
     * @throws TripNotAvailableException si le trajet est annulé ou terminé
     * @throws SeatOutOfRangeException   si le numéro de siège dépasse la capacité du bus
     * @throws BookingConflictException  si le siège est déjà réservé sur ce trajet
     */
    public function createBooking(User $user, Trip $trip, int $seatNumber, string $passengerType = 'ADULT'): Booking
    {
        $allowedTypes = ['ADULT', 'CHILD', 'BABY'];
        if (!\in_array($passengerType, $allowedTypes, true)) {
            $passengerType = 'ADULT';
        }

        $this->assertTripIsAvailable($trip);
        $this->assertSeatInRange($trip, $seatNumber);

        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            // Le SELECT FOR UPDATE dans findOneByTripAndSeat bloque les réservations concurrentes
            $this->assertSeatIsAvailable($trip, $seatNumber);

            $basePrice = (float) $trip->getRoute()->getBasePrice();
            $unitPrice = match ($passengerType) {
                'CHILD' => (string) round($basePrice * 0.75, 2),
                'BABY'  => '0.00',
                default => (string) $basePrice,
            };

            $booking = new Booking();
            $booking->setUser($user);
            $booking->setTrip($trip);
            $booking->setSeatNumber($seatNumber);
            $booking->setPassengerType($passengerType);
            $booking->setUnitPrice($unitPrice);

            $this->em->persist($booking);
            $this->em->flush();

            $conn->commit();
        } catch (BookingConflictException $e) {
            $conn->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw new \RuntimeException('Erreur lors de la création de la réservation.', previous: $e);
        }

        return $booking;
    }

    /**
     * Crée une réservation pour l'utilisateur actuellement authentifié via JWT.
     *
     * @throws \LogicException           si aucun utilisateur authentifié n'est trouvé
     * @throws TripNotAvailableException si le trajet est annulé ou terminé
     * @throws SeatOutOfRangeException   si le numéro de siège dépasse la capacité du bus
     * @throws BookingConflictException  si le siège est déjà réservé sur ce trajet
     */
    public function createBookingForCurrentUser(Trip $trip, int $seatNumber): Booking
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('A valid authenticated user is required to create a booking.');
        }

        return $this->createBooking($user, $trip, $seatNumber);
    }

    /** Vérifie que le trajet est en statut SCHEDULED (ni annulé, ni terminé). */
    private function assertTripIsAvailable(Trip $trip): void
    {
        if (in_array($trip->getStatus(), ['CANCELLED', 'COMPLETED'], true)) {
            $tripId = $trip->getId() ?? throw new \LogicException('Trip must be persisted before booking.');
            throw new TripNotAvailableException($tripId, $trip->getStatus());
        }
    }

    /** Vérifie que le siège demandé est entre 1 et la capacité totale du bus. */
    private function assertSeatInRange(Trip $trip, int $seatNumber): void
    {
        $totalSeats = $trip->getBus()->getTotalSeats();

        if ($seatNumber < 1 || $seatNumber > $totalSeats) {
            $tripId = $trip->getId() ?? throw new \LogicException('Trip must be persisted before booking.');
            throw new SeatOutOfRangeException($seatNumber, $totalSeats, $tripId);
        }
    }

    /** Vérifie qu'aucune autre réservation n'existe déjà pour ce siège sur ce trajet. */
    private function assertSeatIsAvailable(Trip $trip, int $seatNumber): void
    {
        $existing = $this->bookingRepository->findOneByTripAndSeat($trip, $seatNumber);

        if (null !== $existing) {
            $tripId = $trip->getId() ?? throw new \LogicException('Trip must be persisted before booking.');
            throw new BookingConflictException($seatNumber, $tripId);
        }
    }
}
