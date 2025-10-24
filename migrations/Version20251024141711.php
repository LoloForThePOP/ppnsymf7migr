<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251024141711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ppbase ADD extra_is_randomized_string_id TINYINT(1) DEFAULT 1 NOT NULL, ADD extra_are_private_messages_activated TINYINT(1) DEFAULT 1 NOT NULL, DROP cache_is_randomized_string_id, DROP cache_are_private_messages_activated, CHANGE cache_views_count extra_views_count INT DEFAULT 0 NOT NULL, CHANGE cache_overall_quality_assessment extra_overall_quality_assessment SMALLINT DEFAULT 0, CHANGE cache_cache_thumbnail_url extra_cache_thumbnail_url VARCHAR(255) DEFAULT NULL, CHANGE cache_short_editorial_text extra_short_editorial_text LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ppbase ADD cache_is_randomized_string_id TINYINT(1) DEFAULT 1 NOT NULL, ADD cache_are_private_messages_activated TINYINT(1) DEFAULT 1 NOT NULL, DROP extra_is_randomized_string_id, DROP extra_are_private_messages_activated, CHANGE extra_views_count cache_views_count INT DEFAULT 0 NOT NULL, CHANGE extra_overall_quality_assessment cache_overall_quality_assessment SMALLINT DEFAULT 0, CHANGE extra_cache_thumbnail_url cache_cache_thumbnail_url VARCHAR(255) DEFAULT NULL, CHANGE extra_short_editorial_text cache_short_editorial_text LONGTEXT DEFAULT NULL');
    }
}
