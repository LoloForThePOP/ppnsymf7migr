<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251101141905 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE need DROP FOREIGN KEY FK_E6F46C4493530B7B');
        $this->addSql('DROP INDEX IDX_E6F46C4493530B7B ON need');
        $this->addSql('ALTER TABLE need CHANGE project_presentation_id project_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE need ADD CONSTRAINT FK_E6F46C44166D1F9C FOREIGN KEY (project_id) REFERENCES ppbase (id)');
        $this->addSql('CREATE INDEX IDX_E6F46C44166D1F9C ON need (project_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE need DROP FOREIGN KEY FK_E6F46C44166D1F9C');
        $this->addSql('DROP INDEX IDX_E6F46C44166D1F9C ON need');
        $this->addSql('ALTER TABLE need CHANGE project_id project_presentation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE need ADD CONSTRAINT FK_E6F46C4493530B7B FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_E6F46C4493530B7B ON need (project_presentation_id)');
    }
}
