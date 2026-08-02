<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Décale les trajets planifiés vers l'avenir pour que le site conserve
 * toujours des trajets réservables.
 *
 * Les horaires et les durées sont préservés : seul le jour change, et le
 * même décalage est appliqué à tous les trajets pour garder leur ordre.
 */
#[AsCommand(
    name: 'app:demo:refresh',
    description: 'Décale les trajets planifiés passés vers les prochains jours.',
)]
final class RefreshDemoTripsCommand extends Command
{
    public function __construct(
        private readonly TripRepository $tripRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io  = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        $trajets = $this->tripRepository->findBy(['status' => 'SCHEDULED']);

        if ([] === $trajets) {
            $io->warning('Aucun trajet planifié en base.');

            return Command::SUCCESS;
        }

        // Trajet planifié le plus ancien : sert de référence au décalage
        $plusAncien = null;

        foreach ($trajets as $trajet) {
            if (null === $plusAncien || $trajet->getDepartureTime() < $plusAncien) {
                $plusAncien = $trajet->getDepartureTime();
            }
        }

        // On vise un premier départ demain, en conservant les écarts entre trajets
        $cible = $now->modify('+1 day')->setTime(0, 0);

        if ($plusAncien >= $cible) {
            $io->success('Les trajets sont déjà tous à venir, aucun décalage nécessaire.');

            return Command::SUCCESS;
        }

        // Le décalage en jours entiers peut laisser le trajet dans le passé
        // (l'heure de départ est conservée) : on ajoute un jour si nécessaire.
        $joursDecalage = (int) $plusAncien->diff($cible)->days;

        if ($plusAncien->modify("+{$joursDecalage} days") < $cible) {
            ++$joursDecalage;
        }

        // Mise à jour atomique : les deux colonnes doivent bouger dans la même
        // requête, sinon la contrainte « arrivée après départ » est violée.
        $this->em->getConnection()->executeStatement(
            'UPDATE trips
                SET departure_time = DATE_ADD(departure_time, INTERVAL :jours DAY),
                    arrival_time   = DATE_ADD(arrival_time,   INTERVAL :jours DAY)
              WHERE status = :statut',
            ['jours' => $joursDecalage, 'statut' => 'SCHEDULED'],
        );

        $premier = $this->em->getConnection()->fetchOne(
            "SELECT MIN(departure_time) FROM trips WHERE status = 'SCHEDULED'"
        );

        $io->success(sprintf(
            '%d trajet(s) décalé(s) de %d jour(s). Premier départ : %s.',
            count($trajets),
            $joursDecalage,
            (new \DateTimeImmutable((string) $premier))->format('d/m/Y H:i'),
        ));

        return Command::SUCCESS;
    }
}
