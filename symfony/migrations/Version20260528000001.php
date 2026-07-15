<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime la contrainte unique_trip_seat(trip_id, seat_number) qui empêchait
 * la re-réservation d'un siège après annulation.
 * L'unicité est désormais garantie au niveau service (statuts actifs uniquement).
 */
final class Version20260528000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop unique_trip_seat constraint to allow re-booking cancelled seats';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookings DROP INDEX unique_trip_seat');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT unique_trip_seat UNIQUE (trip_id, seat_number)');
    }
}
