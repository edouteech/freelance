<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220621130141 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE registration ADD insurer_name_rcp_ac VARCHAR(255) DEFAULT NULL, ADD id_eudo_ac INT DEFAULT NULL, ADD has_already_rcp_ac ENUM(\'no\', \'asseris\', \'other\') DEFAULT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\RcpTypeEnum)\', ADD step_ac INT DEFAULT NULL, ADD status_ac TINYINT(1) DEFAULT NULL, CHANGE information information DATETIME DEFAULT NULL, CHANGE agencies agencies DATETIME DEFAULT NULL, CHANGE contract contract DATETIME DEFAULT NULL, CHANGE payment payment DATETIME DEFAULT NULL, CHANGE valid_payment valid_payment DATETIME DEFAULT NULL, CHANGE valid_asseris valid_asseris DATETIME DEFAULT NULL, CHANGE valid_caci valid_caci DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE registration DROP insurer_name_rcp_ac, DROP member_id, DROP id_eudo_ac, DROP has_already_rcp_ac, DROP step_ac, DROP status_ac');
    }
}
