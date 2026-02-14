<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cached recommendation profile tables (user_preferences, user_embeddings).';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('user_preferences')) {
            $this->addSql('CREATE TABLE user_preferences (user_id INT NOT NULL, fav_categories JSON DEFAULT NULL, fav_keywords JSON DEFAULT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE user_preferences ADD CONSTRAINT FK_USER_PREFERENCES_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        }

        if (!$schema->hasTable('user_embeddings')) {
            $this->addSql('CREATE TABLE user_embeddings (user_id INT NOT NULL, model VARCHAR(64) NOT NULL, dims SMALLINT NOT NULL, normalized TINYINT(1) NOT NULL DEFAULT 1, vector LONGBLOB NOT NULL, content_hash BINARY(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_USER_EMBEDDINGS_USER (user_id), PRIMARY KEY(user_id, model)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE user_embeddings ADD CONSTRAINT FK_USER_EMBEDDINGS_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('user_embeddings')) {
            $this->addSql('ALTER TABLE user_embeddings DROP FOREIGN KEY FK_USER_EMBEDDINGS_USER');
            $this->addSql('DROP TABLE user_embeddings');
        }

        if ($schema->hasTable('user_preferences')) {
            $this->addSql('ALTER TABLE user_preferences DROP FOREIGN KEY FK_USER_PREFERENCES_USER');
            $this->addSql('DROP TABLE user_preferences');
        }
    }
}
