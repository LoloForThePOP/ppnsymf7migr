<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216195000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add homepage performance indexes on ppbase publication ordering and place geolocation lookup.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('ppbase')) {
            $ppbase = $schema->getTable('ppbase');
            if (!$ppbase->hasIndex('idx_ppbase_pub_del_created')) {
                $this->addSql('CREATE INDEX idx_ppbase_pub_del_created ON ppbase (is_published, is_deleted, created_at, id)');
            }
        }

        if ($schema->hasTable('place')) {
            $place = $schema->getTable('place');
            if (!$place->hasIndex('idx_place_geo_project')) {
                $this->addSql('CREATE INDEX idx_place_geo_project ON place (geoloc_latitude, geoloc_longitude, project_id)');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('place')) {
            $place = $schema->getTable('place');
            if ($place->hasIndex('idx_place_geo_project')) {
                $this->addSql('DROP INDEX idx_place_geo_project ON place');
            }
        }

        if ($schema->hasTable('ppbase')) {
            $ppbase = $schema->getTable('ppbase');
            if ($ppbase->hasIndex('idx_ppbase_pub_del_created')) {
                $this->addSql('DROP INDEX idx_ppbase_pub_del_created ON ppbase');
            }
        }
    }
}
