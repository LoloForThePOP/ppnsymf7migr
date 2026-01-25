<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260122124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove volunteering status (paused feature).';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('ppbase') && $schema->getTable('ppbase')->hasColumn('volunteering_status')) {
            $this->addSql('ALTER TABLE ppbase DROP COLUMN volunteering_status');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('ppbase') && !$schema->getTable('ppbase')->hasColumn('volunteering_status')) {
            $this->addSql('ALTER TABLE ppbase ADD volunteering_status VARCHAR(20) DEFAULT NULL');
        }
    }
}
