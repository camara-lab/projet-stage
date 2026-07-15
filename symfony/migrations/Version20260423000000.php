<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial — 7 entités : User, Bus, City, Route, Trip, Booking, Payment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE buses (id BIGINT AUTO_INCREMENT NOT NULL, plate_number VARCHAR(50) NOT NULL, total_seats INT NOT NULL, status VARCHAR(20) DEFAULT 'AVAILABLE' NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_FE00EAF3FCFF3785 (plate_number), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE cities (id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_D95DB16B5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE users (id BIGINT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, full_name VARCHAR(255) NOT NULL, phone VARCHAR(20) DEFAULT NULL, role VARCHAR(10) DEFAULT 'USER' NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), UNIQUE INDEX UNIQ_1483A5E9444F97DD (phone), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE routes (id BIGINT AUTO_INCREMENT NOT NULL, departure_city_id BIGINT NOT NULL, arrival_city_id BIGINT NOT NULL, base_price NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_32D5C2B3918B251E (departure_city_id), INDEX IDX_32D5C2B34067ACA7 (arrival_city_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE trips (id BIGINT AUTO_INCREMENT NOT NULL, route_id BIGINT NOT NULL, bus_id BIGINT NOT NULL, departure_time DATETIME NOT NULL, arrival_time DATETIME NOT NULL, status VARCHAR(20) DEFAULT 'SCHEDULED' NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_AA7370DA34ECB4E6 (route_id), INDEX IDX_AA7370DA2546731D (bus_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE bookings (id BIGINT AUTO_INCREMENT NOT NULL, user_id BIGINT NOT NULL, trip_id BIGINT NOT NULL, seat_number INT NOT NULL, status VARCHAR(20) DEFAULT 'PENDING' NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_7A853C35A76ED395 (user_id), INDEX IDX_7A853C35A5BC2E0E (trip_id), UNIQUE INDEX unique_trip_seat (trip_id, seat_number), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE payments (id BIGINT AUTO_INCREMENT NOT NULL, booking_id BIGINT NOT NULL, amount NUMERIC(10, 2) NOT NULL, payment_method VARCHAR(20) NOT NULL, payment_status VARCHAR(20) NOT NULL, payment_provider VARCHAR(20) NOT NULL, transaction_id VARCHAR(255) DEFAULT NULL, payment_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_65D29B323301C60 (booking_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_7A853C35A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_7A853C35A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trips (id)');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B323301C60 FOREIGN KEY (booking_id) REFERENCES bookings (id)');
        $this->addSql('ALTER TABLE routes ADD CONSTRAINT FK_32D5C2B3918B251E FOREIGN KEY (departure_city_id) REFERENCES cities (id)');
        $this->addSql('ALTER TABLE routes ADD CONSTRAINT FK_32D5C2B34067ACA7 FOREIGN KEY (arrival_city_id) REFERENCES cities (id)');
        $this->addSql('ALTER TABLE trips ADD CONSTRAINT FK_AA7370DA34ECB4E6 FOREIGN KEY (route_id) REFERENCES routes (id)');
        $this->addSql('ALTER TABLE trips ADD CONSTRAINT FK_AA7370DA2546731D FOREIGN KEY (bus_id) REFERENCES buses (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookings DROP FOREIGN KEY FK_7A853C35A76ED395');
        $this->addSql('ALTER TABLE bookings DROP FOREIGN KEY FK_7A853C35A5BC2E0E');
        $this->addSql('ALTER TABLE payments DROP FOREIGN KEY FK_65D29B323301C60');
        $this->addSql('ALTER TABLE routes DROP FOREIGN KEY FK_32D5C2B3918B251E');
        $this->addSql('ALTER TABLE routes DROP FOREIGN KEY FK_32D5C2B34067ACA7');
        $this->addSql('ALTER TABLE trips DROP FOREIGN KEY FK_AA7370DA34ECB4E6');
        $this->addSql('ALTER TABLE trips DROP FOREIGN KEY FK_AA7370DA2546731D');
        $this->addSql('DROP TABLE bookings');
        $this->addSql('DROP TABLE buses');
        $this->addSql('DROP TABLE cities');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE routes');
        $this->addSql('DROP TABLE trips');
        $this->addSql('DROP TABLE users');
    }
}
