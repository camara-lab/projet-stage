<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Bus;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des trajets de bus.
 *
 * Toutes les requêtes complexes passent par le QueryBuilder Doctrine pour
 * éviter le problème N+1 grâce aux jointures eagerly chargées (addSelect).
 *
 * @extends ServiceEntityRepository<Trip>
 */
class TripRepository extends ServiceEntityRepository
{
    /** Nombre de trajets retournés par page (cahier des charges). */
    public const int PAR_PAGE = 10;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trip::class);
    }

    // -------------------------------------------------------------------------
    // Recherche paginée avec filtres
    // -------------------------------------------------------------------------

    /**
     * Retourne une page de trajets SCHEDULED selon les filtres fournis.
     *
     * Accepte soit des IDs de villes (from/to) soit des noms (departureCity/arrivalCity).
     * Les IDs ont la priorité si fournis.
     *
     * @return Trip[]
     */
    public function findByFilters(
        ?string $villeDepart,
        ?string $villeArrivee,
        ?\DateTimeInterface $date,
        int $page,
        ?int $fromId = null,
        ?int $toId = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->join('t.route', 'r')
            ->join('r.departureCity', 'dc')
            ->join('r.arrivalCity', 'ac')
            ->join('t.bus', 'b')
            ->addSelect('r', 'dc', 'ac', 'b')
            ->where('t.status = :statut')
            ->setParameter('statut', 'SCHEDULED')
            ->orderBy('t.departureTime', 'ASC')
            ->setFirstResult(($page - 1) * self::PAR_PAGE)
            ->setMaxResults(self::PAR_PAGE);

        if (null !== $fromId) {
            $qb->andWhere('dc.id = :fromId')->setParameter('fromId', $fromId);
        } elseif (null !== $villeDepart && '' !== $villeDepart) {
            $qb->andWhere('dc.name LIKE :depart')->setParameter('depart', $villeDepart.'%');
        }

        if (null !== $toId) {
            $qb->andWhere('ac.id = :toId')->setParameter('toId', $toId);
        } elseif (null !== $villeArrivee && '' !== $villeArrivee) {
            $qb->andWhere('ac.name LIKE :arrivee')->setParameter('arrivee', $villeArrivee.'%');
        }

        if (null !== $date) {
            $qb->andWhere('t.departureTime >= :debutJournee')
               ->andWhere('t.departureTime <= :finJournee')
               ->setParameter('debutJournee', $date->format('Y-m-d').' 00:00:00')
               ->setParameter('finJournee', $date->format('Y-m-d').' 23:59:59');
        }

        /** @var Trip[] $resultats */
        $resultats = $qb->getQuery()->getResult();

        return $resultats;
    }

    /**
     * Compte le nombre total de trajets correspondant aux filtres (pour la pagination).
     */
    public function countByFilters(
        ?string $villeDepart,
        ?string $villeArrivee,
        ?\DateTimeInterface $date,
        ?int $fromId = null,
        ?int $toId = null,
    ): int {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->join('t.route', 'r')
            ->join('r.departureCity', 'dc')
            ->join('r.arrivalCity', 'ac')
            ->where('t.status = :statut')
            ->setParameter('statut', 'SCHEDULED');

        if (null !== $fromId) {
            $qb->andWhere('dc.id = :fromId')->setParameter('fromId', $fromId);
        } elseif (null !== $villeDepart && '' !== $villeDepart) {
            $qb->andWhere('dc.name LIKE :depart')->setParameter('depart', $villeDepart.'%');
        }

        if (null !== $toId) {
            $qb->andWhere('ac.id = :toId')->setParameter('toId', $toId);
        } elseif (null !== $villeArrivee && '' !== $villeArrivee) {
            $qb->andWhere('ac.name LIKE :arrivee')->setParameter('arrivee', $villeArrivee.'%');
        }

        if (null !== $date) {
            $qb->andWhere('t.departureTime >= :debutJournee')
               ->andWhere('t.departureTime <= :finJournee')
               ->setParameter('debutJournee', $date->format('Y-m-d').' 00:00:00')
               ->setParameter('finJournee', $date->format('Y-m-d').' 23:59:59');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    // -------------------------------------------------------------------------
    // Calcul des places disponibles (batch — évite le N+1)
    // -------------------------------------------------------------------------

    /**
     * Retourne un tableau [tripId => nbSiègesRéservés] pour une liste de trajets.
     *
     * Un seul appel SQL remplace N appels countSiegesReserves() en boucle.
     *
     * @param Trip[] $trajets
     * @return array<int, int>
     */
    public function countSiegesReservesBatch(array $trajets): array
    {
        if ([] === $trajets) {
            return [];
        }

        $rows = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(b.trip) AS tripId, COUNT(b.id) AS nb')
            ->from(Booking::class, 'b')
            ->where('b.trip IN (:trajets)')
            ->andWhere('b.status != :annule')
            ->setParameter('trajets', $trajets)
            ->setParameter('annule', 'CANCELLED')
            ->groupBy('b.trip')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['tripId']] = (int) $row['nb'];
        }

        return $map;
    }

    // -------------------------------------------------------------------------
    // Gestion des conflits de bus (admin)
    // -------------------------------------------------------------------------

    /**
     * Vérifie si un bus est déjà affecté à un autre trajet sur la même plage horaire.
     *
     * Règle de chevauchement : deux plages [A, B] et [C, D] se chevauchent si A < D ET B > C.
     *
     * Exemple : le bus CTM-W-12345 part de Casa à 08h00 et revient à 11h00 ;
     * on ne peut pas le planifier sur Rabat → Tanger de 10h00 à 13h00.
     *
     * @param int|null $exclureTrajetId Exclut un trajet existant (utile pour la modification)
     */
    public function isBusOccupe(
        Bus $bus,
        \DateTimeInterface $depart,
        \DateTimeInterface $arrivee,
        ?int $exclureTrajetId = null,
    ): bool {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.bus = :bus')
            // Chevauchement : le nouveau trajet commence avant la fin de l'existant
            // ET se termine après le début de l'existant
            ->andWhere('t.departureTime < :arrivee')
            ->andWhere('t.arrivalTime > :depart')
            ->andWhere('t.status != :annule')
            ->setParameter('bus', $bus)
            ->setParameter('depart', $depart)
            ->setParameter('arrivee', $arrivee)
            ->setParameter('annule', 'CANCELLED');

        // Lors d'une modification, on exclut le trajet lui-même
        if (null !== $exclureTrajetId) {
            $qb->andWhere('t.id != :exclure')
               ->setParameter('exclure', $exclureTrajetId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Retourne la liste des numéros de sièges déjà réservés sur un trajet.
     * Utilisé par l'endpoint détail pour colorier la grille de sièges.
     *
     * @return int[]
     */
    public function findBookedSeats(Trip $trajet): array
    {
        $rows = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('b.seatNumber')
            ->from(Booking::class, 'b')
            ->where('b.trip = :trajet')
            ->andWhere('b.status != :annule')
            ->setParameter('trajet', $trajet)
            ->setParameter('annule', 'CANCELLED')
            ->getQuery()
            ->getArrayResult();

        return array_map(fn ($r) => (int) $r['seatNumber'], $rows);
    }

    /**
     * Compte le nombre de sièges déjà réservés sur un trajet donné.
     *
     * Seules les réservations actives (PENDING ou CONFIRMED) sont comptées ;
     * les réservations CANCELLED ne bloquent plus de siège.
     *
     * Exemple : bus de 44 places, 5 réservations actives → 39 places libres.
     */
    public function countSiegesReserves(Trip $trajet): int
    {
        $resultat = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(Booking::class, 'b')
            ->where('b.trip = :trajet')
            ->andWhere('b.status != :annule')
            ->setParameter('trajet', $trajet)
            ->setParameter('annule', 'CANCELLED')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $resultat;
    }
}
