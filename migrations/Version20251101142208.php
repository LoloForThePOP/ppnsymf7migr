<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251101142208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE need DROP FOREIGN KEY FK_E6F46C44AB627E8B');
        $this->addSql('ALTER TABLE need DROP FOREIGN KEY FK_E6F46C44166D1F9C');
        $this->addSql('DROP INDEX IDX_E6F46C44AB627E8B ON need');
        $this->addSql('ALTER TABLE need DROP presentation_id');
        $this->addSql('ALTER TABLE need ADD CONSTRAINT FK_E6F46C44166D1F9C FOREIGN KEY (project_id) REFERENCES ppbase (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE need DROP FOREIGN KEY FK_E6F46C44166D1F9C');
        $this->addSql('ALTER TABLE need ADD presentation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE need ADD CONSTRAINT FK_E6F46C44AB627E8B FOREIGN KEY (presentation_id) REFERENCES ppbase (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE need ADD CONSTRAINT FK_E6F46C44166D1F9C FOREIGN KEY (project_id) REFERENCES ppbase (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_E6F46C44AB627E8B ON need (presentation_id)');
    }
}
