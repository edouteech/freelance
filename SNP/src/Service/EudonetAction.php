<?php

namespace App\Service;

use App\Entity\AbstractEudoEntity;
use App\Entity\Address;
use App\Entity\Agreement;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\FormationCourse;
use App\Entity\FormationParticipant;
use App\Entity\OrderDetail;
use App\Normalizer\EntityNormalizer;
use App\Repository\FormationParticipantRepository;
use App\Serializer\MaxDepthHandler;
use DateTime;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;

class EudonetAction extends AbstractService {

	private $eudonetConnector;
	private $entityManager;
	private $elearningConnector;
	private $mailer;


	/**
	 * Eudonet constructor.
	 * @param EudonetConnector $eudonetConnector
	 * @param EntityManagerInterface $entityManager
	 * @param ElearningConnector $elearningConnector
	 * @param Mailer $mailer
	 */
	public function __construct(EudonetConnector $eudonetConnector, EntityManagerInterface $entityManager, ElearningConnector $elearningConnector, Mailer $mailer){

		$this->eudonetConnector = $eudonetConnector;
		$this->elearningConnector = $elearningConnector;
		$this->entityManager = $entityManager;
		$this->mailer = $mailer;
	}

	/**
	 * @param $year
	 * @return bool|int
	 */
	public function getYearCode($year){

		$years = [1960=>2323, 1961=>2324, 1962=>2325, 1963=>2289, 1964=>2290, 1965=>2291, 1966=>2292, 1967=>2293, 1968=>2294, 1969=>2295, 1970=>2296, 1971=>2297, 1972=>2298, 1973=>2299, 1974=>2300, 1975=>2301, 1976=>2302, 1977=>2303, 1978=>2304, 1979=>2305, 1980=>2306, 1981=>2307, 1982=>2308, 1983=>2309, 1984=>2310, 1985=>2311, 1986=>2312, 1987=>2313, 1988=>2314, 1989=>2315, 1990=>2316, 1991=>2317, 1992=>2318, 1993=>2319, 1994=>2320, 1995=>2321, 1996=>2322, 1997=>2287, 1998=>2288, 1999=>2286, 2000=>2285, 2001=>2284, 2002=>2283, 2003=>2282, 2004=>2281, 2005=>2270, 2006=>2280, 2007=>1000025, 2008=>1000026, 2009=>1000027, 2010=>1000028, 2011=>1000029, 2012=>1000030, 2013=>1000031, 2014=>1000032, 2015=>1000033, 2016=>1000034, 2017=>23, 2018=>1405, 2019=>1782, 2020=>1843, 2021=>1844, 2022=>1845, 2023=>3378, 2024=>3379, 2025=>3380, 2026=>3381, 2027=>3382, 2028=>3383, 2029=>3384, 2030=>3385];
		return $years[$year]??false;
	}

	/**
	 * @param $table
	 * @param $file
	 * @return bool|int
	 * @throws Exception
	 * @throws ExceptionInterface
	 */
	public function getUrl($table, $file){

		$qb = $this->eudonetConnector->createQueryBuilder();
		$table_id = $qb->getTableId($table);

		return $this->eudonetConnector->getUrl($table_id, $file);
	}

    /**
     * @param UserInterface $user
     * @param OrderDetail $orderDetail
     * @param FormationCourse $formationCourse
     * @param Contact[] $contacts
     * @return void
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     */
	public function registerFormationParticipants(UserInterface $user, OrderDetail $orderDetail, FormationCourse $formationCourse, $contacts){

		$agreement_id = $this->createAgreement($user, $formationCourse->getId());

		$participants_id = $this->insertParticipants($contacts, $user->getCompany(), $agreement_id, $formationCourse->getId());

		if( $orderDetail->getPaymentId() )
			$this->generateFormationInvoice($orderDetail, $agreement_id, $participants_id, 'vhs_business_school');
	}


	/**
	 * @param $paymentId
	 * @return array|bool
     * @throws Exception
	 */
	public function generateInvoice($paymentId){

		$payment = $this->eudonetConnector->select(['generate_invoice'], 'payment', $paymentId);

		if( !$payment['generate_invoice'] )
			$this->eudonetConnector->update('payment', $paymentId, ['generate_invoice'=>true]);

		$payment = $this->eudonetConnector->select(['invoice_id'], 'payment', $paymentId);

		$this->eudonetConnector->update('invoice', $payment['invoice_id'], ['payment_method'=>'credit_card', 'status'=>'validated']);
		$this->eudonetConnector->update('invoice', $payment['invoice_id'], ['status'=>'clear']);

        return $payment['invoice_id'];
	}


	/**
	 * @param $productID
	 * @param $paymentId
	 * @return array|bool
	 * @throws Exception
	 */
	public function updatePayment($productID, $paymentId=false){

		$contract = $this->eudonetConnector->select('*', 'contract', $productID);

		if( !intval($contract['generate_invoice']) ){

			$this->eudonetConnector->update('contract', $productID, ['generate_invoice'=>'1']);
			$contract = $this->eudonetConnector->select('*', 'contract', $productID);
		}

		$this->eudonetConnector->update('invoice', $contract['invoice'], ['payment_method'=>'credit_card', 'status'=>'validated']);

		if( $paymentId )
			$this->eudonetConnector->update('payment', $paymentId, ['invoice_id'=>$contract['invoice'],'contract_id'=>$productID]);

		return $contract;
	}

	/**
	 * @param UserInterface $user
	 * @param int|null $paymentId
	 * @param $productID
	 * @return void
	 * @throws Exception
	 */
	public function registerSNPIMembership(UserInterface $user, $paymentId, $productID){

		$qb = $this->eudonetConnector->createQueryBuilder();
		$qb->select('*')->from('subscription')->where('title','=', $productID);

		if( !$subscriptions = $this->eudonetConnector->execute($qb) )
			throw new Exception("Can't get subscriptions");

		foreach ($subscriptions as $subscription){

			$invoice = $this->eudonetConnector->select('*','invoice', $subscription['invoice_id']);

			if( $invoice && $invoice['status'] == '2 .Validée' ) { //todo: handle dbvalue instead of label

				$this->eudonetConnector->update('invoice', $invoice['id'], ['status' => 'ongoing']);
				$this->eudonetConnector->update('invoice', $invoice['id'], ['payment_method' => 'credit_card', 'status' => 'validated']);

				if ($paymentId)
					$this->eudonetConnector->update('payment', $paymentId, ['invoice_id' => $invoice['id']]);

                if( $_ENV['EUDONET_GENERATE_DOC']??true )
                    $this->generateFile(116, 'invoice', $invoice['id']);
			}
		}
	}

	/**
	 * @param UserInterface $user
	 * @param bool $entity
	 * @param bool $details
	 * @return array|float
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	public function getMembershipStatus(UserInterface $user, $entity=false, $details=false){

		$qb = $this->eudonetConnector->createQueryBuilder();

		$status = [
			'snpi'=>[
				'enabled'=>false,
				'total_amount'=>0,
                'total_fees'=>0,
				'details'=>[]
			],
			'rcp'=>[
				'enabled'=>false,
				'total_amount'=>0,
                'total_fees'=>0,
				'details'=>[]
			],
			'pj'=>[
				'enabled'=>false,
				'total_amount'=>0,
                'total_fees'=>0,
				'details'=>[]
			],
			'vhs'=>[
				'enabled'=>false,
				'total_amount'=>0,
                'total_fees'=>0,
				'details'=>[]
			],
			'caci'=>[
				'enabled'=>false,
				'total_amount'=>0,
				'total_fees'=>0,
				'details'=>[]
			],
			'asseris'=>[
				'enabled'=>false,
				'total_amount'=>0,
                'total_fees'=>0,
				'details'=>[]
			]
		];

		$renew_company = $renew_caci = false;

		if( $user->isLegalRepresentative() ){

            $company = $user->getCompany();

			if( !$company || !$company->getMemberId() )
				return false;

			$renew_company = $_ENV['MEMBERSHIP_RENEWAL_COMPANY']??false;

			$entity_id =  $company->getId();
			$column =  'company_id';
		}
		elseif( $user->isCommercialAgent() ){

            $contact = $user->getContact();

			if( !$contact )
				return false;

			$renew_caci = $_ENV['MEMBERSHIP_RENEWAL_CACI']??false;

			$entity_id =  $contact->getId();
			$column =  'contact_id';
		}
		else{

			if( !$entity ){

				return $status;
			}
			else{

				return $status[$entity];
			}
		}

		if( (!$entity || $entity == 'snpi') && $user->isLegalRepresentative() ){

			$qb->select(['amount','year'])->from('membership')
				->where($column,'=', $entity_id)
				->andWhere('status', '=', 1352) //accepted
				->andWhere('type', 'in', [3232,3233,3234]); //SNPI;Expert;SNPI+Expert

			if( $results = $this->eudonetConnector->execute($qb) ){

				if( count($results) ){

					$status['snpi']['enabled'] = true;

					if( $renew_company ){

						foreach ($results as $result){

							$result['amount'] = $this->formatFloat($result['amount']);

							if( $result['amount'] ){

								$status['snpi']['total_amount'] += $result['amount'];
								$status['snpi']['details'][] = $result;
							}
						}
					}
				}
			}
		}
		elseif( (!$entity || $entity == 'snpi') && $user->isCommercialAgent() ){

			$qb->select(['amount','year'])->from('membership')
				->where($column,'=', $entity_id)
				->andWhere('status', '=', 1352) //accepted
				->andWhere('type', 'in', [3231]); //CACI

			if( $results = $this->eudonetConnector->execute($qb) ){

				if( count($results) )
					$status['snpi']['enabled'] = true;
			}
		}
		else{

			$status['snpi']['enabled'] = true;
		}

		if( !$entity || $entity != 'snpi'){

            $now = new DateTime();

			$qb->select(['amount','category','invoice', 'entity', 'company_id'])->from('contract')
				->where($column,'=', $entity_id)
				->andWhere('status', 'in', ['valid','pending'])
				->andWhere('end_date', '>', $now->format('Y-m-d')); //validated


			if( $results = $this->eudonetConnector->execute($qb) ){

				foreach ($results as $result){

					$type = $renew = false;
					$result['fees'] = 0;

                    if( $column == 'contact_id'){

                        $qb->select(['price','product'])->from('contract_details')
                            ->where('contract','=', $result['id']); //validated

                        if( $details = $this->eudonetConnector->execute($qb) ){

                            foreach ($details as $detail){

                                if( $detail['product'] == '24' || $detail['product'] == '136')
                                    $result['fees'] += $this->formatFloat($detail['price']);
                            }
                        }
                    }

					if( $result['entity'] == 'VHS' ){

						$type = 'vhs';
						$renew = $renew_company;

						switch( $result['category'] ){

							case 'RCP Agence':

								$status['rcp']['enabled'] = true;
								break;
						}
					}
					elseif( $column == 'company_id'){

						switch( $result['category'] ){

							case 'PJ Agence':
							case 'PJ - Vie Privée':

							$status['pj']['enabled'] = true;

							case 'MRP':

								$type = 'asseris';
								$renew = $renew_company;
								break;
						}
					}
					elseif( $column == 'contact_id'){

						switch( $result['category'] ){

							case 'RCP CACI':

								$status['rcp']['enabled'] = true;
								$type = 'caci';
								$renew = $renew_caci;
								break;

							case 'PJ CACI':

								$status['pj']['enabled'] = true;
								$type = 'caci';
								$renew = $renew_caci;
								break;
						}
					}

					if( $type ){

						$status[$type]['enabled'] = true;

						if( $renew ){

							$result['amount'] = $this->formatFloat($result['amount']);

							if( $result['amount'] ){

								$status[$type]['total_amount'] += $result['amount'];
								$status[$type]['total_fees'] += $result['fees'];
								$status[$type]['details'][] = $result;
							}
						}
					}
				}
			}
		}

		if( !$entity )
			return $status;
		else
			return $status[$entity];
	}

	/**
	 * @param $value
	 * @param int $empty
	 * @return float|int
	 */
	protected function formatFloat($value, $empty=0 ){

		if( empty($value) )
			return $empty;

		if( is_string($value) )
			$value = str_replace(',','.', str_replace(' ','', $value));

		return floatval($value);
	}

	/**
	 * @param $entityType
	 * @param $entityId
	 * @return array|bool
	 * @throws Exception
	 */
	public function getAppendice($entityType, $entityId){

		$qb = $this->eudonetConnector->createQueryBuilder();

		$qb->select('*')
			->from('appendix')
			->where('entity_id', '=', $entityId)
			->andWhere('entity_type', '=', $qb->getTableId($entityType));

		$appendices = $this->eudonetConnector->execute($qb);

		return count($appendices)?$appendices[0]:false;
	}

	/**
	 * @param $search
	 * @param false $last_update
	 * @return array|bool
	 * @throws Exception
	 */
	public function getAppendices($search, $last_update=false){

        $exp = $this->eudonetConnector->createExpressionBuilder();
        $qb = $this->eudonetConnector->createQueryBuilder();

		$qb->select('*')
			->from('appendix', 'a');

        if( $search){

            if( is_array($search) && count($search) > 1 ){

                $qb->subWhere($exp->where('a.filename', 'like', $search[0])->orWhere('a.filename', 'like', $search[1]));
            }
            else{

                $qb->where('a.filename', 'like', $search);
            }
        }
        else
            $qb->where('a.filename', '!=', '');

		if( $last_update ){

            if( $last_update instanceof DateTime)
                $last_update = $last_update->format('Y-m-d H:i:s');

            $qb->andWhere('a.created_at', '>', $last_update);
        }

		$appendices = $this->eudonetConnector->execute($qb);

		if( !$appendices )
			return [];

		$contactRepository = $this->entityManager->getRepository(Contact::class);
		$companyRepository = $this->entityManager->getRepository(Company::class);

        $qb = $this->eudonetConnector->createQueryBuilder();

        foreach ($appendices as $key=>&$appendix){

			$appendix['entity_type'] = $qb->getTableFromNumber($appendix['entity_type']);

            preg_match('/([A-Z0-9_]+)_PM([0-9]+)_PP([0-9]+)[_|\.]/', $appendix['filename'], $output);

			if( count($output) == 4 ){

				$title = $output[1];

				$appendix['title'] = trim($title);
				$appendix['company_id'] = intval($output[2]);
				$appendix['contact_id'] = intval($output[3]);

				$size = strtolower($appendix['size']);

				if( strpos($size, 'k') )
					$appendix['size'] = intval(str_replace('k', '', str_replace(',', '.', $size)));
				elseif( strpos($size, 'm') )
					$appendix['size'] = round(floatval(str_replace('m', '', str_replace(',', '.', $size)))*1000);
				elseif( strpos($size, 'g') )
					$appendix['size'] = round(floatval(str_replace('g', '', str_replace(',', '.', $size)))*1000*1000);

				if( ($appendix['contact_id'] && !$contactRepository->find($appendix['contact_id'])) || ($appendix['company_id'] && !$companyRepository->find($appendix['company_id'])) )
					unset($appendices[$key]);

                if( $appendix['company_id'] && $appendix['title'] == 'AC_SOUS_PJ_ATTESTATION' )
                    unset($appendices[$key]);
            }
			else{
				unset($appendices[$key]);
			}
		}

		return $appendices;
	}

    /**
     * @param UserInterface $user
     * @param int $formationCourseId
     * @return array|bool
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     */
	public function createAgreement(UserInterface $user, $formationCourseId){

		$qb = $this->eudonetConnector->createQueryBuilder();

		$qb->insert('agreement')->setValues([
			'mode'=>'participant',
			'formation_course_id'=>$formationCourseId
		]);

		if( $user->isLegalRepresentative() ){

			/** @var Company $company */
			$company = $user->getCompany();

			$qb->setValue('company_id', $company->getId());

			if( $legalRepresentative = $company->getLegalRepresentative() )
				$qb->setValue('contact_id', $legalRepresentative->getId());
		}
		else{

			/** @var Contact $contact */
			$contact = $user->getContact();
			$qb->setValue('contact_id', $contact->getId());
		}

        $agreement_id = $this->eudonetConnector->execute($qb);

        $agreement = new Agreement($agreement_id);
        $this->clone($agreement);

        return $agreement_id;
	}

	/**
	 * @param $invoice_id
	 * @return array|bool
	 * @throws Exception
	 */
	public function getInvoice($invoice_id){

		if( $invoice = $this->eudonetConnector->select(['date','amount_et','vat','amount_ati'], 'invoice', $invoice_id) ){

			$invoice['amount_et'] = $this->formatFloat($invoice['amount_et']);
			$invoice['vat'] = $this->formatFloat($invoice['vat']);
			$invoice['amount_ati'] = $this->formatFloat($invoice['amount_ati']);
		}

		return $invoice;
	}

	/**
	 * @param $invoice_id
	 * @return string
	 * @throws Exception
	 */
	public function generateRefund($invoice_id){

        if( !$this->eudonetConnector->getValue('invoice', $invoice_id, 'trigger') ){

            $this->eudonetConnector->update('invoice', $invoice_id, ['reinvoicing'=>'generate_invoice']);
            $this->eudonetConnector->update('invoice', $invoice_id, ['trigger'=>'1']);

            return $this->eudonetConnector->getValue('invoice', $invoice_id, 'number');
        }

        return false;
	}

    /**
     * @param $invoice_id
     * @param $amount
     * @return string
     * @throws Exception
     */
	public function generatePartialRefund($invoice_id, $amount){

        if( !$this->eudonetConnector->getValue('invoice', $invoice_id, 'trigger') )
            return $this->eudonetConnector->getValue('invoice', $invoice_id, 'number');

        return false;
	}

	/**
	 * @return false|string
	 */
	public function now(){
		return date('Y/m/d H:i:s');
	}

	/**
	 * @param UserInterface $user
	 * @param $params
	 * @return array|bool
	 * @throws Exception
	 */
	public function createPayment(UserInterface $user, $params){

		$qb = $this->eudonetConnector->createQueryBuilder();

		$qb->insert('payment')->setValues(array_merge([
			'receipt_date'=>$this->now(),
			'due_date'=>$this->now(),
			'type'=>'payment',
			'situation'=>'registered',
			'method'=>'credit_card'
		], $params));

		if( $user->isLegalRepresentative() ){

			$company = $user->getCompany();
			$qb->setValue('company_id', $company->getId());

			if( $legalRepresentative = $company->getLegalRepresentative() )
				$qb->setValue('contact_id', $legalRepresentative->getId());
		}
		else{

			$qb->setValue('contact_id', $user->getContact()->getId());
		}

		return $this->eudonetConnector->execute($qb);
	}

	/**
	 * @param UserInterface $user
	 * @param $params
	 * @return array|bool
	 * @throws Exception
	 */
	public function createDeposit(UserInterface $user, $params){

		$qb = $this->eudonetConnector->createQueryBuilder();

		$qb->insert('deposit')->setValues(array_merge([
			'devise'=>'1', //euro
			'date'=>$this->now(),
			'method'=>'credit_card'
		], $params));

		if( $user->isLegalRepresentative() ){

			$company = $user->getCompany();
			$qb->setValue('company_id', $company->getId());

			if( $legalRepresentative = $company->getLegalRepresentative() )
				$qb->setValue('contact_id', $legalRepresentative->getId());
		}
		else{

			$contact = $user->getContact();

			$qb->setValue('contact_id', $contact->getId());
		}

		return $this->eudonetConnector->execute($qb);
	}


	/**
	 * @param $depositId
	 * @throws Exception
	 */
	public function finalizeDeposit( $depositId ){

		$this->eudonetConnector->update('deposit', $depositId, ['search'=>'1']);
		$this->eudonetConnector->update('deposit', $depositId, ['spread'=>'1']);
		$this->eudonetConnector->update('deposit', $depositId, ['validate'=>'1']);
	}


	/**
	 * @param $agreement_id
	 * @return bool|string
	 * @throws Exception
	 */
	public function createInvoice($agreement_id){

		$this->eudonetConnector->update('agreement', $agreement_id, ['generate_number'=>'1']);

		$agreementNumber = $this->eudonetConnector->getValue('agreement', $agreement_id, 'number');

		if( !$agreementNumber )
			throw new Exception('Unable to generate agreement number');

		$this->eudonetConnector->update('agreement', $agreement_id, ['compute_amounts'=>'1']);
		$this->eudonetConnector->update('agreement', $agreement_id, ['generate_invoice'=>'1']);

		return $this->eudonetConnector->getValue('agreement', $agreement_id, 'invoice_id');
	}


	/**
	 * @param Address $address
	 * @param Contact $contact
	 * @param Company|null $company
	 * @param bool $persist
	 * @return void
	 * @throws ExceptionInterface
	 */
	public function createAddress(Address &$address, Contact $contact, ?Company $company=null, $persist=true){

		if( $company && !$address->isHome() )
            $address->setCompany($company, true);

		if( !$company = $address->getCompany() ){

            $address->setSummary($contact->getFirstname().' '.$contact->getLastname().' - Adresse Personnelle');
            $address->setIsHome(true);
        }
		else{

            $address->setSummary($contact->getFirstname().' '.$contact->getLastname().' - '.$company->getName());
        }

		if( !$address->isHome() )
			$address->setPosition('Salariés et assimilés');

		if( $address->getIssuedAt() )
		    $address->setHasCertificate(true);

		if( is_null($address->isMain()) )
            $address->setIsMain($address->isRealEstateAgent() || $address->isOtherCollaborator() );

		$address->setContact($contact);

		$this->push($address, null, $persist);
    }


    /**
     * @param OrderDetail $orderDetail
     * @param $agreement_id
     * @param $participants_id
     * @param $entity
     * @return void
     * @throws ExceptionInterface
     */
	public function generateFormationInvoice(OrderDetail $orderDetail, $agreement_id, $participants_id, $entity){

        if( $invoice_id = $this->createInvoice($agreement_id) ){

            $this->eudonetConnector->update('invoice', $invoice_id, ['status'=>'ongoing']);
            $this->eudonetConnector->update('invoice', $invoice_id, ['payment_method'=>'credit_card','entity'=>$entity]);

            $this->eudonetConnector->update('agreement', $agreement_id, ['validate_invoice'=>'1']);

            $this->eudonetConnector->update('payment', $orderDetail->getPaymentId(), ['invoice_id'=>$invoice_id]);
        }
        else{

            $order = $orderDetail->getOrder();
            $this->mailer->sendAlert($_ENV['ALERT_EMAIL'], 'Une erreur est survenue pour la commande #'.$order->getId(), 'Eudonet : Unable to generate Invoice');
        }

		$this->generateFile(125, 'agreement', $agreement_id);
        $this->generateFiles(200, 'formation_participant', $participants_id);
	}

	/**
	 * @param int $contact_id
	 * @param int|null $address_id
	 * @param string $agreementNumber
	 * @param int $formationCourseId
	 * @return bool
	 * @throws Exception
	 */
	public function createParticipant($contact_id, $address_id, $agreementNumber, $formationCourseId){

		$qb = $this->eudonetConnector->createQueryBuilder();

		$qb->insert('formation_participant')->setValues([
			'contact_id'=>$contact_id,
			'registered'=>1,
			'agreement'=>$agreementNumber,
			'formation_course_id'=>$formationCourseId
		]);

		if( $address_id )
			$qb->setValue('address_id', $address_id);

		return $this->eudonetConnector->execute($qb);
	}

	/**
	 * @param Contact[] $contacts
	 * @param Company $company
	 * @param $agreementNumber
	 * @param int $formationCourseId
	 * @return array
	 * @throws Exception
	 * @throws ExceptionInterface
	 */
	public function insertParticipants($contacts, $company, $agreementNumber, $formationCourseId){

		$participantIds = [];

		/** @var FormationParticipantRepository $formationParticipantRepository */
		$formationParticipantRepository = $this->entityManager->getRepository(FormationParticipant::class);

		foreach ($contacts as $contact){

			if( $formationParticipant = $formationParticipantRepository->findOneUnexpired($contact, $formationCourseId) ){

				$participantIds[] = $formationParticipant->getId();
			}
			else{

                $address_id = null;

                if( $address = $contact->getAddress($company) )
                    $address_id = $address->getId();

				$participantId = $this->createParticipant($contact->getId(), $address_id, $agreementNumber, $formationCourseId);

				$formationParticipant = new FormationParticipant($participantId);
				$this->clone($formationParticipant);

				$participantIds[] = $participantId;
			}
		}

		return $participantIds;
	}

	/**
	 * @param $model_id
	 * @param $table
	 * @param $id
	 * @return string
	 * @throws Exception
	 * @throws ExceptionInterface
	 */
	public function generateFile($model_id, $table, $id){

		$qb = $this->eudonetConnector->createQueryBuilder();
		$table_id = $qb->getTableId($table);

		return $this->eudonetConnector->generateFile($model_id, $table_id, $id);
	}

	/**
	 * @param $table
	 * @param $entity_id
	 * @param $column
	 * @param $filename
	 * @return string
	 * @throws Exception
	 */
	public function uploadImage($table, $entity_id, $column, $filename){

		$qb = $this->eudonetConnector->createQueryBuilder();

		$table_id = $qb->getTableId($table);

		if( $field_id = $qb->getColumnId($table, $column) )
			return $this->eudonetConnector->uploadImage($table_id, $entity_id, $field_id, $filename);

		return false;
	}

    /**
     * @param string $table
     * @param int $entity_id
     * @param Contact|null $contact
     * @param Company|null $company
     * @param string $filepath
     * @param string $filename
     * @param string $ext
     * @return string
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     */
	public function uploadFile(string $table, int $entity_id, ?Contact $contact, ?Company $company, string $filepath, string $filename, $ext='pdf'){

		$qb = $this->eudonetConnector->createQueryBuilder();

		$table_id = $qb->getTableId($table);

		$t = microtime(true);
		$micro = sprintf("%06d", ($t - floor($t)) * 1000000);
		$d = new DateTime(date('y-m-d H:i:s.' . $micro, $t));
		$reference = substr($d->format("YmdHisu"), 0, 16);

        if( $ext )
            $filename = $filename.'_PM'.($company?$company->getId():0).'_PP'.($contact?$contact->getId():0).'_'.$reference.'.'.$ext;

		return $this->eudonetConnector->uploadFile($table_id, $entity_id, $filepath, $filename);
	}

	/**
	 * @param $model_id
	 * @param $table
	 * @param $ids
	 * @return array
	 * @throws Exception
	 * @throws ExceptionInterface
	 */
	public function generateFiles($model_id, $table, $ids){

		$files=[];

		foreach ($ids as $id)
			$files[]=$this->generateFile($model_id, $table, $id);

		return $files;
	}


	/**
	 * @param $entity
	 * @param string|null $table
	 * @param bool $persist
	 * @return array|bool
	 * @throws ExceptionInterface
	 * @throws Exception
	 */
	public function push(&$entity, ?string $table=null, $persist=true){

		if( !$entity )
			return false;

		$nameConverter = new CamelCaseToSnakeCaseNameConverter();

		if( !$table ){

			$table = explode('\\', get_class($entity));
			$table = end($table);
			$table = $nameConverter->normalize($table);
		}

		$classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $objectNormalizer = new ObjectNormalizer($classMetadataFactory, $nameConverter);
		$dateTimeNormalizer = new DateTimeNormalizer(['datetime_format' =>'Y/m/d H:i:s']);

		$serializer = new Serializer([$dateTimeNormalizer, $objectNormalizer]);

		$action = $entity->getId() ? 'update' : 'insert';

		$values = $serializer->normalize($entity, null, ['groups' => [$action]]);

        $values = array_filter($values, static function($var){
			return $var !== null;
		});

		$qb = $this->eudonetConnector->createQueryBuilder();


		if( $action == 'update' )
			$qb->update($table)->setValues($values)->on($entity->getId());
		else
			$qb->insert($table)->setValues($values);

		$id = $this->eudonetConnector->execute($qb);

		if( $action == 'insert' && method_exists($entity, 'setId') )
			$entity->setId($id);

		if( $persist ){

			$repository = $this->entityManager->getRepository(get_class($entity));

			if( $action == 'update' || !$repository->find($id) ){

				$this->entityManager->persist($entity);
				$this->entityManager->flush();
			}
		}

		return $id;
	}


	/**
	 * @param $entity
	 * @param ?string $table
	 * @return void
	 * @throws Exception
	 * @throws ExceptionInterface
	 */
	public function clone(&$entity, ?string $table=null){

		if( $data = $this->getData($entity, $table) ){

			$serializer = new Serializer([new ObjectNormalizer()]);
			$entity = $serializer->denormalize($data, get_class($entity), null);

			//todo: replace with abstract entity merge
			$this->entityManager->merge($entity);
			$this->entityManager->flush();

			$entity = $this->entityManager->getRepository(get_class($entity))->find($entity->getId());
			$this->entityManager->refresh($entity);
		}
	}


    /**
     * @param AbstractEudoEntity $entity
     * @param string|null $table
     * @param bool $persist
     * @return void
     * @throws Exception
     */
	public function pull(AbstractEudoEntity &$entity, ?string $table=null, $persist=true){

		if( $data = $this->getData($entity, $table) ){

			$entity->merge($data);

			if( $persist ){

				$this->entityManager->persist($entity);
				$this->entityManager->flush();
			}
		}
	}


	/**
	 * @param $entity
	 * @param string|null $table
	 * @return array|bool
	 * @throws Exception
	 */
	public function getData($entity, ?string $table=null){

		if( !$entity || !$entity->getId() )
			return false;

		if( !$table ){

			$table = explode('\\', get_class($entity));
			$table = end($table);

			$converter = new CamelCaseToSnakeCaseNameConverter ();
			$table = $converter->normalize($table);
		}

		$qb = $this->eudonetConnector->createQueryBuilder();

		$qb->select('*')->from($table)->on($entity->getId());

		return $this->eudonetConnector->execute($qb);
	}

	/**
	 * @param Contact $contact
	 * @return array|bool
	 * @throws Exception
	 */
	public function getContracts(Contact $contact){

		$qb = $this->eudonetConnector->createQueryBuilder();

		$qb->select('*')->from('contract')
			->where('contact', '=', $contact->getId())
			->andWhere('status', '=', '1647') // Validé
			->andWhere('amount', '=', '0');

		return $this->eudonetConnector->execute($qb);
	}
}
