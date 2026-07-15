<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ CIN (Carte d\'Identité Nationale) sur la table users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD cin VARCHAR(10) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CIN ON users (cin)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_CIN ON users');
        $this->addSql('ALTER TABLE users DROP COLUMN cin');
    }
}
