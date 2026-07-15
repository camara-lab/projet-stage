<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\Bus;
use App\Entity\City;
use App\Entity\Route;
use App\Entity\Trip;
use App\Entity\User;
use App\Exception\InvalidPaymentMethodException;
use App\Exception\PaymentAlreadyPaidException;
use App\Exception\PaymentCancelledException;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PaymentServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PaymentService $paymentService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->paymentService = $container->get(PaymentService::class);

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function makeBooking(string $status = 'PENDING'): Booking
    {
        $depart = new City(); $depart->setName('TestDepart_'.uniqid());
        $arrivee = new City(); $arrivee->setName('TestArrivee_'.uniqid());
        $this->em->persist($depart); $this->em->persist($arrivee);

        $bus = new Bus();
        $bus->setPlateNumber('TEST-'.uniqid());
        $bus->setTotalSeats(30);
        $this->em->persist($bus);

        $route = new Route();
        $route->setDepartureCity($depart);
        $route->setArrivalCity($arrivee);
        $route->setBasePrice('100.00');
        $this->em->persist($route);

        $trip = new Trip();
        $trip->setRoute($route);
        $trip->setBus($bus);
        $trip->setDepartureTime(new \DateTime('+1 day'));
        $trip->setArrivalTime(new \DateTime('+1 day +3 hours'));
        $trip->setStatus('SCHEDULED');
        $this->em->persist($trip);

        $user = new User();
        $user->setEmail('pay_test_'.uniqid().'@test.ma');
        $user->setFullName('Test User');
        $user->setPassword('hashed');
        $this->em->persist($user);

        $booking = new Booking();
        $booking->setUser($user);
        $booking->setTrip($trip);
        $booking->setSeatNumber(1);
        $booking->setStatus($status);
        $booking->setPassengerType('ADULT');
        $booking->setUnitPrice('100.00');
        $this->em->persist($booking);

        $this->em->flush();

        return $booking;
    }

    public function testPaymentSuccessChangesBookingToPaid(): void
    {
        $booking = $this->makeBooking('PENDING');
        $payment = $this->paymentService->processPayment($booking, 'CARD');

        self::assertSame('PAID', $booking->getStatus());
        self::assertNotNull($payment->getId());
        self::assertNotNull($payment->getTransactionId());
    }

    public function testInvalidPaymentMethodThrows(): void
    {
        $this->expectException(InvalidPaymentMethodException::class);
        $booking = $this->makeBooking('PENDING');
        $this->paymentService->processPayment($booking, 'BITCOIN');
    }

    public function testAlreadyPaidBookingThrows(): void
    {
        $this->expectException(PaymentAlreadyPaidException::class);
        $booking = $this->makeBooking('PAID');
        $this->paymentService->processPayment($booking, 'CARD');
    }

    public function testCancelledBookingThrows(): void
    {
        $this->expectException(PaymentCancelledException::class);
        $booking = $this->makeBooking('CANCELLED');
        $this->paymentService->processPayment($booking, 'CARD');
    }

    public function testPaymentAmountEqualsBookingUnitPrice(): void
    {
        $booking = $this->makeBooking('PENDING');
        $payment = $this->paymentService->processPayment($booking, 'CARD');

        self::assertSame($booking->getUnitPrice(), $payment->getAmount());
    }
}
