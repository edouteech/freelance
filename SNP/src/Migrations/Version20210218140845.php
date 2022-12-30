<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use App\Entity\FormationParticipant;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210218140845 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE formation_participant_connection (id INT AUTO_INCREMENT NOT NULL, formation_participant_id INT NOT NULL, join_at DATETIME DEFAULT NULL, leave_at DATETIME DEFAULT NULL, duration INT DEFAULT NULL, INDEX IDX_9DAB3CF04A484094 (formation_participant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE formation_participant_connection ADD CONSTRAINT FK_9DAB3CF04A484094 FOREIGN KEY (formation_participant_id) REFERENCES formation_participant (id)');

        $participants = (
            $this->connection->createQueryBuilder()
                ->select('p.entity_id', 'p.data')
                ->from('eudo_entity_metadata', 'p')
                ->where('p.entity = :entity')
                ->setParameter('entity', FormationParticipant::class)
                ->execute()
                ->fetchAll()
        );

        $values = [];

        foreach($participants as $participant){

	        $participant = array_merge($participant, json_decode($participant['data'], true));

	        if( isset($participant['raw_log']) && !empty($participant['raw_log']) ){

	        	foreach ($participant['raw_log'] as $log){

			        $tempParticipant = [];

			        $tempParticipant['formation_participant_id'] = (int)$participant['entity_id'];
			        $tempParticipant['join_at'] = isset($log['join_time']) && !empty($log['join_time']) ? "'" . date('Y-m-d H:i:s' ,strtotime($log['join_time'])) . "'" : "null";
			        $tempParticipant['leave_at'] = isset($log['leave_time']) && !empty($log['leave_time']) ? "'" . date('Y-m-d H:i:s' ,strtotime($log['leave_time'])) . "'" : "null";
			        $tempParticipant['duration'] = isset($log['duration']) && !is_null($log['duration']) ? $log['duration'] : "null";

			        $values[] = implode(', ', $tempParticipant);
		        }
	        }
	        else{

		        $tempParticipant = [];

		        $tempParticipant['formation_participant_id'] = (int)$participant['entity_id'];
		        $tempParticipant['join_at'] = isset($participant['join_time']) && !empty($participant['join_time']) ? "'" . date('Y-m-d H:i:s' ,strtotime($participant['join_time'])) . "'" : "null";
		        $tempParticipant['leave_at'] = isset($participant['leave_time']) && !empty($participant['leave_time']) ? "'" . date('Y-m-d H:i:s' ,strtotime($participant['leave_time'])) . "'" : "null";
		        $tempParticipant['duration'] = isset($participant['duration']) && !is_null($participant['duration']) ? $participant['duration'] : "null";

		        $values[] = implode(', ', $tempParticipant);
	        }
        }

        $participantsAsString = "(" . implode("), (", $values) . ")";

        $this->addSql("INSERT INTO formation_participant_connection ( formation_participant_id, join_at, leave_at, duration) VALUES $participantsAsString");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE formation_participant_connection');
    }
}
