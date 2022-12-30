<?php

namespace App\Repository;

use App\Entity\Alert;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Alert|null find($id, $lockMode = null, $lockVersion = null)
 * @method Alert|null findOneBy(array $criteria, array $orderBy = null)
 * @method Alert[]    findAll()
 * @method Alert[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlertRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    public function sentToday($to, $subject, $message){

        $hash = md5($to.$subject.$message);

        $today = new \DateTime();
        $today->setTime(0,0);

        if( !$this->findOneBy(['hash'=>$hash, 'sentAt'=>$today]) ){

            $alert = new Alert();
            $alert->setHash($hash);
            $alert->setSentAt($today);
            $alert->setMessage($message);

            $this->save($alert);

            return false;
        }

        return true;
    }
}
