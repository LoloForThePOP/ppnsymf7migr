<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028201320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_like (id INT AUTO_INCREMENT NOT NULL, project_presentation_id INT DEFAULT NULL, user_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_95F288AB93530B7B (project_presentation_id), INDEX IDX_95F288ABA76ED395 (user_id), UNIQUE INDEX user_project_unique_like (user_id, project_presentation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE project_like ADD CONSTRAINT FK_95F288AB93530B7B FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_like ADD CONSTRAINT FK_95F288ABA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE follow DROP FOREIGN KEY FK_68344470A76ED395');
        $this->addSql('ALTER TABLE follow DROP FOREIGN KEY FK_6834447093530B7B');
        $this->addSql('ALTER TABLE follow ADD CONSTRAINT FK_68344470A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE follow ADD CONSTRAINT FK_6834447093530B7B FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX user_projectPresentation_unique_follow ON follow (user_id, project_presentation_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_like DROP FOREIGN KEY FK_95F288AB93530B7B');
        $this->addSql('ALTER TABLE project_like DROP FOREIGN KEY FK_95F288ABA76ED395');
        $this->addSql('DROP TABLE project_like');
        $this->addSql('ALTER TABLE follow DROP FOREIGN KEY FK_6834447093530B7B');
        $this->addSql('ALTER TABLE follow DROP FOREIGN KEY FK_68344470A76ED395');
        $this->addSql('DROP INDEX user_projectPresentation_unique_follow ON follow');
        $this->addSql('ALTER TABLE follow ADD CONSTRAINT FK_6834447093530B7B FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE follow ADD CONSTRAINT FK_68344470A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
