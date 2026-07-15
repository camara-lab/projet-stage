<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la contrainte CHECK du cahier des charges :
 * - routes : departure_city_id <> arrival_city_id
 */
final class Version20260602000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CHECK constraint: departure_city != arrival_city on routes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE routes ADD CONSTRAINT chk_different_cities CHECK (departure_city_id <> arrival_city_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE routes DROP CHECK chk_different_cities');
    }
}
