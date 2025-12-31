<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251231104858 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE presentation_embeddings (model VARCHAR(64) NOT NULL, presentation_id INT NOT NULL, dims SMALLINT NOT NULL, normalized TINYINT(1) DEFAULT 1 NOT NULL, vector LONGBLOB NOT NULL, content_hash VARBINARY(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_70306090AB627E8B (presentation_id), PRIMARY KEY(presentation_id, model)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE presentation_neighbors (model VARCHAR(64) NOT NULL, `rank` SMALLINT NOT NULL, presentation_id INT NOT NULL, neighbor_id INT NOT NULL, score DOUBLE PRECISION NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_533A5886AB627E8B (presentation_id), INDEX IDX_533A5886CA3465C1 (neighbor_id), UNIQUE INDEX uniq_neighbor (presentation_id, neighbor_id, model), PRIMARY KEY(presentation_id, model, `rank`)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE presentation_embeddings ADD CONSTRAINT FK_70306090AB627E8B FOREIGN KEY (presentation_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE presentation_neighbors ADD CONSTRAINT FK_533A5886AB627E8B FOREIGN KEY (presentation_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE presentation_neighbors ADD CONSTRAINT FK_533A5886CA3465C1 FOREIGN KEY (neighbor_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ppbase ADD ing_source_url_hash VARBINARY(32) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_pp_ing_source_url_hash ON ppbase (ing_source_url_hash)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presentation_embeddings DROP FOREIGN KEY FK_70306090AB627E8B');
        $this->addSql('ALTER TABLE presentation_neighbors DROP FOREIGN KEY FK_533A5886AB627E8B');
        $this->addSql('ALTER TABLE presentation_neighbors DROP FOREIGN KEY FK_533A5886CA3465C1');
        $this->addSql('DROP TABLE presentation_embeddings');
        $this->addSql('DROP TABLE presentation_neighbors');
        $this->addSql('DROP INDEX uniq_pp_ing_source_url_hash ON ppbase');
        $this->addSql('ALTER TABLE ppbase DROP ing_source_url_hash');
    }
}
