<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130145000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy ing_source_published_at from ppbase.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ppbase DROP ing_source_published_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ppbase ADD ing_source_published_at DATE DEFAULT NULL');
    }
}
