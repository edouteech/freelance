<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221004135245 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `order` CHANGE type type ENUM(\'formation\',\'membership_snpi\',\'membership_vhs\',\'membership_asseris\',\'membership_caci\',\'register\',\'signature\') DEFAULT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\OrderTypeEnum)\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
	    $this->addSql('ALTER TABLE `order` CHANGE type type ENUM(\'formation\',\'membership_snpi\',\'membership_vhs\',\'membership_asseris\',\'membership_caci\',\'signature\') DEFAULT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\OrderTypeEnum)\'');
    }
}
