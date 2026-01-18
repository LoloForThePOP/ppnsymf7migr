<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260118132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add presentation events for analytics.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE presentation_event (id INT AUTO_INCREMENT NOT NULL, project_presentation_id INT NOT NULL, user_id INT DEFAULT NULL, type VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, visitor_hash VARCHAR(64) DEFAULT NULL, meta JSON DEFAULT NULL, INDEX idx_presentation_event_type (type), INDEX idx_presentation_event_created_at (created_at), INDEX idx_presentation_event_pp (project_presentation_id), INDEX idx_presentation_event_visitor (visitor_hash), INDEX IDX_2CF5FCD026C71C11 (project_presentation_id), INDEX IDX_2CF5FCD0A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE presentation_event ADD CONSTRAINT FK_2CF5FCD026C71C11 FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE presentation_event ADD CONSTRAINT FK_2CF5FCD0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE presentation_event');
    }
}
