<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    /**
     * Find a non-expired refresh token by its value.
     */
    public function findValidByToken(string $token): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.token = :token')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Delete all expired tokens (can be called from a maintenance command).
     */
    public function deleteExpired(): int
    {
        return (int) $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt <= :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Delete all refresh tokens for a given user (logout from all devices).
     */
    public function deleteAllForUser(int $userId): void
    {
        $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
