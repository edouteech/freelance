<?php

namespace App\Repository;

use App\Entity\AbstractEntity;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserAccessLog;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @param $action
     * @param AbstractEntity $entity
     * @return Notification
     * @throws ExceptionInterface
     * @throws ORMException
     */
    public function create($action, AbstractEntity $entity){

        $notification = new Notification();

        $notification->setEntityType(get_class($entity));
        $notification->setEntityId($entity->getId());
        $notification->setAction($action);

        $this->save($notification);

        return $notification;
    }

    /**
     * @param $action
     * @param AbstractEntity $entity
     * @return Notification
     */
    public function findOnByEntity($action, AbstractEntity $entity){

    	return $this->findOneBy(['entityId'=>$entity->getId(), 'entityType'=>get_class($entity), 'action'=>$action]);
    }
}
