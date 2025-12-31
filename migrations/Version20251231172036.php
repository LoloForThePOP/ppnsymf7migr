<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251231172036 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, creator_id INT DEFAULT NULL, content LONGTEXT DEFAULT NULL, title VARCHAR(255) NOT NULL, type VARCHAR(255) DEFAULT NULL, is_validated TINYINT(1) DEFAULT 1 NOT NULL, slug VARCHAR(255) DEFAULT NULL, short_description LONGTEXT DEFAULT NULL, views_count INT NOT NULL, thumbnail VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_23A0E6661220EA6 (creator_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, unique_name VARCHAR(50) NOT NULL, label VARCHAR(100) DEFAULT NULL, position SMALLINT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_64C19C198AB450A (unique_name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE category_ppbase (category_id INT NOT NULL, ppbase_id INT NOT NULL, INDEX IDX_AD2AA7AB12469DE2 (category_id), INDEX IDX_AD2AA7AB4EF146D4 (ppbase_id), PRIMARY KEY(category_id, ppbase_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, replied_user_id INT DEFAULT NULL, article_id INT DEFAULT NULL, project_presentation_id INT DEFAULT NULL, creator_id INT DEFAULT NULL, news_id INT DEFAULT NULL, content LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9474526C727ACA70 (parent_id), INDEX IDX_9474526C4D078E33 (replied_user_id), INDEX IDX_9474526C7294869C (article_id), INDEX IDX_9474526C93530B7B (project_presentation_id), INDEX IDX_9474526C61220EA6 (creator_id), INDEX IDX_9474526CB5A459A0 (news_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE conversation (id INT AUTO_INCREMENT NOT NULL, topic VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE conversation_message (id INT AUTO_INCREMENT NOT NULL, conversation_id INT NOT NULL, sender_id INT DEFAULT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2DEB3E759AC0396 (conversation_id), INDEX IDX_2DEB3E75F624B39D (sender_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE conversation_participant (id INT AUTO_INCREMENT NOT NULL, conversation_id INT NOT NULL, user_id INT DEFAULT NULL, is_muted TINYINT(1) NOT NULL, last_read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_398016619AC0396 (conversation_id), INDEX IDX_39801661A76ED395 (user_id), UNIQUE INDEX user_conversation_unique (user_id, conversation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, project_presentation_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, position SMALLINT DEFAULT NULL, mime_type VARCHAR(255) DEFAULT NULL, file_name VARCHAR(255) NOT NULL, size INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D8698A7693530B7B (project_presentation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE follow (id INT AUTO_INCREMENT NOT NULL, project_presentation_id INT DEFAULT NULL, user_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_follow_user (user_id), INDEX idx_follow_project_presentation (project_presentation_id), UNIQUE INDEX user_projectPresentation_unique_follow (user_id, project_presentation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE need (id INT AUTO_INCREMENT NOT NULL, project_id INT DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, is_paid VARCHAR(255) DEFAULT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, position SMALLINT DEFAULT NULL, INDEX IDX_E6F46C44166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE news (id INT AUTO_INCREMENT NOT NULL, project_id INT DEFAULT NULL, creator_id INT DEFAULT NULL, text_content LONGTEXT DEFAULT NULL, image1 VARCHAR(255) DEFAULT NULL, image2 VARCHAR(255) DEFAULT NULL, image3 VARCHAR(255) DEFAULT NULL, caption_image1 VARCHAR(1000) DEFAULT NULL, caption_image2 VARCHAR(1000) DEFAULT NULL, caption_image3 VARCHAR(1000) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1DD39950166D1F9C (project_id), UNIQUE INDEX UNIQ_1DD3995061220EA6 (creator_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE place (id INT AUTO_INCREMENT NOT NULL, project_id INT DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, type VARCHAR(40) NOT NULL, country VARCHAR(255) DEFAULT NULL, administrative_area_level1 VARCHAR(255) DEFAULT NULL, administrative_area_level2 VARCHAR(255) DEFAULT NULL, locality VARCHAR(255) DEFAULT NULL, sublocality_level1 VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(20) DEFAULT NULL, position SMALLINT DEFAULT NULL, geoloc_latitude DOUBLE PRECISION DEFAULT NULL, geoloc_longitude DOUBLE PRECISION DEFAULT NULL, INDEX IDX_741D53CD166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ppbase (id INT AUTO_INCREMENT NOT NULL, creator_id INT NOT NULL, string_id VARCHAR(190) NOT NULL, logo VARCHAR(255) DEFAULT NULL, custom_thumbnail VARCHAR(255) DEFAULT NULL, goal VARCHAR(400) NOT NULL, title VARCHAR(255) DEFAULT NULL, keywords VARCHAR(255) DEFAULT NULL, origin_language VARCHAR(8) DEFAULT NULL, text_description LONGTEXT DEFAULT NULL, is_admin_validated TINYINT(1) NOT NULL, is_published TINYINT(1) NOT NULL, is_deleted TINYINT(1) DEFAULT NULL, is_creation_form_completed TINYINT(1) DEFAULT NULL, score SMALLINT DEFAULT 0, statuses JSON DEFAULT NULL, status_remarks VARCHAR(3000) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', other_components_other_components JSON DEFAULT NULL, extra_views_count INT DEFAULT 0 NOT NULL, extra_is_randomized_string_id TINYINT(1) DEFAULT 1 NOT NULL, extra_are_private_messages_activated TINYINT(1) DEFAULT 1 NOT NULL, extra_cache_thumbnail_url VARCHAR(255) DEFAULT NULL, extra_short_editorial_text LONGTEXT DEFAULT NULL, ing_source_url VARCHAR(2048) DEFAULT NULL, ing_source_url_hash VARBINARY(32) DEFAULT NULL, ing_source_organization_name VARCHAR(255) DEFAULT NULL, ing_source_organization_website VARCHAR(2048) DEFAULT NULL, ing_source_published_at DATE DEFAULT NULL, ing_ingested_at DATETIME DEFAULT NULL, ing_ingestion_status VARCHAR(20) DEFAULT NULL, ing_ingestion_status_comment VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_A2C26DD04AC2F1F0 (string_id), INDEX IDX_A2C26DD061220EA6 (creator_id), UNIQUE INDEX uniq_pp_ing_source_url_hash (ing_source_url_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE presentation_embeddings (model VARCHAR(64) NOT NULL, presentation_id INT NOT NULL, dims SMALLINT NOT NULL, normalized TINYINT(1) DEFAULT 1 NOT NULL, vector LONGBLOB NOT NULL, content_hash VARBINARY(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_70306090AB627E8B (presentation_id), PRIMARY KEY(presentation_id, model)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE presentation_neighbors (model VARCHAR(64) NOT NULL, `rank` SMALLINT NOT NULL, presentation_id INT NOT NULL, neighbor_id INT NOT NULL, score DOUBLE PRECISION NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_533A5886AB627E8B (presentation_id), INDEX IDX_533A5886CA3465C1 (neighbor_id), PRIMARY KEY(presentation_id, model, `rank`)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE profile (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, website1 VARCHAR(150) DEFAULT NULL, website2 VARCHAR(150) DEFAULT NULL, website3 VARCHAR(150) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, postal_mail LONGTEXT DEFAULT NULL, tel1 VARCHAR(25) DEFAULT NULL, description LONGTEXT DEFAULT NULL, extra JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_8157AA0FA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE project_like (id INT AUTO_INCREMENT NOT NULL, project_presentation_id INT DEFAULT NULL, user_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_like_user (user_id), INDEX idx_like_project_presentation (project_presentation_id), UNIQUE INDEX user_project_unique_like (user_id, project_presentation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE slide (id INT AUTO_INCREMENT NOT NULL, project_presentation_id INT DEFAULT NULL, type VARCHAR(30) NOT NULL, mime_type VARCHAR(100) DEFAULT NULL, image_path VARCHAR(255) DEFAULT NULL, youtube_url VARCHAR(255) DEFAULT NULL, caption VARCHAR(400) DEFAULT NULL, licence VARCHAR(255) DEFAULT NULL, position SMALLINT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_72EFEE6293530B7B (project_presentation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, is_verified TINYINT(1) NOT NULL, is_active TINYINT(1) NOT NULL, password_reset_token VARCHAR(255) DEFAULT NULL, google_id VARCHAR(50) DEFAULT NULL, facebook_id VARCHAR(50) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', email_validation_token VARCHAR(100) DEFAULT NULL, email_validation_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', username VARCHAR(40) NOT NULL, username_slug VARCHAR(120) NOT NULL, reset_password_token VARCHAR(100) DEFAULT NULL, reset_password_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6661220EA6 FOREIGN KEY (creator_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE category_ppbase ADD CONSTRAINT FK_AD2AA7AB12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category_ppbase ADD CONSTRAINT FK_AD2AA7AB4EF146D4 FOREIGN KEY (ppbase_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C727ACA70 FOREIGN KEY (parent_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C4D078E33 FOREIGN KEY (replied_user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C7294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C93530B7B FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C61220EA6 FOREIGN KEY (creator_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CB5A459A0 FOREIGN KEY (news_id) REFERENCES news (id)');
        $this->addSql('ALTER TABLE conversation_message ADD CONSTRAINT FK_2DEB3E759AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_message ADD CONSTRAINT FK_2DEB3E75F624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_398016619AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_39801661A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7693530B7B FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id)');
        $this->addSql('ALTER TABLE follow ADD CONSTRAINT FK_6834447093530B7B FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE follow ADD CONSTRAINT FK_68344470A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE need ADD CONSTRAINT FK_E6F46C44166D1F9C FOREIGN KEY (project_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE news ADD CONSTRAINT FK_1DD39950166D1F9C FOREIGN KEY (project_id) REFERENCES ppbase (id)');
        $this->addSql('ALTER TABLE news ADD CONSTRAINT FK_1DD3995061220EA6 FOREIGN KEY (creator_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE place ADD CONSTRAINT FK_741D53CD166D1F9C FOREIGN KEY (project_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ppbase ADD CONSTRAINT FK_A2C26DD061220EA6 FOREIGN KEY (creator_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE presentation_embeddings ADD CONSTRAINT FK_70306090AB627E8B FOREIGN KEY (presentation_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE presentation_neighbors ADD CONSTRAINT FK_533A5886AB627E8B FOREIGN KEY (presentation_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE presentation_neighbors ADD CONSTRAINT FK_533A5886CA3465C1 FOREIGN KEY (neighbor_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT FK_8157AA0FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_like ADD CONSTRAINT FK_95F288AB93530B7B FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_like ADD CONSTRAINT FK_95F288ABA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE slide ADD CONSTRAINT FK_72EFEE6293530B7B FOREIGN KEY (project_presentation_id) REFERENCES ppbase (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6661220EA6');
        $this->addSql('ALTER TABLE category_ppbase DROP FOREIGN KEY FK_AD2AA7AB12469DE2');
        $this->addSql('ALTER TABLE category_ppbase DROP FOREIGN KEY FK_AD2AA7AB4EF146D4');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C727ACA70');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C4D078E33');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C7294869C');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C93530B7B');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C61220EA6');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CB5A459A0');
        $this->addSql('ALTER TABLE conversation_message DROP FOREIGN KEY FK_2DEB3E759AC0396');
        $this->addSql('ALTER TABLE conversation_message DROP FOREIGN KEY FK_2DEB3E75F624B39D');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_398016619AC0396');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_39801661A76ED395');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7693530B7B');
        $this->addSql('ALTER TABLE follow DROP FOREIGN KEY FK_6834447093530B7B');
        $this->addSql('ALTER TABLE follow DROP FOREIGN KEY FK_68344470A76ED395');
        $this->addSql('ALTER TABLE need DROP FOREIGN KEY FK_E6F46C44166D1F9C');
        $this->addSql('ALTER TABLE news DROP FOREIGN KEY FK_1DD39950166D1F9C');
        $this->addSql('ALTER TABLE news DROP FOREIGN KEY FK_1DD3995061220EA6');
        $this->addSql('ALTER TABLE place DROP FOREIGN KEY FK_741D53CD166D1F9C');
        $this->addSql('ALTER TABLE ppbase DROP FOREIGN KEY FK_A2C26DD061220EA6');
        $this->addSql('ALTER TABLE presentation_embeddings DROP FOREIGN KEY FK_70306090AB627E8B');
        $this->addSql('ALTER TABLE presentation_neighbors DROP FOREIGN KEY FK_533A5886AB627E8B');
        $this->addSql('ALTER TABLE presentation_neighbors DROP FOREIGN KEY FK_533A5886CA3465C1');
        $this->addSql('ALTER TABLE profile DROP FOREIGN KEY FK_8157AA0FA76ED395');
        $this->addSql('ALTER TABLE project_like DROP FOREIGN KEY FK_95F288AB93530B7B');
        $this->addSql('ALTER TABLE project_like DROP FOREIGN KEY FK_95F288ABA76ED395');
        $this->addSql('ALTER TABLE slide DROP FOREIGN KEY FK_72EFEE6293530B7B');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE category_ppbase');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE conversation_message');
        $this->addSql('DROP TABLE conversation_participant');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE follow');
        $this->addSql('DROP TABLE need');
        $this->addSql('DROP TABLE news');
        $this->addSql('DROP TABLE place');
        $this->addSql('DROP TABLE ppbase');
        $this->addSql('DROP TABLE presentation_embeddings');
        $this->addSql('DROP TABLE presentation_neighbors');
        $this->addSql('DROP TABLE profile');
        $this->addSql('DROP TABLE project_like');
        $this->addSql('DROP TABLE slide');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
