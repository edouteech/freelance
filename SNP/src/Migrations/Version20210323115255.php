<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210323115255 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

       // $this->addSql('ALTER TABLE formation ADD previous_formation_id INT DEFAULT NULL');
        //$this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BFFFD56A63 FOREIGN KEY (previous_formation_id) REFERENCES formation (id)');
        //$this->addSql('CREATE INDEX IDX_404021BFFFD56A63 ON formation (previous_formation_id)');
       // $this->addSql('ALTER TABLE formation_participant_connection DROP raw_log');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BFFFD56A63');
        $this->addSql('DROP INDEX IDX_404021BFFFD56A63 ON formation');
        $this->addSql('ALTER TABLE formation DROP previous_formation_id');
        $this->addSql('ALTER TABLE formation_participant_connection ADD raw_log LONGTEXT DEFAULT NULL COLLATE utf8_unicode_ci');
    }
}
