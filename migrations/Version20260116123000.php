<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260116123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ulule_project_catalog table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ulule_project_catalog (id INT AUTO_INCREMENT NOT NULL, ulule_id INT NOT NULL, name VARCHAR(255) DEFAULT NULL, subtitle VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, source_url VARCHAR(2048) DEFAULT NULL, lang VARCHAR(10) DEFAULT NULL, country VARCHAR(2) DEFAULT NULL, type VARCHAR(20) DEFAULT NULL, goal_raised TINYINT(1) DEFAULT NULL, is_online TINYINT(1) DEFAULT NULL, is_cancelled TINYINT(1) DEFAULT NULL, description_length INT DEFAULT NULL, last_seen_at DATETIME DEFAULT NULL, last_imported_at DATETIME DEFAULT NULL, import_status VARCHAR(20) DEFAULT NULL, import_status_comment VARCHAR(255) DEFAULT NULL, imported_string_id VARCHAR(255) DEFAULT NULL, last_error VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_ULULE_CATALOG_ULULE_ID (ulule_id), INDEX IDX_ULULE_CATALOG_STATUS (import_status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ulule_project_catalog');
    }
}
