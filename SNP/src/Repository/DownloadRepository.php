<?php

namespace App\Repository;

use App\Entity\Download;
use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method Download|null find($id, $lockMode = null, $lockVersion = null)
 * @method Download|null findOneBy(array $criteria, array $orderBy = null)
 * @method Download[]    findAll()
 * @method Download[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DownloadRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Download::class);
    }

    /**
     * @param $uuid
     * @return Download|null
     * @throws NonUniqueResultException
     */
    public function findByUuid($uuid)
    {
        $now = new DateTime();

        $qb = $this->createQueryBuilder('d')
            ->where('d.uuid = :uuid')
            ->andWhere('d.expireAt >= :now')
            ->setParameter('uuid', $uuid)
            ->setParameter('now', $now);

        $query = $qb->getQuery();

        return $query->getOneOrNullResult();
    }

    /**
     * @param Request $request
     * @param $path
     * @param bool $delete
     * @param bool $filename
     * @return Download
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws Exception
     */
    public function create(Request $request, $path, $delete=true, $filename=false){

        if( !file_exists($path) )
            throw new NotFoundHttpException('File does not exists');

        $now = new DateTime();
        $download = new Download();
        $ipHash = $this->getHash($request->getClientIp());

        $download->setDeleteFile($delete);
        $download->setFilename($filename);
        $download->setPath($path);
        $download->setUuid(md5(uniqid($ipHash)));
        $download->setIpHash($ipHash);
        $download->setExpireAt($now->modify('+15 minutes'));

        $this->save($download);

        return $download;
    }

	/**
	 * @param $request
	 * @param $filename
	 * @param $rows
	 * @return Download
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function createFromCSVData($request, $filename, $rows, $delete=true){

		$filepath = tempnam(sys_get_temp_dir(), $filename);

		$content = implode("\n", $rows);
		$content = "\xEF\xBB\xBF".$content; //BOM

		file_put_contents($filepath, $content);

		return $this->create($request, $filepath, $delete, $filename);
	}

    /**
     * @return array
     */
    public function deleteExpired(){

        $qb = $this->createQueryBuilder('d');
        $now = new DateTime();

        $qb->delete()
            ->where('d.expireAt < :now')
            ->setParameter('now', $now);

        $query = $qb->getQuery();

        return $query->getResult();
    }

    /**
     * @param Download $download
     * @return array
     */
    public function hydrate(Download $download){

        return [
            'url'=>$_ENV['SECURE_URL'].'/download/'.$download->getUuid().'/'.$download->getFilename(),
            'expireAt'=>$this->formatDate($download->getExpireAt())
        ];
    }
}
