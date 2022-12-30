<?php

namespace App\Repository;

use App\Entity\Option;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method Option|null find($id, $lockMode = null, $lockVersion = null)
 * @method Option|null findOneBy(array $criteria, array $orderBy = null)
 * @method Option[]    findAll()
 * @method Option[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OptionRepository extends AbstractRepository
{
	public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
	{
		parent::__construct($registry, Option::class, $parameterBag);
	}

	/**
	 * @param $name
	 * @return mixed
	 */
	public function get($name){

		$option = $this->findOneBy(['name'=>$name]);

		if( $option && !$option->isExpired() )
			return $option->getValue();

		return false;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param bool $expire
	 * @return bool
	 */
	public function set($name, $value, $expire=false){

		$option = $this->findOneBy(['name'=>$name]);

		if( !$option ){

			$option = new Option();
			$option->setName($name);
		}

		if( $expire )
			$option->setExpire($expire);

		$option->setValue($value);

		try {
			$this->save($option);
			return true;
		} catch (ORMException|ExceptionInterface $e) {
			return false;
		}
	}

	/**
	 * @param $name
	 * @param $public
	 * @return bool
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function setPublic($name, $public){

		$option = $this->findOneBy(['name'=>$name]);

		if( $option->getPublic() != $public ){

			$option->setPublic($public);
			$this->save($option);
		}

		return true;
	}


	/**
	 * @param Option[] $items
	 * @param bool $type
	 * @return array
	 */
	public function hydrateAll($items, $type=false){

		$data = [];

		foreach ($items as $item){

			if( $item->getType() ){

				$data[$item->getName()] = [
					'value'=>$item->getValue(),
					'type'=>$item->getType()
				];
			}
			else{

				$data[$item->getName()] = $item->getValue();
			}
		}

		return $data;
	}


	/**
	 * @param Option $option
	 * @param bool $type
	 * @return array
	 */
	public function hydrate(Option $option, $type=false){

		return [
			'type'=>$option->getType(),
			'name'=>$option->getName(),
			'value'=>$option->getValue(),
		];
	}
}
