<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122095734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE place DROP FOREIGN KEY FK_741D53CD166D1F9C');
        $this->addSql('ALTER TABLE place ADD CONSTRAINT FK_741D53CD166D1F9C FOREIGN KEY (project_id) REFERENCES ppbase (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE place DROP FOREIGN KEY FK_741D53CD166D1F9C');
        $this->addSql('ALTER TABLE place ADD CONSTRAINT FK_741D53CD166D1F9C FOREIGN KEY (project_id) REFERENCES ppbase (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
