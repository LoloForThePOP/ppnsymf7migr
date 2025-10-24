<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251024174436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, project_presentation_id INT DEFAULT NULL, user_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, replied_user_id INT DEFAULT NULL, content LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9474526C93530B7B (project_presentation_id), INDEX IDX_9474526CA76ED395 (user_id), INDEX IDX_9474526C727ACA70 (parent_id), INDEX IDX_9474526C4D078E33 (replied_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C93530B7B FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C727ACA70 FOREIGN KEY (parent_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C4D078E33 FOREIGN KEY (replied_user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE category ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C93530B7B');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C727ACA70');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C4D078E33');
        $this->addSql('DROP TABLE comment');
        $this->addSql('ALTER TABLE category DROP created_at');
    }
}
