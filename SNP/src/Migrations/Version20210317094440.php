<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210317094440 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE formation_foad (id INT AUTO_INCREMENT NOT NULL, formation_id INT NOT NULL, quiz LONGTEXT DEFAULT NULL, video LONGTEXT DEFAULT NULL, `write` LONGTEXT DEFAULT NULL, INDEX IDX_1D64E1395200282E (formation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE formation_foad ADD CONSTRAINT FK_1D64E1395200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');

        $formations = $this->connection->createQueryBuilder()
            ->select('f.id', 'f.quiz')
            ->from('formation', 'f')
            ->where('f.quiz != ""')
            ->execute()->fetchAll();

        foreach($formations as $formation)
            $this->addSql("INSERT INTO formation_foad (quiz, formation_id) VALUES ('".str_replace("'", "\'", $formation['quiz'])."',".$formation['id'].")");

        $this->addSql('ALTER TABLE formation DROP quiz');
        //$this->addSql('ALTER TABLE formation_participant_connection DROP raw_log');
        $this->addSql('ALTER TABLE news CHANGE role role ENUM(\'ROLE_USER\', \'ROLE_CLIENT\', \'ROLE_COMPANY\', \'ROLE_CONTACT\') NOT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\RoleEnum)\'');
        $this->addSql('ALTER TABLE page CHANGE role role ENUM(\'ROLE_USER\', \'ROLE_CLIENT\', \'ROLE_COMPANY\', \'ROLE_CONTACT\') NOT NULL COMMENT \'(DC2Type:App\\\\DBAL\\\\RoleEnum)\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE formation_foad');
        $this->addSql('ALTER TABLE formation ADD quiz LONGTEXT DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE formation_participant_connection ADD raw_log LONGTEXT DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE news CHANGE role role VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE page CHANGE role role VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
    }
}
