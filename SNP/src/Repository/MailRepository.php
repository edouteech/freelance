<?php

namespace App\Repository;

use App\Entity\Mail;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method Mail|null find($id, $lockMode = null, $lockVersion = null)
 * @method Mail|null findOneBy(array $criteria, array $orderBy = null)
 * @method Mail[]    findAll()
 * @method Mail[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MailRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, Mail::class, $parameterBag);
    }

	/**
	 * @param Mail|null $mail
	 * @param bool $type
	 * @return array
	 */
	public function hydrate(?Mail $mail, $type=false)
	{
		if( !$mail )
			return null;

		return [
			'id' => $mail->getId(),
			'street' => $mail->getStreet(),
			'zip' => $mail->getZip(),
			'city' => $mail->getCity(),
			'country' => $mail->getCountry()
		];
	}
}
