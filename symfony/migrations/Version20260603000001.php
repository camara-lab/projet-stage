<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Création de la table refresh_tokens pour la gestion native des JWT refresh tokens.
 */
final class Version20260603000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table refresh_tokens (implémentation native JWT refresh token)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_tokens (
                id         INT AUTO_INCREMENT NOT NULL,
                user_id    BIGINT NOT NULL,
                token      VARCHAR(128) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_REFRESH_TOKEN (token),
                INDEX IDX_REFRESH_USER (user_id),
                CONSTRAINT FK_REFRESH_TOKEN_USER
                    FOREIGN KEY (user_id)
                    REFERENCES users (id)
                    ON DELETE CASCADE,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_tokens');
    }
}
