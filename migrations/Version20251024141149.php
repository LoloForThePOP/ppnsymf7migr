<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251024141149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ppbase ADD cache_views_count INT DEFAULT 0 NOT NULL, ADD cache_is_randomized_string_id TINYINT(1) DEFAULT 1 NOT NULL, ADD cache_overall_quality_assessment SMALLINT DEFAULT 0, ADD cache_are_private_messages_activated TINYINT(1) DEFAULT 1 NOT NULL, ADD cache_cache_thumbnail_url VARCHAR(255) DEFAULT NULL, ADD cache_short_editorial_text LONGTEXT DEFAULT NULL, DROP parameters, DROP cache_data, DROP data');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ppbase ADD parameters JSON DEFAULT NULL, ADD cache_data JSON DEFAULT NULL, ADD data JSON DEFAULT NULL, DROP cache_views_count, DROP cache_is_randomized_string_id, DROP cache_overall_quality_assessment, DROP cache_are_private_messages_activated, DROP cache_cache_thumbnail_url, DROP cache_short_editorial_text');
    }
}
