<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220627104848 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE contract ADD insurer VARCHAR(255) DEFAULT NULL, ADD non_renewable TINYINT(1) DEFAULT NULL, ADD policy_number VARCHAR(255) DEFAULT NULL, ADD payment_method VARCHAR(255) DEFAULT NULL, ADD web TINYINT(1) DEFAULT NULL, ADD start_date DATETIME DEFAULT NULL, ADD end_date DATETIME DEFAULT NULL');
        $this->addSql('CREATE TABLE contract_details (id INT AUTO_INCREMENT NOT NULL, contact_id INT DEFAULT NULL, contract_id INT DEFAULT NULL, product VARCHAR(255) DEFAULT NULL, quantity INT DEFAULT NULL, non_renewable TINYINT(1) DEFAULT NULL, prorata VARCHAR(255) DEFAULT NULL, unit_price DOUBLE PRECISION DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, inserted_at DATETIME DEFAULT NULL, INDEX IDX_7A777642E7A1254A (contact_id), INDEX IDX_7A7776422576E0FD (contract_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE contract_details ADD CONSTRAINT FK_7A777642E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE contract_details ADD CONSTRAINT FK_7A7776422576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE contract DROP insurer, DROP non_renewable, DROP policy_number, DROP payment_method, DROP web, DROP start_date, DROP end_date');
        $this->addSql('DROP TABLE contract_details');
    }
}
