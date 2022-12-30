<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method Role|null find($id, $lockMode = null, $lockVersion = null)
 * @method Role|null findOneBy(array $criteria, array $orderBy = null)
 * @method Role[]    findAll()
 * @method Role[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoleRepository extends AbstractRepository
{
	private $user;

	public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag, TokenStorageInterface $tokenStorage)
	{
		parent::__construct($registry, Role::class, $parameterBag);

		$this->user = $tokenStorage->getToken()?$tokenStorage->getToken()->getUser():false;
	}

	/**
	 * @return array
	 */
	public function getUserRoles(){

		return $this->user->getRoles();
	}

	/**
	 * @return UserInterface
	 */
	public function getUser(){

		return $this->user;
	}

    /**
     * @param array|null $roles
     * @return array
     */
    public function findRolesIdByName(?array $roles){

        $_roles = $this->findRolesByName($roles);
        $roles = [];

        foreach ($_roles as $role)
            $roles[] = $role->getId();

        return $roles;
    }

    /**
     * @param array|null $roles
     * @return array
     */
    public function findRolesNameById(?array $roles){

        $_roles = $this->findRolesById($roles);
        $roles = [];

        if( is_array($_roles) ){

            foreach ($_roles as $role)
                $roles[] = $role->getName();
        }

        return $roles;
    }

    /**
     * @param array|null $roles
     * @return Role[]|false
     */
    public function findRolesByName(?array $roles)
	{
        if( !$roles || !is_array($roles) || !count($roles) )
			return false;

		return $this->createQueryBuilder('r')
				->where('r.name IN (:names)')
				->andWhere('r.name NOT IN (:adminRoles)')
				->setParameters([
					'names' => $roles,
					'adminRoles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']
				])
				->getQuery()
				->getResult();
	}

    /**
     * @param array|null $roles
     * @return Role[]|false
     */
    public function findRolesById(?array $roles)
	{
        if( !$roles || !is_array($roles) || !count($roles) )
			return false;

		return $this->createQueryBuilder('r')
				->where('r.id IN (:ids)')
				->andWhere('r.name NOT IN (:adminRoles)')
				->setParameters([
					'ids' => $roles,
					'adminRoles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']
				])
				->getQuery()
				->getResult();
	}
}
