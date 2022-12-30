<?php

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method Menu|null find($id, $lockMode = null, $lockVersion = null)
 * @method Menu|null findOneBy(array $criteria, array $orderBy = null)
 * @method Menu[]    findAll()
 * @method Menu[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MenuRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, Menu::class, $parameterBag);
    }


	/**
	 * @param Menu[] $items
	 * @param bool $type
	 * @return array
	 */
	public function hydrateAll($items, $type=false){

		$_items = parent::hydrateAll($items, $type);
		return $this->addDepth($_items);
	}


	/**
	 * @param Menu $menu
	 * @param bool $type
	 * @return array
	 */
	public function hydrate(Menu $menu, $type=false){

		return [
			'id'=>$menu->getId(),
			'parent'=>$menu->getParent(),
			'title'=>$menu->getTitle(),
			'link'=>$menu->getLink(),
			'location'=>$menu->getLocation(),
			'type'=>$menu->getType(),
			'target'=>$menu->getTarget(),
			'order'=>$menu->getMenuOrder(),
		];
	}

	/**
	 * @param $items
	 * @param int $parent_id
	 * @return array
	 */
	protected function addDepth($items, $parent_id=0)
	{
		$branch = [];

		foreach ($items as $item)
		{
			if( $item['parent'] == $parent_id )
			{
				if( $children = $this->addDepth($items, $item['id']))
					$item['children'] = $children;

				$location = $item['location'];
				unset($item['location'], $item['parent']);

				if( !$parent_id )
					$branch[(string)$location][] = $item;
				else
					$branch[] = $item;
			}
		}

		return $branch;
	}
}
