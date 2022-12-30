<?php

namespace App\Controller\FormationCourse;

use App\Controller\AbstractController;
use App\Repository\AppendixRepository;
use App\Repository\CompanyRepository;
use App\Repository\ContactRepository;
use App\Repository\DownloadRepository;
use App\Repository\ExternalFormationRepository;
use App\Repository\FormationCourseRepository;
use App\Repository\FormationParticipantRepository;
use App\Repository\UserRepository;
use Cocur\Slugify\Slugify;
use DateTime;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;


/**
 * Formation Course Contact Controller
 *
 * @SWG\Tag(name="Formations Course Contact")

 * @Security(name="Authorization")
*/
class ContactController extends AbstractController
{
	/**
	 * Get contact formations report
	 *
	 * @IsGranted("ROLE_CLIENT")
	 *
	 * @Route("/formation/course/contact/{contact_id}/report", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Returns a list of formations")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param $contact_id
	 * @param ExternalFormationRepository $externalFormationRepository
	 * @param FormationParticipantRepository $formationParticipantRepository
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param AppendixRepository $appendixRepository
	 * @param CompanyRepository $companyRepository
	 * @return JsonResponse
	 */
	public function getContactReport($contact_id, ExternalFormationRepository $externalFormationRepository, FormationParticipantRepository $formationParticipantRepository, FormationCourseRepository $formationCourseRepository, AppendixRepository $appendixRepository, CompanyRepository $companyRepository)
	{
		$user = $this->getUser();

		if( $user->isLegalRepresentative() ){

			$company = $user->getCompany();

			if( !$contact = $companyRepository->getContact($company, $contact_id) )
				return $this->respondNotFound('Contact not found');

			if( !$businessCard = $company->getBusinessCard() )
				return $this->respondNotFound('Business card not found');

			$startAt = $businessCard->getIssuedAt();
		}
		else{

			$company = null;
			$contact = $user->getContact();

			if( $contact->getId() != $contact_id )
				return $this->respondNotFound('Contact not found');

			$startAt = new DateTime('3 years ago');
			$startAt->modify('1 month ago');
		}

		$senority = $contact->getSeniority($company);

		$report = [
			'valid'=>$user->isLegalRepresentative() ? $senority!==false : true,
			'senority'=>$senority,
			'quota'=>$contact->getFormationsQuota($senority),
			'completed'=>0,
			'completedEthics'=>0,
			'completedDiscrimination'=>0,
			'formations'=>[],
			'list'=>[]
		];

		// get formations
		$formationParticipants = $formationParticipantRepository->getLastFormations([$contact], $startAt);
		foreach ($formationParticipants as $formationParticipant){

			$formationCourse = $formationParticipant->getFormationCourse();
			$formation = $formationCourse->getFormation();

			if( in_array($formationCourse->getStatus(), ['completed', 'confirmed', 'suspended']) ){

				$report['completed'] += $formation->getHours();
				$report['completedEthics'] += $formation->getHoursEthics();
				$report['completedDiscrimination'] += $formation->getHoursDiscrimination();

				$report['formations'][] = ['formationCourse', $formationCourse, $formationParticipant];
			}
		}

		// get external formations
		$externalFormations = $externalFormationRepository->getLastFormations([$contact], $startAt);
		foreach ($externalFormations as $externalFormation){

			$report['completed'] += $externalFormation->getHours();
			$report['completedEthics'] += $externalFormation->getHoursEthics();
			$report['completedDiscrimination'] += $externalFormation->getHoursDiscrimination();

			$report['formations'][] = ['externalFormation', $externalFormation];
		}

		// order merged formations by date
		usort($report['formations'], function ($a, $b) {
			return $a[1]->getStartAt() < $b[1]->getStartAt();
		});

		// hydrate list
		foreach ($report['formations'] as $formation){

			if( $formation[0] == 'formationCourse' ){

				$formationReport = $formationCourseRepository->hydrate($formation[1]);
				$appendix = $formationParticipantRepository->findAppendix($formation[2]);
				$formationReport['appendix'] = $appendixRepository->hydrate($appendix);

				$report['list'][] = $formationReport;
			}
			else{

				$report['list'][] = $externalFormationRepository->hydrate($formation[1]);
			}

			unset($report['formations']);
		}

		return $this->respondOK($report);
	}


    /**
     * Download all contact certificates
     *
     * @Route("/formation/course/contact/{id}/download", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @IsGranted("ROLE_CLIENT")
     *
     * @SWG\Response(response=200, description="Redirect to zip")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Appendix not found")
     *
     * @param Request $request
     * @param DownloadRepository $downloadRepository
     * @param ExternalFormationRepository $externalFormationRepository
     * @param ContactRepository $contactRepository
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param UserRepository $userRepository
     * @param int $id
     *
     * @return JsonResponse|Response
     * @throws Exception
     * @throws ExceptionInterface
     */
	public function downloadAll(Request $request, DownloadRepository $downloadRepository, ExternalFormationRepository $externalFormationRepository, ContactRepository $contactRepository, FormationParticipantRepository $formationParticipantRepository, UserRepository $userRepository, $id)
	{
		$user = $this->getUser();

		if( !$contact = $contactRepository->find($id) )
			return $this->respondNotFound('Unable to find contact');

		if( !$userRepository->hasRights($user, $contact) )
			return $this->respondError('You have no right on this contact');

		$files = [];
		$slugify = new Slugify();

		$externalFormations = $externalFormationRepository->findBy(['contact'=>$contact]);
		foreach ($externalFormations as $externalFormation){

			if( $certificate = $externalFormation->getCertificate() ){

				$ext = pathinfo($certificate, PATHINFO_EXTENSION);
				$filename = $externalFormation->getEndAt()->format('Y').'-'.$slugify->slugify($externalFormation->getTitle()).'.'.$ext;

				$files[$filename] = $this->getPath('certificate_directory').'/'.$certificate;
			}
		}

		$formationParticipants = $formationParticipantRepository->findAllByContacts($contact, ['present'=>1]);

		foreach ($formationParticipants as $formationParticipant){

			if( $appendix = $formationParticipantRepository->findAppendix($formationParticipant) ){

				if( $url = $appendix->getLink() ){

					$filename = $appendix->getCreatedAt()->format('Y').'-'.$appendix->getFilename();

					$files[$filename] = $this->storeRemoteFile($url, false, $appendix->getCreatedAt(), '/export/tmp');
				}
			}
		}

		$path = $this->createZip($files, 'attestations-'.$slugify->slugify($contact->getLastname()).'.zip');

        $download = $downloadRepository->create($request, $path);

        return $this->respondOk($downloadRepository->hydrate($download));
	}
}
