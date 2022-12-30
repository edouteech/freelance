<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210609090641 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE formation_participant_progress (id INT AUTO_INCREMENT NOT NULL, formation_participant_id INT NOT NULL, chapter INT NOT NULL, subchapter INT NOT NULL, time_elapsed INT NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_BB152B324A484094 (formation_participant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE formation_participant_progress ADD CONSTRAINT FK_BB152B324A484094 FOREIGN KEY (formation_participant_id) REFERENCES formation_participant (id)');
        $this->addSql('ALTER TABLE formation DROP content, DROP drive_file_id');
        $this->addSql('ALTER TABLE term CHANGE role role ENUM(\'ROLE_USER\', \'ROLE_CLIENT\', \'ROLE_COMPANY\', \'ROLE_CONTACT\', \'ROLE_COMMERCIAL_AGENT\') NOT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\RoleEnum)\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE formation_participant_progress');
        $this->addSql('ALTER TABLE formation ADD content LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, ADD drive_file_id VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE term CHANGE role role VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
    }
}
