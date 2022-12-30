<?php

namespace App\Service;

use App\Entity\Signatory;
use App\Entity\Signature;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

class ContraliaAction extends AbstractService {

	private $contraliaConnector;
	private $entityManager;
	private $kernel;

	/**
	 * Contralia constructor.
	 *
	 * https://www.contralia.fr/Contralia/
	 *
	 * @param ContraliaConnector $contraliaConnector
	 * @param EntityManagerInterface $entityManager
	 * @param KernelInterface $kernel
	 */
	public function __construct(ContraliaConnector $contraliaConnector, EntityManagerInterface $entityManager, KernelInterface $kernel){

		$this->contraliaConnector = $contraliaConnector;
		$this->entityManager = $entityManager;
		$this->kernel = $kernel;
	}

	/**
	 * @param $signature
	 * @throws Exception
	 */
	public function initiate(Signature &$signature){

		$organizationalUnitCode = 'CONTRALIA_'.strtoupper($signature->getAccount()).'_UNIT_CODE';

		if( !$_ENV[$organizationalUnitCode]??false )
			throw new Exception($organizationalUnitCode.' is empty');

		$this->contraliaConnector->setAccount($signature->getAccount());

		$transactionId = $this->contraliaConnector->initiate([
			'organizationalUnitCode'=>$_ENV[$organizationalUnitCode],
			'signatoriesCount'=>$signature->getCount(),
			'customRef'=>$signature->getEntityId()
		]);

		$signature->setTransactionId($transactionId);

		$this->entityManager->persist($signature);
		$this->entityManager->flush();
	}

	/**
	 * @param Signature $signature
	 * @param $filepath
	 * @param array $params
	 * @return string
	 * @throws Exception
	 */
	public function upload(Signature &$signature, $filepath, $params=[]){

		$this->contraliaConnector->setAccount($signature->getAccount());

		$params['file'] = $filepath;

		if( isset($params['fields']) && is_array($params['fields']) ){

			$fields = [];

			for($i=1; $i<=$signature->getCount(); $i++){

				$line = ceil($i / ($params['fields']['per_row']??1));
				$col = $i % ($params['fields']['per_row']??1);
				$col = $col ?: ($params['fields']['per_row']??1);

				$x = $params['fields']['origin_x'] + $params['fields']['width']*($col-1) + $params['fields']['offset_x']*$col;
				$y = $params['fields']['origin_y'] - ( $params['fields']['height']*($line-1) + $params['fields']['offset_y']*$line );

				//todo: handle multiple pages

				$fields[] = [
					'type'=>'signatorySignature',
					'settings'=>['x'=>$x, 'y'=>$y, 'width'=>$params['fields']['width'], 'height'=>$params['fields']['height'], 'page'=>$params['fields']['page']]
				];
			}

			$params['fields'] = $fields;
		}

		$filename = $this->contraliaConnector->upload($signature->getTransactionId(), $params);

		$signature->setFileUploaded(true);

		$this->entityManager->persist($signature);
		$this->entityManager->flush();

		return $filename;
	}

    /**
     * @param Signatory $signatory
     * @param int $position
     * @throws Exception
     */
	public function addSignatory(Signatory &$signatory, int $position=1){

		$transactionId = $signatory->getSignature()->getTransactionId();

		$address = $signatory->getAddress();
		$contact = $address->getContact();
		$signature = $signatory->getSignature();

		$this->contraliaConnector->setAccount($signature->getAccount());

		$input = [
			'firstname'=>$contact->getFirstname(),
			'lastname'=>$contact->getLastname(),
			'email'=>$address->getEmail(),
			'phone'=>$address->getPhone()
		];

		if( $company = $address->getCompany() )
			$input['companyName'] = $company->getName();

		$signatureId = $this->contraliaConnector->addSignatory($transactionId, $position, $input);

		$signatory->setTransactionId($signatureId);

		$this->entityManager->persist($signatory);
		$this->entityManager->flush();
	}

	/**
	 * @param $signatory
	 * @param array $params
	 * @return array|bool
	 * @throws Exception
	 */
	public function getOtp(Signatory $signatory, $params=[]){

		$signature = $signatory->getSignature();
		$address = $signatory->getAddress();

		$this->contraliaConnector->setAccount($signature->getAccount());

		$params = array_merge([
			'phone'=>$address->getPhone(),
			'email'=>$address->getEmail()
		], $params);

		//todo: check email

		return $this->contraliaConnector->genOtp($signatory->getTransactionId(), $params);
	}

	/**
	 * @param $signatory
	 * @param $otp
	 * @return array|bool
	 * @throws Exception
	 */
	public function checkOtp(Signatory $signatory, $otp){

		$signature = $signatory->getSignature();

		$this->contraliaConnector->setAccount($signature->getAccount());

		return $this->contraliaConnector->checkOtp($signatory->getTransactionId(), [
			'otp'=>$otp
		]);
	}

	/**
	 * @param Signatory $signatory
	 * @param $otp
	 * @return array|bool
	 * @throws Exception
	 */
	public function sign(Signatory $signatory, $otp){

		$signature = $signatory->getSignature();

		$this->contraliaConnector->setAccount($signature->getAccount());

		return $this->contraliaConnector->sign($signatory->getTransactionId(), [
			'otp'=>$otp
		]);
	}

    /**
     * @param Signature $signature
     * @return array|bool
     * @throws Exception
     */
	public function terminate(Signature $signature){

		$this->contraliaConnector->setAccount($signature->getAccount());
        $signatureRepository = $this->entityManager->getRepository(Signature::class);

		try {

			$status = $this->contraliaConnector->terminate($signature->getTransactionId());

            $signature->setStatus('closed');
            $signatureRepository->save($signature);

            return $status;

		} catch (Exception $e) {


			if( $e->getMessage() == 'Transaction not open'){

                $signature->setStatus('closed');
                $signatureRepository->save($signature);

                return false;
			}
			else{

                $signature->setStatus('archived');
                $signatureRepository->save($signature);

				throw $e;
			}
		}
	}

    /**
     * @param Signature $signature
     * @param $name
     * @param bool $filepath
     * @return string
     * @throws Exception
     */
	public function getFinalDoc(Signature $signature, $name, $filepath=false){

		$this->contraliaConnector->setAccount($signature->getAccount());

		try {

			$content = $this->contraliaConnector->getFinalDoc($signature->getTransactionId(), ['name' => $name]);

			$filesystem = new Filesystem();

            if( !$filepath ){

                $filedir = $this->kernel->getCacheDir() .'/contralia';

                if( !is_dir($filedir ) )
                    $filesystem->mkdir($filedir);

                $filepath = $filedir.'/'.$signature->getTransactionId();
            }

            $filesystem->dumpFile($filepath, $content);

			return $filepath;

		} catch (Exception $e) {

			if( $e->getMessage() == 'Transaction not archived' )
				return false;
			else
				throw $e;
		}
	}

	/**
	 * @param Signature $signature
	 * @param $name
	 * @return array|bool
	 * @throws Exception
	 */
	public function getCurrentDoc(Signature $signature, $name){

		$this->contraliaConnector->setAccount($signature->getAccount());

		try {

			$content = $this->contraliaConnector->getCurrentDoc($signature->getTransactionId(), ['name' => $name]);

			$filesystem = new Filesystem();

			$filedir = $this->kernel->getCacheDir() .'/contralia';

			if( !is_dir($filedir ) )
				$filesystem->mkdir($filedir);

			$filepath = $filedir.'/'.$signature->getTransactionId();

			$filesystem->dumpFile($filepath, $content);

			return $filepath;

		} catch (Exception $e) {

			if( $e->getMessage() == 'Transaction not archived' )
				return false;
			else
				throw $e;
		}
	}
}