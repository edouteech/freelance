<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210121130404 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE contact_metadata (id INT AUTO_INCREMENT NOT NULL, contact_id INT NOT NULL, state ENUM(\'favorite\',\'read\',\'pinned\'), date DATETIME NOT NULL, entity_id VARCHAR(20) NOT NULL, type ENUM(\'resource\',\'appendix\',\'tour\'), INDEX IDX_D531A883E7A1254A (contact_id), UNIQUE INDEX resource_idx (state, contact_id, entity_id, type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE contact_metadata ADD CONSTRAINT FK_D531A883E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('INSERT INTO contact_metadata (state, date, entity_id, type, contact_id) (SELECT um.state, um.date, um.entity_id, um.type, u.contact_id FROM user_metadata um LEFT JOIN user u on u.id = um.user_id WHERE u.contact_id IS NOT NULL)');
        $this->addSql('DROP TABLE user_metadata');
        $this->addSql('ALTER TABLE document_asset CHANGE type type ENUM(\'pdf\',\'editable-pdf\')');
        $this->addSql('ALTER TABLE external_formation DROP FOREIGN KEY FK_641B8EABA76ED395');
        $this->addSql('DROP INDEX IDX_641B8EABA76ED395 ON external_formation');
        $this->addSql('ALTER TABLE external_formation DROP user_id');
        $this->addSql('ALTER TABLE news CHANGE link_type link_type ENUM(\'page\',\'external\',\'document\',\'article\')');
        $this->addSql('ALTER TABLE `order` CHANGE type type ENUM(\'formation\',\'membership_snpi\',\'membership_vhs\',\'membership_asseris\',\'membership_caci\',\'signature\')');
        $this->addSql('ALTER TABLE payment CHANGE status status ENUM(\'captured\',\'authorized\',\'payedout\',\'refunded\',\'unknown\',\'failed\',\'suspended\',\'expired\',\'pending\',\'canceled\',\'new\')');
        $this->addSql('ALTER TABLE role CHANGE name name ENUM(\'ROLE_CLIENT\',\'ROLE_ADMIN\',\'ROLE_SUPER_ADMIN\',\'ROLE_FORMATION_READ\',\'ROLE_FORMATION_CREATE\',\'ROLE_FORMATION_DELETE\',\'ROLE_SIGNATURE_READ\',\'ROLE_SIGNATURE_CREATE\',\'ROLE_SIGNATURE_DELETE\',\'ROLE_SIGNATUREPACK_CREATE\',\'ROLE_DOCUMENT_READ\',\'ROLE_NEWS_READ\')');
        $this->addSql('ALTER TABLE term CHANGE role role ENUM(\'ROLE_USER\',\'ROLE_CLIENT\',\'ROLE_COMPANY\',\'ROLE_CONTACT\')');
        $this->addSql('ALTER TABLE user CHANGE type type ENUM(\'contact\',\'company\',\'collaborator\',\'commercial_agent\',\'legal_representative\')');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE user_metadata (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, state VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, date DATETIME NOT NULL, entity_id VARCHAR(20) NOT NULL COLLATE utf8_unicode_ci, type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, INDEX IDX_AF99D014A76ED395 (user_id), UNIQUE INDEX resource_idx (state, user_id, entity_id, type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user_metadata ADD CONSTRAINT FK_AF99D014A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('DROP TABLE contact_metadata');
        $this->addSql('ALTER TABLE document_asset CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE external_formation ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE external_formation ADD CONSTRAINT FK_641B8EABA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_641B8EABA76ED395 ON external_formation (user_id)');
        $this->addSql('ALTER TABLE news CHANGE link_type link_type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE `order` CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE payment CHANGE status status VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE role CHANGE name name VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE term CHANGE role role VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE user CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
    }
}
