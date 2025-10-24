<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251024223632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE settings (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, keywords LONGTEXT DEFAULT NULL, min_proposals INT DEFAULT NULL, max_proposals INT DEFAULT NULL, excluded_countries LONGTEXT DEFAULT NULL, email_notifications TINYINT(1) NOT NULL, telegram_notifications TINYINT(1) NOT NULL, email_address VARCHAR(255) DEFAULT NULL, telegram_chat_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_E545A0C5A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE settings ADD CONSTRAINT FK_E545A0C5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE test_entity
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alert ADD CONSTRAINT FK_17FD46C1BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE test_entity (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE settings DROP FOREIGN KEY FK_E545A0C5A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE settings
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE alert DROP FOREIGN KEY FK_17FD46C1BE04EA9
        SQL);
    }
}
