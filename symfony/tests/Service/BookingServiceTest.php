<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Bus;
use App\Entity\City;
use App\Entity\Route;
use App\Entity\Trip;
use App\Entity\User;
use App\Exception\BookingConflictException;
use App\Exception\SeatOutOfRangeException;
use App\Exception\TripNotAvailableException;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BookingServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private BookingService $bookingService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->bookingService = $container->get(BookingService::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('TRUNCATE TABLE bookings');
        $conn->executeStatement('TRUNCATE TABLE trips');
        $conn->executeStatement('TRUNCATE TABLE buses');
        $conn->executeStatement('TRUNCATE TABLE routes');
        $conn->executeStatement('TRUNCATE TABLE cities');
        $conn->executeStatement('TRUNCATE TABLE users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function testDoubleBookingSameSeatThrowsBookingConflictException(): void
    {
        // Arrange
        $user = (new User())
            ->setEmail('alice@example.com')
            ->setPassword('hashed')
            ->setFullName('Alice');
        $this->em->persist($user);

        $city1 = (new City())->setName('Dakar');
        $city2 = (new City())->setName('Thiès');
        $this->em->persist($city1);
        $this->em->persist($city2);

        $bus = (new Bus())->setPlateNumber('DK-0001')->setTotalSeats(40);
        $this->em->persist($bus);

        $route = (new Route())
            ->setDepartureCity($city1)
            ->setArrivalCity($city2)
            ->setBasePrice('2500.00');
        $this->em->persist($route);

        $trip = (new Trip())
            ->setRoute($route)
            ->setBus($bus)
            ->setDepartureTime(new \DateTime('+1 day'))
            ->setArrivalTime(new \DateTime('+1 day +3 hours'));
        $this->em->persist($trip);

        $this->em->flush();

        // Act — première réservation doit réussir
        $this->bookingService->createBooking($user, $trip, 12);

        // Assert — deuxième réservation sur le même siège doit lever l'exception
        $this->expectException(BookingConflictException::class);
        $this->bookingService->createBooking($user, $trip, 12);
    }

    public function testBookingOnCancelledTripThrowsTripNotAvailableException(): void
    {
        // Arrange
        $user = (new User())
            ->setEmail('bob@example.com')
            ->setPassword('hashed')
            ->setFullName('Bob');
        $this->em->persist($user);

        $city1 = (new City())->setName('Dakar');
        $city2 = (new City())->setName('Thiès');
        $this->em->persist($city1);
        $this->em->persist($city2);

        $bus = (new Bus())->setPlateNumber('DK-0002')->setTotalSeats(40);
        $this->em->persist($bus);

        $route = (new Route())
            ->setDepartureCity($city1)
            ->setArrivalCity($city2)
            ->setBasePrice('2500.00');
        $this->em->persist($route);

        $trip = (new Trip())
            ->setRoute($route)
            ->setBus($bus)
            ->setDepartureTime(new \DateTime('+1 day'))
            ->setArrivalTime(new \DateTime('+1 day +3 hours'))
            ->setStatus('CANCELLED');
        $this->em->persist($trip);

        $this->em->flush();

        // Assert
        $this->expectException(TripNotAvailableException::class);
        $this->bookingService->createBooking($user, $trip, 1);
    }

    public function testSeatAboveBusCapacityThrowsSeatOutOfRangeException(): void
    {
        // Arrange — bus de 10 sièges
        $user = (new User())
            ->setEmail('carol@example.com')
            ->setPassword('hashed')
            ->setFullName('Carol');
        $this->em->persist($user);

        $city1 = (new City())->setName('Dakar');
        $city2 = (new City())->setName('Thiès');
        $this->em->persist($city1);
        $this->em->persist($city2);

        $bus = (new Bus())->setPlateNumber('DK-0003')->setTotalSeats(10);
        $this->em->persist($bus);

        $route = (new Route())
            ->setDepartureCity($city1)
            ->setArrivalCity($city2)
            ->setBasePrice('2500.00');
        $this->em->persist($route);

        $trip = (new Trip())
            ->setRoute($route)
            ->setBus($bus)
            ->setDepartureTime(new \DateTime('+1 day'))
            ->setArrivalTime(new \DateTime('+1 day +3 hours'));
        $this->em->persist($trip);

        $this->em->flush();

        // Assert — siège 11 sur un bus de 10 places doit lever l'exception
        $this->expectException(SeatOutOfRangeException::class);
        $this->bookingService->createBooking($user, $trip, 11);
    }

    public function testCreateBookingReturnsCorrectBookingData(): void
    {
        // Arrange
        $user = (new User())
            ->setEmail('diana@example.com')
            ->setPassword('hashed')
            ->setFullName('Diana');
        $this->em->persist($user);

        $city1 = (new City())->setName('Dakar');
        $city2 = (new City())->setName('Thiès');
        $this->em->persist($city1);
        $this->em->persist($city2);

        $bus = (new Bus())->setPlateNumber('DK-0004')->setTotalSeats(40);
        $this->em->persist($bus);

        $route = (new Route())
            ->setDepartureCity($city1)
            ->setArrivalCity($city2)
            ->setBasePrice('2500.00');
        $this->em->persist($route);

        $trip = (new Trip())
            ->setRoute($route)
            ->setBus($bus)
            ->setDepartureTime(new \DateTime('+1 day'))
            ->setArrivalTime(new \DateTime('+1 day +3 hours'));
        $this->em->persist($trip);

        $this->em->flush();

        // Act
        $booking = $this->bookingService->createBooking($user, $trip, 5);

        // Assert
        self::assertNotNull($booking->getId());
        self::assertSame(5, $booking->getSeatNumber());
        self::assertSame('PENDING', $booking->getStatus());
        self::assertSame($user->getEmail(), $booking->getUser()->getEmail());
        self::assertSame($trip->getId(), $booking->getTrip()->getId());
        self::assertSame('ADULT', $booking->getPassengerType());
        self::assertSame('2500', $booking->getUnitPrice());
    }

    public function testChildBookingApplies25PercentDiscount(): void
    {
        $user = (new User())->setEmail('grace@example.com')->setPassword('hashed')->setFullName('Grace');
        $this->em->persist($user);

        $city1 = (new City())->setName('Dakar');
        $city2 = (new City())->setName('Thiès');
        $this->em->persist($city1);
        $this->em->persist($city2);

        $bus = (new Bus())->setPlateNumber('DK-0006')->setTotalSeats(40);
        $this->em->persist($bus);

        $route = (new Route())->setDepartureCity($city1)->setArrivalCity($city2)->setBasePrice('100.00');
        $this->em->persist($route);

        $trip = (new Trip())
            ->setRoute($route)->setBus($bus)
            ->setDepartureTime(new \DateTime('+1 day'))
            ->setArrivalTime(new \DateTime('+1 day +3 hours'));
        $this->em->persist($trip);
        $this->em->flush();

        $booking = $this->bookingService->createBooking($user, $trip, 3, 'CHILD');

        self::assertSame('CHILD', $booking->getPassengerType());
        self::assertSame('75', $booking->getUnitPrice());
    }

    public function testBabyBookingHasZeroUnitPrice(): void
    {
        $user = (new User())->setEmail('henry@example.com')->setPassword('hashed')->setFullName('Henry');
        $this->em->persist($user);

        $city1 = (new City())->setName('Dakar');
        $city2 = (new City())->setName('Thiès');
        $this->em->persist($city1);
        $this->em->persist($city2);

        $bus = (new Bus())->setPlateNumber('DK-0007')->setTotalSeats(40);
        $this->em->persist($bus);

        $route = (new Route())->setDepartureCity($city1)->setArrivalCity($city2)->setBasePrice('100.00');
        $this->em->persist($route);

        $trip = (new Trip())
            ->setRoute($route)->setBus($bus)
            ->setDepartureTime(new \DateTime('+1 day'))
            ->setArrivalTime(new \DateTime('+1 day +3 hours'));
        $this->em->persist($trip);
        $this->em->flush();

        $booking = $this->bookingService->createBooking($user, $trip, 4, 'BABY');

        self::assertSame('BABY', $booking->getPassengerType());
        self::assertSame('0.00', $booking->getUnitPrice());
    }

    public function testTwoUsersCannotBookSameSeatOnSameTrip(): void
    {
        // Arrange
        $user1 = (new User())
            ->setEmail('eve@example.com')
            ->setPassword('hashed')
            ->setFullName('Eve');
        $this->em->persist($user1);

        $user2 = (new User())
            ->setEmail('frank@example.com')
            ->setPassword('hashed')
            ->setFullName('Frank');
        $this->em->persist($user2);

        $city1 = (new City())->setName('Dakar');
        $city2 = (new City())->setName('Thiès');
        $this->em->persist($city1);
        $this->em->persist($city2);

        $bus = (new Bus())->setPlateNumber('DK-0005')->setTotalSeats(40);
        $this->em->persist($bus);

        $route = (new Route())
            ->setDepartureCity($city1)
            ->setArrivalCity($city2)
            ->setBasePrice('2500.00');
        $this->em->persist($route);

        $trip = (new Trip())
            ->setRoute($route)
            ->setBus($bus)
            ->setDepartureTime(new \DateTime('+1 day'))
            ->setArrivalTime(new \DateTime('+1 day +3 hours'));
        $this->em->persist($trip);

        $this->em->flush();

        // Act — premier utilisateur réserve le siège 8
        $this->bookingService->createBooking($user1, $trip, 8);

        // Assert — second utilisateur tente le même siège 8
        $this->expectException(BookingConflictException::class);
        $this->bookingService->createBooking($user2, $trip, 8);
    }
}
