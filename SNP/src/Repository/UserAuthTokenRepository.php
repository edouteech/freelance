<?php

namespace App\Repository;

use App\Entity\UserAuthToken;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method UserAuthToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserAuthToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserAuthToken[]    findAll()
 * @method UserAuthToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserAuthTokenRepository extends AbstractRepository
{
	public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
	{
		parent::__construct($registry, UserAuthToken::class, $parameterBag);
	}


	/**
	 * Generate auth token and update user login data
	 *
	 * @param UserInterface $user
	 * @return string
	 * @throws ORMException
	 * @throws ExceptionInterface
	 */
	public function generate(UserInterface $user){

		// get previous token if exists
		if( $authTokens = $this->findBy(['user'=>$user]) )
			$this->deleteAll($authTokens);

		$authToken = new UserAuthToken();
		$authToken->setUser($user);

		$this->save($authToken);

		return $authToken->getValue();
	}


	/**
	 * Generate auth token and update user login data
	 *
	 * @param UserInterface $user
	 * @return void
	 * @throws ORMException
	 */
	public function removeAll(UserInterface $user){

		$userAuthTokens = $this->findBy(['user'=>$user]);

		if( $userAuthTokens )
			$this->deleteAll($userAuthTokens);
	}
}
