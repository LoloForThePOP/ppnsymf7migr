<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260116140500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Ulule created date to catalog';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ulule_project_catalog ADD ulule_created_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ulule_project_catalog DROP ulule_created_at');
    }
}
