<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210122144511 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE document_asset CHANGE type type ENUM(\'pdf\',\'editable-pdf\')');
        $this->addSql('ALTER TABLE formation_participant ADD note LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE news CHANGE link_type link_type ENUM(\'page\',\'external\',\'document\',\'article\')');
        $this->addSql('ALTER TABLE `order` CHANGE type type ENUM(\'formation\',\'membership_snpi\',\'membership_vhs\',\'membership_asseris\',\'membership_caci\',\'signature\')');
        $this->addSql('ALTER TABLE payment CHANGE status status ENUM(\'captured\',\'authorized\',\'payedout\',\'refunded\',\'unknown\',\'failed\',\'suspended\',\'expired\',\'pending\',\'canceled\',\'new\')');
        $this->addSql('ALTER TABLE role CHANGE name name ENUM(\'ROLE_CLIENT\',\'ROLE_ADMIN\',\'ROLE_SUPER_ADMIN\')');
        $this->addSql('ALTER TABLE term CHANGE role role ENUM(\'ROLE_USER\',\'ROLE_CLIENT\',\'ROLE_COMPANY\')');
        $this->addSql('ALTER TABLE user CHANGE type type ENUM(\'contact\',\'company\')');
        $this->addSql('ALTER TABLE user_metadata CHANGE state state ENUM(\'favorite\',\'read\',\'pinned\'), CHANGE type type ENUM(\'resource\',\'appendix\',\'tour\')');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE document_asset CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE formation_participant DROP note');
        $this->addSql('ALTER TABLE news CHANGE link_type link_type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE `order` CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE payment CHANGE status status VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE role CHANGE name name VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE term CHANGE role role VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE user CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE user_metadata CHANGE state state VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
    }
}
