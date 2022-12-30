<?php

namespace App\Controller;

use App\Entity\ExternalFormation;
use App\Form\Type\ExternalFormationType;
use App\Repository\DownloadRepository;
use App\Repository\ExternalFormationRepository;
use App\Repository\UserRepository;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;


/**
 * External Formation Controller
 *
 * @SWG\Tag(name="Formations course external")

 * @IsGranted("ROLE_CLIENT")
 *
 * @Security(name="Authorization")
*/
class ExternalFormationController extends AbstractController
{
	/**
	 * Add external formation course
	 *
	 * @Route("/formation/course/external", methods={"POST"})
	 *
	 * @SWG\Parameter( name="contact", in="body", required=true, description="Contact information", @SWG\Schema( type="object",
	 *     @SWG\Property(property="title", type="string"),
	 *     @SWG\Property(property="address", type="string"),
	 *     @SWG\Property(property="hours", type="number"),
	 *     @SWG\Property(property="hoursEthics", type="number"),
	 *     @SWG\Property(property="hoursDiscrimination", type="number"),
	 *     @SWG\Property(property="startAt", type="string"),
	 *     @SWG\Property(property="contact", type="integer"),
	 *     @SWG\Property(property="endAt", type="string"),
	 *     @SWG\Property(property="certificate", type="string", format="binary"),
	 *     @SWG\Property(property="format", type="string", enum={"instructor-led", "in-house", "e-learning"}),
	 * ))
	 *
	 * @SWG\Response(response=201, description="Formation added")
	 * @SWG\Response(response=404, description="Contact not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param ExternalFormationRepository $externalFormationRepository
	 * @param UserRepository $userRepository
	 *
	 * @return JsonResponse
	 *
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function add(Request $request, ExternalFormationRepository $externalFormationRepository, UserRepository $userRepository)
	{
		$user = $this->getUser();

		$externalFormation = new ExternalFormation();

		$form = $this->submitForm(ExternalFormationType::class, $request, $externalFormation);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$contact = $externalFormation->getContact();

		if( !$userRepository->hasRights($user, $contact) )
			return $this->respondError('You have no right on this contact');

		if( $certificate = $form->get('certificate')->getData() ){

			[$directory, $filename] = $this->moveUploadedFile($certificate, 'certificate_directory.'.$contact->getId());
			$externalFormation->setCertificate($filename);
		}

		$externalFormationRepository->save($externalFormation);

		return $this->respondCreated();
	}


    /**
     * Download certificate
     *
     * @Route("/formation/course/external/{id}/download", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @SWG\Response(response=200, description="Redirect to document")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Appendix not found")
     *
     * @param Request $request
     * @param ExternalFormationRepository $externalFormationRepository
     * @param DownloadRepository $downloadRepository
     * @param UserRepository $userRepository
     * @param int $id
     *
     * @return JsonResponse|Response
     * @throws ExceptionInterface
     * @throws ORMException
     */
	public function download(Request $request, ExternalFormationRepository $externalFormationRepository, DownloadRepository $downloadRepository, UserRepository $userRepository, $id)
	{
		$user = $this->getUser();

		if( !$externalFormation = $externalFormationRepository->find($id) )
			return $this->respondNotFound('Unable to find formation');

		if( !$userRepository->hasRights($user, $externalFormation->getContact()) )
			return $this->respondError('You have no right on this contact');

		if( $certificate = $externalFormation->getCertificate() ){

			$slugify = new Slugify();
			$ext = pathinfo($certificate, PATHINFO_EXTENSION);
			$filename = $slugify->slugify($externalFormation->getTitle()).'.'.$ext;
			$path = $this->getPath('certificate_directory').'/'.$certificate;

            $download = $downloadRepository->create($request, $path, false, $filename);

            return $this->respondOK($downloadRepository->hydrate($download));
		}

		return $this->respondNotFound('Unable to find document asset');
	}


	/**
	 * Delete external formation course
	 *
	 * @Route("/formation/course/external/{id}/delete", methods={"POST"})
	 *
	 * @SWG\Response(response=200, description="Formation deleted")
	 * @SWG\Response(response=404, description="Formation not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param $id
	 * @param ExternalFormationRepository $externalFormationRepository
	 *
	 * @param UserRepository $userRepository
	 * @return JsonResponse
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function delete($id, ExternalFormationRepository $externalFormationRepository, UserRepository $userRepository)
	{
		$user = $this->getUser();

		if( !$externalFormation = $externalFormationRepository->find($id) )
			return $this->respondNotFound();

		if( !$userRepository->hasRights($user, $externalFormation->getContact()) )
			return $this->respondError('You have no right on this contact');

		$this->deleteUploadedFile('certificate_directory', $externalFormation->getCertificate());

		$externalFormationRepository->delete($externalFormation);

		return $this->respondOK();
	}
}
