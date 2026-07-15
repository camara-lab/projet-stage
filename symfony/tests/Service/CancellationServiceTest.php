<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\Bus;
use App\Entity\City;
use App\Entity\Payment;
use App\Entity\Route;
use App\Entity\Trip;
use App\Entity\User;
use App\Service\TripCancellationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CancellationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TripCancellationService $cancellationService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->cancellationService = $container->get(TripCancellationService::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('TRUNCATE TABLE payments');
        $conn->executeStatement('TRUNCATE TABLE bookings');
        $conn->executeStatement('TRUNCATE TABLE trips');
        $conn->executeStatement('TRUNCATE TABLE buses');
        $conn->executeStatement('TRUNCATE TABLE routes');
        $conn->executeStatement('TRUNCATE TABLE cities');
        $conn->executeStatement('TRUNCATE TABLE users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function buildTrip(): Trip
    {
        $city1 = (new City())->setName('Casablanca');
        $city2 = (new City())->setName('Rabat');
        $this->em->persist($city1);
        $this->em->persist($city2);

        $bus = (new Bus())->setPlateNumber('CAN-99999')->setTotalSeats(40);
        $this->em->persist($bus);

        $route = (new Route())->setDepartureCity($city1)->setArrivalCity($city2)->setBasePrice('80.00');
        $this->em->persist($route);

        $trip = (new Trip())
            ->setRoute($route)
            ->setBus($bus)
            ->setDepartureTime(new \DateTime('+2 days'))
            ->setArrivalTime(new \DateTime('+2 days +3 hours'));
        $this->em->persist($trip);

        return $trip;
    }

    private function buildUser(string $email): User
    {
        $user = (new User())->setEmail($email)->setPassword('hashed')->setFullName('Test');
        $this->em->persist($user);
        return $user;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function testCancelTripSetsTripStatusCancelled(): void
    {
        $trip = $this->buildTrip();
        $this->em->flush();

        $this->cancellationService->cancelTrip($trip);

        self::assertSame('CANCELLED', $trip->getStatus());
    }

    public function testCancelTripCancelsPendingBookings(): void
    {
        $trip = $this->buildTrip();
        $user = $this->buildUser('pending.user@test.ma');

        $booking = (new Booking())->setUser($user)->setTrip($trip)->setSeatNumber(1);
        $this->em->persist($booking);
        $this->em->flush();

        $tripId    = $trip->getId();
        $bookingId = $booking->getId();
        $this->em->clear();
        $trip    = $this->em->find(Trip::class, $tripId);
        $booking = $this->em->find(Booking::class, $bookingId);

        $result = $this->cancellationService->cancelTrip($trip);

        self::assertSame('CANCELLED', $booking->getStatus());
        self::assertSame(1, $result['nbReservations']);
        self::assertSame(0, $result['nbRembourses']);
    }

    public function testCancelTripRefundsPaidBookings(): void
    {
        $trip = $this->buildTrip();
        $user = $this->buildUser('paid.user@test.ma');

        $booking = (new Booking())->setUser($user)->setTrip($trip)->setSeatNumber(2);
        $booking->setStatus('PAID');
        $this->em->persist($booking);

        $payment = new Payment();
        $payment->setBooking($booking);
        $payment->setAmount('80.00');
        $payment->setPaymentMethod('WAFA_CASH');
        $payment->setPaymentProvider('Wafacash');
        $payment->setPaymentStatus('SUCCESS');
        $payment->setTransactionId('TEST-TX-001');
        $this->em->persist($payment);

        $this->em->flush();

        $tripId    = $trip->getId();
        $bookingId = $booking->getId();
        $paymentId = $payment->getId();
        $this->em->clear();
        $trip    = $this->em->find(Trip::class, $tripId);
        $booking = $this->em->find(Booking::class, $bookingId);
        $payment = $this->em->find(Payment::class, $paymentId);

        $result = $this->cancellationService->cancelTrip($trip);

        self::assertSame('CANCELLED', $booking->getStatus());
        self::assertSame('REFUNDED', $payment->getPaymentStatus());
        self::assertSame(1, $result['nbReservations']);
        self::assertSame(1, $result['nbRembourses']);
    }

    public function testCancelTripIgnoresAlreadyCancelledBookings(): void
    {
        $trip = $this->buildTrip();
        $user = $this->buildUser('already.cancelled@test.ma');

        $booking = (new Booking())->setUser($user)->setTrip($trip)->setSeatNumber(3);
        $booking->setStatus('CANCELLED');
        $this->em->persist($booking);
        $this->em->flush();

        $result = $this->cancellationService->cancelTrip($trip);

        self::assertSame(0, $result['nbReservations']);
        self::assertSame(0, $result['nbRembourses']);
    }
}
