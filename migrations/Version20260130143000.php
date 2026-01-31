<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source created/updated timestamps to ingestion metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ppbase ADD ing_source_created_at DATETIME DEFAULT NULL, ADD ing_source_updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ppbase DROP ing_source_created_at, DROP ing_source_updated_at');
    }
}
