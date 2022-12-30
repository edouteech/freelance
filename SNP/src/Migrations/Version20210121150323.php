<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210121150323 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE contact_metadata CHANGE state state ENUM(\'favorite\',\'read\',\'pinned\'), CHANGE type type ENUM(\'resource\',\'appendix\',\'tour\')');
        $this->addSql('ALTER TABLE document_asset CHANGE type type ENUM(\'pdf\',\'editable-pdf\')');
        $this->addSql('ALTER TABLE news CHANGE link_type link_type ENUM(\'page\',\'external\',\'document\',\'article\')');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398A76ED395');
        $this->addSql('DROP INDEX IDX_F5299398A76ED395 ON `order`');
        $this->addSql('ALTER TABLE `order` ADD contact_id INT DEFAULT NULL');
	    $this->addSql('UPDATE `order` o INNER JOIN user u on o.user_id = u.id SET o.company_id=u.company_id, o.contact_id=u.contact_id');
	    $this->addSql('ALTER TABLE `order` DROP user_id, CHANGE type type ENUM(\'formation\',\'membership_snpi\',\'membership_vhs\',\'membership_asseris\',\'membership_caci\',\'signature\')');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('CREATE INDEX IDX_F5299398E7A1254A ON `order` (contact_id)');
        $this->addSql('ALTER TABLE payment CHANGE status status ENUM(\'captured\',\'authorized\',\'payedout\',\'refunded\',\'unknown\',\'failed\',\'suspended\',\'expired\',\'pending\',\'canceled\',\'new\')');
        $this->addSql('ALTER TABLE role CHANGE name name ENUM(\'ROLE_CLIENT\',\'ROLE_ADMIN\',\'ROLE_SUPER_ADMIN\',\'ROLE_FORMATION_READ\',\'ROLE_FORMATION_CREATE\',\'ROLE_FORMATION_DELETE\',\'ROLE_SIGNATURE_READ\',\'ROLE_SIGNATURE_CREATE\',\'ROLE_SIGNATURE_DELETE\',\'ROLE_SIGNATUREPACK_CREATE\',\'ROLE_DOCUMENT_READ\',\'ROLE_NEWS_READ\')');
        $this->addSql('ALTER TABLE term CHANGE role role ENUM(\'ROLE_USER\',\'ROLE_CLIENT\',\'ROLE_COMPANY\',\'ROLE_CONTACT\')');
        $this->addSql('ALTER TABLE user CHANGE type type ENUM(\'contact\',\'company\',\'collaborator\',\'commercial_agent\',\'legal_representative\')');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE contact_metadata CHANGE state state VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE document_asset CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE news CHANGE link_type link_type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398E7A1254A');
        $this->addSql('DROP INDEX IDX_F5299398E7A1254A ON `order`');
        $this->addSql('ALTER TABLE `order` ADD user_id INT NOT NULL, DROP contact_id, CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_F5299398A76ED395 ON `order` (user_id)');
        $this->addSql('ALTER TABLE payment CHANGE status status VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE role CHANGE name name VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE term CHANGE role role VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE user CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
    }
}
