<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout contrainte CHECK arrival_time > departure_time + index performance trips';
    }

    public function up(Schema $schema): void
    {
        // Contrainte CDC : l'heure d'arrivée doit être après l'heure de départ
        $this->addSql('ALTER TABLE trips ADD CONSTRAINT chk_arrival_after_departure CHECK (arrival_time > departure_time)');

        // Index performance : recherche de trajets par date (filtre le plus fréquent)
        $this->addSql('CREATE INDEX idx_trips_departure_time ON trips (departure_time)');

        // Index performance : filtre par statut (WHERE status = "SCHEDULED" sur toutes les recherches)
        $this->addSql('CREATE INDEX idx_trips_status ON trips (status)');

        // Index performance : filtre sur statut des réservations (admin + user)
        $this->addSql('CREATE INDEX idx_bookings_status ON bookings (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trips DROP CONSTRAINT chk_arrival_after_departure');
        $this->addSql('DROP INDEX idx_trips_departure_time ON trips');
        $this->addSql('DROP INDEX idx_trips_status ON trips');
        $this->addSql('DROP INDEX idx_bookings_status ON bookings');
    }
}
