<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260203164000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add project bookmarks (user <-> project_presentation).';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('bookmark')) {
            return;
        }

        $this->addSql('CREATE TABLE bookmark (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, project_presentation_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_bookmark_user (user_id), INDEX idx_bookmark_project_presentation (project_presentation_id), UNIQUE INDEX user_project_unique_bookmark (user_id, project_presentation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_BOOKMARK_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_BOOKMARK_PP FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('bookmark')) {
            return;
        }

        $this->addSql('ALTER TABLE bookmark DROP FOREIGN KEY FK_BOOKMARK_USER');
        $this->addSql('ALTER TABLE bookmark DROP FOREIGN KEY FK_BOOKMARK_PP');
        $this->addSql('DROP TABLE bookmark');
    }
}
