<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260118123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add funding metadata to ppbase.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ppbase ADD funding_end_at DATETIME DEFAULT NULL, ADD funding_status VARCHAR(20) DEFAULT NULL, ADD funding_platform VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ppbase DROP funding_end_at, DROP funding_status, DROP funding_platform');
    }
}
