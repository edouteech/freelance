<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210903075839 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE news CHANGE link_type link_type ENUM(\'\', \'page\', \'external\', \'document\', \'article\') DEFAULT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\NewsLinkTypeEnum)\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE news CHANGE link_type link_type ENUM(\'\', \'page\', \'external\', \'document\', \'article\') CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:App\\\\DBAL\\\\NewsLinkTypeEnum)\'');
    }
}
