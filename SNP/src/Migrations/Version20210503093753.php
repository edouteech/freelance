<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210503093753 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE user_access_log (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, type ENUM(\'login\', \'logout\', \'refresh\') NOT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\UserAccessLogTypeEnum)\', occurred_at DATETIME NOT NULL, ip_hash VARCHAR(255) NOT NULL, INDEX IDX_4818F3FCA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_access_log ADD CONSTRAINT FK_4818F3FCA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE formation CHANGE format format ENUM(\'\', \'instructor-led\', \'in-house\', \'e-learning\', \'webinar\', \'live\') DEFAULT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\FormationTypeEnum)\'');
        $this->addSql('ALTER TABLE formation_course CHANGE format format ENUM(\'\', \'instructor-led\', \'in-house\', \'e-learning\', \'webinar\', \'live\') DEFAULT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\FormationTypeEnum)\'');
        $this->addSql('ALTER TABLE survey_question CHANGE format format ENUM(\'\', \'instructor-led\', \'in-house\', \'e-learning\', \'webinar\', \'live\') DEFAULT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\FormationTypeEnum)\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE user_access_log');
        $this->addSql('ALTER TABLE formation CHANGE format format VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE formation_course CHANGE format format VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE survey_question CHANGE format format VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
    }
}
