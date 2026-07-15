<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260623130456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_booking_seat ON bookings (trip_id, seat_number)');
        $this->addSql('ALTER TABLE refresh_tokens RENAME INDEX uniq_refresh_token TO UNIQ_9BACE7E15F37A13B');
        $this->addSql('ALTER TABLE refresh_tokens RENAME INDEX idx_refresh_user TO IDX_9BACE7E1A76ED395');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_booking_seat ON bookings');
        $this->addSql('ALTER TABLE refresh_tokens RENAME INDEX idx_9bace7e1a76ed395 TO IDX_REFRESH_USER');
        $this->addSql('ALTER TABLE refresh_tokens RENAME INDEX uniq_9bace7e15f37a13b TO UNIQ_REFRESH_TOKEN');
    }
}
