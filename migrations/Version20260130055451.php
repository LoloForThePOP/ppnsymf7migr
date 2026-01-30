<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130055451 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE need ADD status VARCHAR(255) NOT NULL, CHANGE title title VARCHAR(100) NOT NULL, CHANGE is_paid payment_status VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX IDX_2CF5FCD026C71C11 ON presentation_event');
        $this->addSql('ALTER TABLE presentation_event CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE presentation_event RENAME INDEX idx_2cf5fcd0a76ed395 TO IDX_DDCBF106A76ED395');
        $this->addSql('DROP INDEX IDX_ULULE_CATALOG_STATUS ON ulule_project_catalog');
        $this->addSql('ALTER TABLE ulule_project_catalog CHANGE last_seen_at last_seen_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE last_imported_at last_imported_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE ulule_created_at ulule_created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ulule_project_catalog RENAME INDEX uniq_ulule_catalog_ulule_id TO UNIQ_3EB741525F517B21');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE need DROP status, CHANGE title title VARCHAR(255) NOT NULL, CHANGE payment_status is_paid VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE presentation_event CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IDX_2CF5FCD026C71C11 ON presentation_event (project_presentation_id)');
        $this->addSql('ALTER TABLE presentation_event RENAME INDEX idx_ddcbf106a76ed395 TO IDX_2CF5FCD0A76ED395');
        $this->addSql('ALTER TABLE ulule_project_catalog CHANGE last_seen_at last_seen_at DATETIME DEFAULT NULL, CHANGE last_imported_at last_imported_at DATETIME DEFAULT NULL, CHANGE ulule_created_at ulule_created_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_ULULE_CATALOG_STATUS ON ulule_project_catalog (import_status)');
        $this->addSql('ALTER TABLE ulule_project_catalog RENAME INDEX uniq_3eb741525f517b21 TO UNIQ_ULULE_CATALOG_ULULE_ID');
    }
}
