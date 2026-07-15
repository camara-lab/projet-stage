<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function findOneByTripAndSeat(Trip $trip, int $seatNumber): ?Booking
    {
        return $this->createQueryBuilder('b')
            ->where('b.trip = :trip')
            ->andWhere('b.seatNumber = :seatNumber')
            ->andWhere('b.status != :cancelled')
            ->setParameter('trip', $trip)
            ->setParameter('seatNumber', $seatNumber)
            ->setParameter('cancelled', 'CANCELLED')
            ->setMaxResults(1)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    /** @return Booking[] */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }
}
