<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FormationFoadRepository")
 */
class FormationFoad extends AbstractEntity
{
    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $quiz;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $video;

    /**
     * @ORM\Column(type="text", nullable=true, name="`write`")
     */
    private $write;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $documents;

    /**
     * @ORM\ManyToOne(targetEntity=Formation::class, inversedBy="foad")
     * @ORM\JoinColumn(nullable=false)
     */
    private $formation;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $driveFileId;

    public function getDriveFileId(): ?string
    {
        return $this->driveFileId;
    }

    public function setDriveFileId(?string $driveFileId): self
    {
        $this->driveFileId = $driveFileId;

        return $this;
    }

    public function getVideo(): ?array
    {
        if( $this->video && is_string($this->video) )
            return json_decode($this->video, true);

        return null;
    }

    public function setVideo($video): self
    {
        if( is_array($video) )
            $video = json_encode($video);

        $this->video = $video;

        return $this;
    }

    public function getDocuments(): ?array
    {
        if( $this->documents && is_string($this->documents) )
            return json_decode($this->documents, true);

        return null;
    }

    public function setDocuments($documents): self
    {
        if( is_array($documents) )
            $documents = json_encode($documents);

        $this->documents = $documents;

        return $this;
    }

    public function getWrite(): ?array
    {
        if( $this->write && is_string($this->write) )
            return json_decode($this->write, true);

        return null;
    }

    public function setWrite($write): self
    {
        if( is_array($write) )
            $write = json_encode($write);

        $this->write = $write;

        return $this;
    }

    public function getQuiz($id=false): ?array
    {
        $quiz = false;

        if( $id ){

            $write = $this->getWrite();

            foreach ($write['chapters'] as $chapters){

                foreach ($chapters['subchapters'] as $chapter){

                    if( $chapter['layout'] == 'quiz' && $chapter['id']??false == $id ){

                        $quiz = $chapter['quiz'];
                        break 2;
                    }
                }
            }
        }
        elseif( $this->quiz && is_string($this->quiz) ){

            $quiz = json_decode(html_entity_decode($this->quiz), true);
        }

        if( !empty($quiz) && is_array($quiz) ){

            foreach($quiz as &$question){

                $answers = [];

                foreach ($question['answers'] as $answer)
                    $answers[] = $answer['answer']??'';

                $question['answers'] = $answers;

                unset($question['value'], $question['match'], $question['answer_count'], $question['details']);
            }

            return $quiz;
        }

        return null;
    }

    public function setQuiz($quiz): self
    {
        if( is_array($quiz) )
            $quiz = json_encode($quiz);

        $this->quiz = $quiz;

        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;

        return $this;
    }
}
