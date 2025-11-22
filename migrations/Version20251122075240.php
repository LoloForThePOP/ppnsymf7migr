<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122075240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ppbase ADD origin_language VARCHAR(8) DEFAULT NULL, ADD ing_source_url VARCHAR(2048) DEFAULT NULL, ADD ing_source_organization_name VARCHAR(255) DEFAULT NULL, ADD ing_source_organization_website VARCHAR(2048) DEFAULT NULL, ADD ing_source_published_at DATE DEFAULT NULL, ADD ing_ingested_at DATETIME DEFAULT NULL, ADD ing_ingestion_status VARCHAR(20) DEFAULT NULL, ADD ing_ingestion_status_comment VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ppbase DROP origin_language, DROP ing_source_url, DROP ing_source_organization_name, DROP ing_source_organization_website, DROP ing_source_published_at, DROP ing_ingested_at, DROP ing_ingestion_status, DROP ing_ingestion_status_comment');
    }
}
