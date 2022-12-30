<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220127144118 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE address CHANGE is_expert is_expert TINYINT(1) DEFAULT NULL, CHANGE is_real_estate_agent is_real_estate_agent TINYINT(1) DEFAULT NULL, CHANGE is_other_collaborator is_other_collaborator TINYINT(1) DEFAULT NULL');
        $this->addSql("UPDATE address set is_real_estate_agent=null, is_other_collaborator=null, is_commercial_agent=null, is_expert=null WHERE 1");

        $this->addSql("UPDATE address set is_real_estate_agent = true where positions like '%Négociateur immobilier%'");
        $this->addSql("UPDATE address set is_other_collaborator = true where positions like '%Autre collaborateur%'");
        $this->addSql("UPDATE address set is_commercial_agent = true where positions like '%Agent commercial%'");
        $this->addSql("UPDATE address set is_expert = true where positions like '%Expert immobilier%'");
     }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    }
}
