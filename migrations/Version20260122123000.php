<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260122123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add volunteering status to project presentations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ppbase ADD volunteering_status VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ppbase DROP volunteering_status');
    }
}
