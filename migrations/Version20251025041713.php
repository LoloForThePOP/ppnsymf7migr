<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251025041713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE need (id INT AUTO_INCREMENT NOT NULL, presentation_id INT DEFAULT NULL, project_presentation_id INT DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, is_paid VARCHAR(255) DEFAULT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, position SMALLINT DEFAULT NULL, INDEX IDX_E6F46C44AB627E8B (presentation_id), INDEX IDX_E6F46C4493530B7B (project_presentation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE news (id INT AUTO_INCREMENT NOT NULL, project_id INT DEFAULT NULL, creator_id INT DEFAULT NULL, text_content LONGTEXT DEFAULT NULL, image1 VARCHAR(255) DEFAULT NULL, image2 VARCHAR(255) DEFAULT NULL, image3 VARCHAR(255) DEFAULT NULL, caption_image1 VARCHAR(1000) DEFAULT NULL, caption_image2 VARCHAR(1000) DEFAULT NULL, caption_image3 VARCHAR(1000) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1DD39950166D1F9C (project_id), UNIQUE INDEX UNIQ_1DD3995061220EA6 (creator_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE need ADD CONSTRAINT FK_E6F46C44AB627E8B FOREIGN KEY (presentation_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE need ADD CONSTRAINT FK_E6F46C4493530B7B FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id)');
        $this->addSql('ALTER TABLE news ADD CONSTRAINT FK_1DD39950166D1F9C FOREIGN KEY (project_id) REFERENCES ppbase (id)');
        $this->addSql('ALTER TABLE news ADD CONSTRAINT FK_1DD3995061220EA6 FOREIGN KEY (creator_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395');
        $this->addSql('DROP INDEX IDX_9474526CA76ED395 ON comment');
        $this->addSql('ALTER TABLE comment ADD news_id INT DEFAULT NULL, CHANGE user_id creator_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C61220EA6 FOREIGN KEY (creator_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CB5A459A0 FOREIGN KEY (news_id) REFERENCES news (id)');
        $this->addSql('CREATE INDEX IDX_9474526C61220EA6 ON comment (creator_id)');
        $this->addSql('CREATE INDEX IDX_9474526CB5A459A0 ON comment (news_id)');
        $this->addSql('ALTER TABLE ppbase ADD creator_id INT NOT NULL');
        $this->addSql('ALTER TABLE ppbase ADD CONSTRAINT FK_A2C26DD061220EA6 FOREIGN KEY (creator_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_A2C26DD061220EA6 ON ppbase (creator_id)');
        $this->addSql('ALTER TABLE slide ADD project_presentation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE slide ADD CONSTRAINT FK_72EFEE6293530B7B FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id)');
        $this->addSql('CREATE INDEX IDX_72EFEE6293530B7B ON slide (project_presentation_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CB5A459A0');
        $this->addSql('ALTER TABLE need DROP FOREIGN KEY FK_E6F46C44AB627E8B');
        $this->addSql('ALTER TABLE need DROP FOREIGN KEY FK_E6F46C4493530B7B');
        $this->addSql('ALTER TABLE news DROP FOREIGN KEY FK_1DD39950166D1F9C');
        $this->addSql('ALTER TABLE news DROP FOREIGN KEY FK_1DD3995061220EA6');
        $this->addSql('DROP TABLE need');
        $this->addSql('DROP TABLE news');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C61220EA6');
        $this->addSql('DROP INDEX IDX_9474526C61220EA6 ON comment');
        $this->addSql('DROP INDEX IDX_9474526CB5A459A0 ON comment');
        $this->addSql('ALTER TABLE comment ADD user_id INT DEFAULT NULL, DROP creator_id, DROP news_id');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_9474526CA76ED395 ON comment (user_id)');
        $this->addSql('ALTER TABLE slide DROP FOREIGN KEY FK_72EFEE6293530B7B');
        $this->addSql('DROP INDEX IDX_72EFEE6293530B7B ON slide');
        $this->addSql('ALTER TABLE slide DROP project_presentation_id');
        $this->addSql('ALTER TABLE ppbase DROP FOREIGN KEY FK_A2C26DD061220EA6');
        $this->addSql('DROP INDEX IDX_A2C26DD061220EA6 ON ppbase');
        $this->addSql('ALTER TABLE ppbase DROP creator_id');
    }
}
