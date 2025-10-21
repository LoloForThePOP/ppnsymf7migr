<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251021112356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_profile ADD description LONGTEXT DEFAULT NULL, ADD website1 VARCHAR(150) DEFAULT NULL, ADD website2 VARCHAR(150) DEFAULT NULL, ADD website3 VARCHAR(150) DEFAULT NULL, ADD image VARCHAR(255) DEFAULT NULL, ADD postal_mail LONGTEXT DEFAULT NULL, ADD tel1 VARCHAR(25) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD extra JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_profile DROP description, DROP website1, DROP website2, DROP website3, DROP image, DROP postal_mail, DROP tel1, DROP updated_at, DROP extra');
    }
}
