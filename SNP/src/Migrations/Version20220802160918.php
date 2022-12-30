<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220802160918 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

         $this->addSql('ALTER TABLE registration ADD contract_rcp_id INT DEFAULT NULL, ADD contract_pj_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A7E7C55907 FOREIGN KEY (contract_rcp_id) REFERENCES contract (id)');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A7B16A80B3 FOREIGN KEY (contract_pj_id) REFERENCES contract (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_62A8A7A7E7C55907 ON registration (contract_rcp_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_62A8A7A7B16A80B3 ON registration (contract_pj_id)');
      }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7E7C55907');
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7B16A80B3');
        $this->addSql('DROP INDEX UNIQ_62A8A7A7E7C55907 ON registration');
        $this->addSql('DROP INDEX UNIQ_62A8A7A7B16A80B3 ON registration');
        $this->addSql('ALTER TABLE registration DROP contract_rcp_id, DROP contract_pj_id');
       }
}
