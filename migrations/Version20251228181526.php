<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251228181526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expiration timestamps for email verification and reset password tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD email_validation_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD reset_password_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP email_validation_token_expires_at, DROP reset_password_token_expires_at');
    }
}
