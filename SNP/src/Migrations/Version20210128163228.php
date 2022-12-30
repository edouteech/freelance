<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210128163228 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE contact_metadata CHANGE state state ENUM(\'favorite\', \'read\', \'pinned\') NOT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\ContactMetadataStateEnum)\', CHANGE type type ENUM(\'resource\', \'appendix\', \'tour\') NOT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\ContactMetadataTypeEnum)\'');
        $this->addSql('DELETE FROM document_asset WHERE type IS NULL');
        $this->addSql('ALTER TABLE document_asset CHANGE type type ENUM(\'pdf\', \'editable-pdf\') NOT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\DocumentAssetTypeEnum)\'');
        $this->addSql('ALTER TABLE news CHANGE link_type link_type ENUM(\'page\', \'external\', \'document\', \'article\') NOT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\NewsLinkTypeEnum)\'');
        $this->addSql('ALTER TABLE `order` CHANGE type type ENUM(\'formation\', \'membership_snpi\', \'membership_vhs\', \'membership_asseris\', \'membership_caci\', \'signature\') NOT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\OrderTypeEnum)\'');
        $this->addSql('ALTER TABLE payment CHANGE status status ENUM(\'captured\', \'authorized\', \'payedout\', \'refunded\', \'unknown\', \'failed\', \'suspended\', \'expired\', \'pending\', \'canceled\', \'new\') NOT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\PaymentStatusEnum)\'');
        $this->addSql('ALTER TABLE role CHANGE name name ENUM(\'ROLE_CLIENT\', \'ROLE_ADMIN\', \'ROLE_SUPER_ADMIN\', \'ROLE_FORMATION_READ\', \'ROLE_FORMATION_CREATE\', \'ROLE_FORMATION_DELETE\', \'ROLE_SIGNATURE_READ\', \'ROLE_SIGNATURE_CREATE\', \'ROLE_SIGNATURE_DELETE\', \'ROLE_SIGNATUREPACK_CREATE\', \'ROLE_DOCUMENT_READ\', \'ROLE_NEWS_READ\') NOT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\RoleNameEnum)\'');
        $this->addSql('ALTER TABLE term CHANGE role role ENUM(\'ROLE_USER\', \'ROLE_CLIENT\', \'ROLE_COMPANY\', \'ROLE_CONTACT\') NOT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\RoleEnum)\'');
        $this->addSql('ALTER TABLE user CHANGE type type ENUM(\'contact\', \'company\', \'collaborator\', \'commercial_agent\', \'legal_representative\') COMMENT \'(DC2Type:App\\\\DBAL\\\\UserTypeEnum)\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE contact_metadata CHANGE state state VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE document_asset CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE news CHANGE link_type link_type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE `order` CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE payment CHANGE status status VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE role CHANGE name name VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE term CHANGE role role VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE user CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
    }
}
