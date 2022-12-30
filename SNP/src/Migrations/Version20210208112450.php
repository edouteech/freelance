<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210208112450 extends AbstractMigration
{
	public function getDescription() : string
	{
		return '';
	}

	public function up(Schema $schema) : void
	{
		// this up() migration is auto-generated, please modify it to your needs
		$this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

		$this->addSql('ALTER TABLE news ADD role ENUM(\'ROLE_USER\',\'ROLE_CLIENT\',\'ROLE_CONTACT\',\'ROLE_COMPANY\'), CHANGE link_type link_type ENUM(\'page\',\'external\',\'document\',\'article\')');
		$this->addSql('ALTER TABLE resource DROP role');
		$this->addSql("DELETE FROM news WHERE 1");
		$this->addSql("DELETE FROM resource WHERE dtype = 'news'");
		$this->addSql("DELETE FROM `option` WHERE name = 'cms_last_sync'");
	}

	public function down(Schema $schema) : void
	{
		// this down() migration is auto-generated, please modify it to your needs
		$this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

		$this->addSql('ALTER TABLE news DROP role, CHANGE link_type link_type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
		$this->addSql('ALTER TABLE resource ADD role VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
	}
}
