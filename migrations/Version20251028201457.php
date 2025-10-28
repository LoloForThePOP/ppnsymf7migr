<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028201457 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE follow RENAME INDEX idx_68344470a76ed395 TO idx_follow_user');
        $this->addSql('ALTER TABLE follow RENAME INDEX idx_6834447093530b7b TO idx_follow_project_presentation');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE follow RENAME INDEX idx_follow_project_presentation TO IDX_6834447093530B7B');
        $this->addSql('ALTER TABLE follow RENAME INDEX idx_follow_user TO IDX_68344470A76ED395');
    }
}
