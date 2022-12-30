<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210209121454 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
	    $this->addSql('ALTER TABLE page ADD role ENUM(\'ROLE_USER\',\'ROLE_CLIENT\',\'ROLE_CONTACT\',\'ROLE_COMPANY\')');
	    $this->addSql("DELETE FROM page WHERE 1");
	    $this->addSql("DELETE FROM resource WHERE dtype = 'page'");
	    $this->addSql("DELETE FROM `option` WHERE name = 'cms_last_sync'");
       }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE page DROP role');
      }
}
