<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220125145232 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE formation_participant ADD agreement_id INT DEFAULT NULL, DROP agreement');
        $this->addSql('ALTER TABLE formation_participant ADD CONSTRAINT FK_88624BCA24890B2B FOREIGN KEY (agreement_id) REFERENCES agreement (id)');
        $this->addSql('CREATE INDEX IDX_88624BCA24890B2B ON formation_participant (agreement_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE formation_participant DROP FOREIGN KEY FK_88624BCA24890B2B');
        $this->addSql('DROP INDEX IDX_88624BCA24890B2B ON formation_participant');
        $this->addSql('ALTER TABLE formation_participant ADD agreement VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, DROP agreement_id');
    }
}
