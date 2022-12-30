<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserAccessLog;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;


/**
 * @method UserAccessLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserAccessLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserAccessLog[]    findAll()
 * @method UserAccessLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserAccessLogRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAccessLog::class);
    }

    /**
     * @param User $user
     * @param $type
     * @param Request $request
     * @return UserAccessLog
     * @throws ORMException
     * @throws ExceptionInterface
     */
    public function create(UserInterface $user, $type, Request $request){

       $userAccessLog = new UserAccessLog();

	   if( $user->hasRole('ROLE_ADMIN') )
		   return $userAccessLog;

       $userAccessLog->setType($type);
       $userAccessLog->setUser($user);
       $userAccessLog->setIpHash($this->getHash($request->getClientIp()));

       $this->save($userAccessLog);

       return $userAccessLog;
   }
}
