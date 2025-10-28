<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028201557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_like RENAME INDEX idx_95f288aba76ed395 TO idx_like_user');
        $this->addSql('ALTER TABLE project_like RENAME INDEX idx_95f288ab93530b7b TO idx_like_project_presentation');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_like RENAME INDEX idx_like_project_presentation TO IDX_95F288AB93530B7B');
        $this->addSql('ALTER TABLE project_like RENAME INDEX idx_like_user TO IDX_95F288ABA76ED395');
    }
}
