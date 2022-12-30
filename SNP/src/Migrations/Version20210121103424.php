<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210121103424 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

	    $this->addSql('ALTER TABLE user ADD dashboard LONGTEXT DEFAULT NULL');

	    $users = (
	    $this->connection->createQueryBuilder()
		    ->select('u.id', 'u.is_accessible', 'u.has_notification', 'u.last_sync_at')
		    ->from('user', 'u')
		    ->execute()
		    ->fetchAll()
	    );

	    foreach ($users as $user) {

		    $this->addSql(
			    sprintf(
				    'UPDATE user SET dashboard = \'%s\' WHERE id = %d',
				    json_encode([ 'isAccessible' => $user['is_accessible'], 'hasNotification' => $user['has_notification'], 'lastSyncAt' => $user['last_sync_at'] ]),
				    (int) $user['id']
			    )
		    );

	    }

	    $this->addSql('ALTER TABLE user DROP has_notification, DROP is_accessible');

        $this->addSql('CREATE TABLE agreement (id INT NOT NULL, contact_id INT NOT NULL, company_id INT DEFAULT NULL, formation_course_id INT NOT NULL, number VARCHAR(255) DEFAULT NULL, mode VARCHAR(255) DEFAULT NULL, generate_number TINYINT(1) DEFAULT NULL, generate_invoice TINYINT(1) DEFAULT NULL, validate_invoice TINYINT(1) DEFAULT NULL, compute_amounts TINYINT(1) DEFAULT NULL, invoice_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, inserted_at DATETIME DEFAULT NULL, INDEX IDX_2E655A24E7A1254A (contact_id), INDEX IDX_2E655A24979B1AD6 (company_id), INDEX IDX_2E655A249361994F (formation_course_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE formation_interest (id INT AUTO_INCREMENT NOT NULL, contact_id INT NOT NULL, company_id INT DEFAULT NULL, formation_course_id INT NOT NULL, created_at DATETIME NOT NULL, alert TINYINT(1) NOT NULL, send_at DATETIME DEFAULT NULL, INDEX IDX_ABAC4D96E7A1254A (contact_id), INDEX IDX_ABAC4D96979B1AD6 (company_id), INDEX IDX_ABAC4D969361994F (formation_course_id), UNIQUE INDEX interest (contact_id, formation_course_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE agreement ADD CONSTRAINT FK_2E655A24E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE agreement ADD CONSTRAINT FK_2E655A24979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)');
        $this->addSql('ALTER TABLE agreement ADD CONSTRAINT FK_2E655A249361994F FOREIGN KEY (formation_course_id) REFERENCES formation_course (id)');
        $this->addSql('ALTER TABLE formation_interest ADD CONSTRAINT FK_ABAC4D96E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE formation_interest ADD CONSTRAINT FK_ABAC4D96979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)');
        $this->addSql('ALTER TABLE formation_interest ADD CONSTRAINT FK_ABAC4D969361994F FOREIGN KEY (formation_course_id) REFERENCES formation_course (id)');
        $this->addSql('ALTER TABLE address ADD email_hash VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE document_asset CHANGE type type ENUM(\'pdf\',\'editable-pdf\')');
        $this->addSql('ALTER TABLE news CHANGE link_type link_type ENUM(\'page\',\'external\',\'document\',\'article\')');
        $this->addSql('ALTER TABLE `order` CHANGE type type ENUM(\'formation\',\'membership_snpi\',\'membership_vhs\',\'membership_asseris\',\'membership_caci\',\'signature\')');
        $this->addSql('ALTER TABLE payment ADD refund_amount INT DEFAULT NULL, CHANGE status status ENUM(\'captured\',\'authorized\',\'payedout\',\'refunded\',\'unknown\',\'failed\',\'suspended\',\'expired\',\'pending\',\'canceled\',\'new\')');
        $this->addSql('ALTER TABLE role CHANGE name name ENUM(\'ROLE_CLIENT\',\'ROLE_ADMIN\',\'ROLE_SUPER_ADMIN\',\'ROLE_FORMATION_READ\',\'ROLE_FORMATION_CREATE\',\'ROLE_FORMATION_DELETE\',\'ROLE_SIGNATURE_READ\',\'ROLE_SIGNATURE_CREATE\',\'ROLE_SIGNATURE_DELETE\',\'ROLE_SIGNATUREPACK_CREATE\',\'ROLE_DOCUMENT_READ\',\'ROLE_NEWS_READ\')');
        $this->addSql('ALTER TABLE term CHANGE role role ENUM(\'ROLE_USER\',\'ROLE_CLIENT\',\'ROLE_COMPANY\',\'ROLE_CONTACT\')');
        $this->addSql('ALTER TABLE user DROP INDEX UNIQ_8D93D649979B1AD6, ADD INDEX IDX_8D93D649979B1AD6 (company_id)');
        $this->addSql('ALTER TABLE user DROP INDEX UNIQ_8D93D649E7A1254A, ADD INDEX IDX_8D93D649E7A1254A (contact_id)');
        $this->addSql('DROP INDEX UNIQ_8D93D649AA08CB10 ON user');
        $this->addSql('ALTER TABLE user ADD change_password TINYINT(1) DEFAULT NULL, DROP last_sync_at, CHANGE type type ENUM(\'contact\',\'company\',\'collaborator\',\'commercial_agent\',\'legal_representative\')');
        $this->addSql('CREATE UNIQUE INDEX uuid ON user (login, company_id, contact_id)');
        $this->addSql('ALTER TABLE user_metadata CHANGE state state ENUM(\'favorite\',\'read\',\'pinned\'), CHANGE type type ENUM(\'resource\',\'appendix\',\'tour\')');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE agreement');
        $this->addSql('DROP TABLE formation_interest');
        $this->addSql('ALTER TABLE address DROP email_hash');
        $this->addSql('ALTER TABLE document_asset CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE news CHANGE link_type link_type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE `order` CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE payment DROP refund_amount, CHANGE status status VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE role CHANGE name name VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE term CHANGE role role VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE user DROP INDEX IDX_8D93D649E7A1254A, ADD UNIQUE INDEX UNIQ_8D93D649E7A1254A (contact_id)');
        $this->addSql('ALTER TABLE user DROP INDEX IDX_8D93D649979B1AD6, ADD UNIQUE INDEX UNIQ_8D93D649979B1AD6 (company_id)');
        $this->addSql('DROP INDEX uuid ON user');
        $this->addSql('ALTER TABLE user ADD is_accessible TINYINT(1) DEFAULT NULL, ADD last_sync_at DATETIME DEFAULT NULL, DROP dashboard, CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE change_password has_notification TINYINT(1) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649AA08CB10 ON user (login)');
        $this->addSql('ALTER TABLE user_metadata CHANGE state state VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
    }
}
