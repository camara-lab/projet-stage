<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

final class AdminStatsService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** CA total des paiements SUCCESS. */
    public function chiffreAffairesTotal(): float
    {
        $sql = "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'SUCCESS'";
        return (float) $this->em->getConnection()->executeQuery($sql)->fetchOne();
    }

    /** Taux d'occupation moyen de tous les trajets. */
    public function tauxOccupationMoyen(): float
    {
        $sql = "
            SELECT COALESCE(AVG(reserves.nb_reserves / b.total_seats * 100), 0) AS taux
            FROM trips t
            JOIN buses b ON t.bus_id = b.id
            LEFT JOIN (
                SELECT trip_id, COUNT(*) AS nb_reserves FROM bookings
                WHERE status != 'CANCELLED' GROUP BY trip_id
            ) AS reserves ON reserves.trip_id = t.id
            WHERE t.status IN ('SCHEDULED', 'COMPLETED')
        ";
        return round((float) $this->em->getConnection()->executeQuery($sql)->fetchOne(), 2);
    }

    /** Top 3 lignes les plus réservées. */
    public function topTroisLignes(): array
    {
        $sql = "
            SELECT CONCAT(c_depart.name, ' → ', c_arrivee.name) AS ligne,
                   COUNT(bk.id) AS nb_reservations
            FROM bookings bk
            JOIN trips t ON bk.trip_id = t.id
            JOIN routes r ON t.route_id = r.id
            JOIN cities c_depart  ON r.departure_city_id = c_depart.id
            JOIN cities c_arrivee ON r.arrival_city_id   = c_arrivee.id
            WHERE bk.status != 'CANCELLED'
            GROUP BY r.id, c_depart.name, c_arrivee.name
            ORDER BY nb_reservations DESC LIMIT 3
        ";
        $lignes = $this->em->getConnection()->executeQuery($sql)->fetchAllAssociative();
        return array_map(
            static fn(array $r): array => ['ligne' => $r['ligne'], 'nbReservations' => (int) $r['nb_reservations']],
            $lignes,
        );
    }

    /** Répartition des réservations par statut. */
    public function reservationsParStatut(): array
    {
        $sql = "SELECT status, COUNT(*) AS nb FROM bookings GROUP BY status";
        $rows = $this->em->getConnection()->executeQuery($sql)->fetchAllAssociative();
        $result = ['PENDING' => 0, 'PAID' => 0, 'CANCELLED' => 0, 'REFUNDED' => 0];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['nb'];
        }
        return $result;
    }

    /** CA des 6 derniers mois (pour le graphique). */
    public function caParMois(): array
    {
        $sql = "
            SELECT DATE_FORMAT(payment_date, '%Y-%m') AS mois,
                   COALESCE(SUM(amount), 0) AS total
            FROM payments
            WHERE payment_status = 'SUCCESS'
              AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY mois ORDER BY mois ASC
        ";
        $rows = $this->em->getConnection()->executeQuery($sql)->fetchAllAssociative();

        // Remplir les mois manquants avec 0
        $mois = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("-$i months"));
            $mois[$key] = 0.0;
        }
        foreach ($rows as $row) {
            $mois[$row['mois']] = (float) $row['total'];
        }
        return $mois;
    }

    /** Nombre de trajets SCHEDULED à venir dans les 7 prochains jours. */
    public function trajetsASemaine(): int
    {
        $sql = "SELECT COUNT(*) FROM trips WHERE status = 'SCHEDULED' AND departure_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)";
        return (int) $this->em->getConnection()->executeQuery($sql)->fetchOne();
    }

    /** Nombre total de passagers actifs (ayant au moins 1 réservation non annulée). */
    public function passagersActifs(): int
    {
        $sql = "SELECT COUNT(DISTINCT user_id) FROM bookings WHERE status != 'CANCELLED'";
        return (int) $this->em->getConnection()->executeQuery($sql)->fetchOne();
    }

    /** 5 dernières réservations pour le fil d'activité. */
    public function dernieresReservations(): array
    {
        $sql = "
            SELECT bk.id, bk.status, bk.seat_number, bk.created_at,
                   u.full_name, u.email,
                   c1.name AS ville_depart, c2.name AS ville_arrivee,
                   t.departure_time
            FROM bookings bk
            JOIN users u ON bk.user_id = u.id
            JOIN trips t ON bk.trip_id = t.id
            JOIN routes r ON t.route_id = r.id
            JOIN cities c1 ON r.departure_city_id = c1.id
            JOIN cities c2 ON r.arrival_city_id = c2.id
            ORDER BY bk.created_at DESC LIMIT 5
        ";
        return $this->em->getConnection()->executeQuery($sql)->fetchAllAssociative();
    }
}
