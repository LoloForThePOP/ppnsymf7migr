<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251030195229 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conversation (id INT AUTO_INCREMENT NOT NULL, topic VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE conversation_message (id INT AUTO_INCREMENT NOT NULL, conversation_id INT NOT NULL, sender_id INT DEFAULT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2DEB3E759AC0396 (conversation_id), INDEX IDX_2DEB3E75F624B39D (sender_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE conversation_participant (id INT AUTO_INCREMENT NOT NULL, conversation_id INT NOT NULL, user_id INT DEFAULT NULL, is_muted TINYINT(1) NOT NULL, last_read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_398016619AC0396 (conversation_id), INDEX IDX_39801661A76ED395 (user_id), UNIQUE INDEX user_conversation_unique (user_id, conversation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE conversation_message ADD CONSTRAINT FK_2DEB3E759AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_message ADD CONSTRAINT FK_2DEB3E75F624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_398016619AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_39801661A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ppbase ADD is_creation_form_completed TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_message DROP FOREIGN KEY FK_2DEB3E759AC0396');
        $this->addSql('ALTER TABLE conversation_message DROP FOREIGN KEY FK_2DEB3E75F624B39D');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_398016619AC0396');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_39801661A76ED395');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE conversation_message');
        $this->addSql('DROP TABLE conversation_participant');
        $this->addSql('ALTER TABLE ppbase DROP is_creation_form_completed');
    }
}
