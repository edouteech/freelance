<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\Type\AppendixReadType;
use App\Repository\AppendixRepository;
use App\Repository\DownloadRepository;
use App\Repository\OptionRepository;
use App\Repository\ContactMetadataRepository;
use App\Repository\UserRepository;
use App\Service\EudonetAction;
use App\Service\ServicesAction;
use Cocur\Slugify\Slugify;
use DateTime;
use Doctrine\ORM\ORMException;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;


/**
 * Appendix Controller
 *
 * @SWG\Tag(name="Appendix")
 *
 * @IsGranted("ROLE_CLIENT")
 * @Security(name="Authorization")
*/
class AppendixController extends AbstractController
{
	/**
	 * Refresh appendices list
	 *
	 * @Route("/appendix", methods={"POST"})
	 *
	 * @SWG\Response(response=200, description="List updated")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param OptionRepository $optionRepository
	 * @param UserRepository $userRepository
	 * @param EudonetAction $eudonetAction
	 * @param AppendixRepository $appendixRepository
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 * @throws Exception
	 */
	public function refresh(OptionRepository $optionRepository, UserRepository $userRepository, EudonetAction $eudonetAction, AppendixRepository $appendixRepository)
	{
        /** @var User $user */
        $user = $this->getUser();

		set_time_limit(60);

		$last_sync = $user->getLastSyncAt();

		if( $optionRepository->get('eudo_call_remain') < 400 )
			return $this->respondError('Documents not synced', 509);

        $now = new DateTime();
        $minuteago = new DateTime('now - 1 minute');

        if($last_sync && $last_sync->getTimestamp() >= $minuteago->getTimestamp())
             return $this->respondOK(['flooding'=>true]);

		if( $user->isLegalRepresentative() || $user->isCollaborator() ){

            if(!$company = $user->getCompany() )
				return $this->respondNotFound('Company not found');

			$search = '_PM'.$company->getId().'_';
		}
		else{

			if( $user->isRegistering() )
				return $this->respondOK(['count'=>0]);

            if( !$contact = $user->getContact() )
				return $this->respondNotFound('Contact not found');

			$search = ['_PP'.$contact->getId().'_', '_PP'.$contact->getId().'.'];
        }

        //$last_updated = new DateTime("now - 5 minutes");

		$appendices = $eudonetAction->getAppendices($search, $last_sync);
		$appendixRepository->bulkInserts($appendices, 1, false);

        $user->setLastSyncAt($now);
		$userRepository->save($user);

		return $this->respondOK([
			'count'=>count($appendices)
		]);
	}


	/**
	 * Refresh certificates list
	 *
	 * @Route("/appendix/certificates", methods={"POST"})
	 *
	 * @SWG\Response(response=200, description="Certificates updated")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param ServicesAction $servicesAction
	 * @param EudonetAction $eudonetAction
	 * @param AppendixRepository $appendixRepository
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 * @throws Exception
	 */
	public function refreshCertificates(ServicesAction $servicesAction, EudonetAction $eudonetAction, AppendixRepository $appendixRepository)
	{
		$user = $this->getUser();

		set_time_limit(60);

        $last_updated = new DateTime("now - 5 minutes");

        $appendices = [];

        if( !$user->isCommercialAgent() || !$contact = $user->getContact() )
            return $this->respondNotFound('Contact not found');

        $search = '_PP'.$contact->getId().'_';

        if( $certificates = $servicesAction->generateCertificates($user) ){

            $appendices = $eudonetAction->getAppendices($search, $last_updated);
            $appendixRepository->bulkInserts($appendices, 1, false);
        }

        $appendixRepository->fixPublic($user);

		return $this->respondOK([
			'count'=>count($appendices),
			'certificates'=>$certificates
		]);
	}

	/**
	 * Download appendices
	 *
	 * @Route("/appendix/download", methods={"GET"})
	 *
	 * @SWG\Parameter(name="ids[]", in="query", type="array", @SWG\Items(type="integer"), description="array of appendices id")
	 *
	 * @SWG\Response(response=200, description="Redirect to document")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=404, description="Appendix not found")
	 *
	 * @param UserInterface $user
	 * @param Request $request
	 * @param AppendixRepository $appendixRepository
	 * @param ContactMetadataRepository $metadataRepository
	 * @param DownloadRepository $downloadRepository
	 *
	 * @return Response
	 * @throws ExceptionInterface
	 * @throws ORMException
	 * @throws Exception
	 */
	public function downloadAll(UserInterface $user, Request $request, AppendixRepository $appendixRepository, ContactMetadataRepository $metadataRepository, DownloadRepository $downloadRepository)
	{
		$files = [];
		$slugify = new Slugify();

		if(!$ids = $request->get('ids'))
			return $this->respondNotFound('ids is required');

		if( !$appendices = $appendixRepository->findByUser($user, $ids) )
			return $this->respondNotFound('Unable to find appendices');

		foreach ($appendices as $appendix) {

			if( $url = $appendix->getLink() ){

				if( $contact = $user->getContact() ) {

					$criteria = ['state' => 'read', 'contact' => $contact, 'entityId' => $appendix->getId(), 'type' => 'appendix'];
					$metadataRepository->save($criteria);
				}

				$filename = $appendix->getCreatedAt()->format('Y') . '-' . $appendix->getFilename();
				$files[$filename] = $this->storeRemoteFile($url, $appendix->getFilename(true), $appendix->getCreatedAt());
			}
		}

		if( !count($files))
			return $this->respondNotFound('Unable to find appendices asset');

		$path = $this->createZip($files, 'appendices-' . $slugify->slugify($user->getName()) . '.zip');
		$download = $downloadRepository->create($request, $path, true);

		return $this->respondOk($downloadRepository->hydrate($download));
	}


	/**
	 * Download appendix
	 *
	 * @Route("/appendix/{id}/download", methods={"GET"}, requirements={"id"="\d+"})
	 *
	 * @SWG\Response(response=200, description="Redirect to document")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=404, description="Appendix not found")
	 *
	 * @param Request $request
	 * @param AppendixRepository $appendixRepository
	 * @param ContactMetadataRepository $metadataRepository
	 * @param DownloadRepository $downloadRepository
	 * @param int $id
	 *
	 * @return Response
	 * @throws ExceptionInterface
	 * @throws ORMException
	 * @throws Exception
	 */
	public function download(Request $request, AppendixRepository $appendixRepository, ContactMetadataRepository $metadataRepository, DownloadRepository $downloadRepository, $id)
	{
		$user = $this->getUser();

		if( !$appendix = $appendixRepository->findOneByUser($user, $id) )
			return $this->respondNotFound('Unable to find appendix');

		if( !$url = $appendix->getLink() )
			return $this->respondNotFound('Unable to find appendix asset');

		if( $contact = $user->getContact() ) {

			$criteria = ['state' => 'read', 'contact' => $contact, 'entityId' => $appendix->getId(), 'type' => 'appendix'];
			$metadataRepository->save($criteria);
		}

		$path = $this->storeRemoteFile($url, $appendix->getFilename(true), $appendix->getCreatedAt());


        //eudonet respond HTML 200 OK even if the file does not exists
        if( mime_content_type($path) == 'text/html' ){

            $content = file_get_contents($path);

            if( strpos($content, "La pièce jointe n'est plus disponible sur le serveur") ){

                $appendix->setPublic(false);
                $appendixRepository->save($appendix);

                return $this->respondNotFound('File not found');
            }
        }

        $download = $downloadRepository->create($request, $path, true);

		return $this->respondOK($downloadRepository->hydrate($download));
	}


	/**
	 * Get one appendix
	 *
	 * @Route("/appendix/{id}", methods={"GET"}, requirements={"id"="\d+"})
	 *
	 * @SWG\Response(response=200, description="Redirect to document")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=404, description="Appendix not found")
	 *
	 * @param AppendixRepository $appendixRepository
	 * @param int $id
	 *
	 * @return Response
	 */
	public function find(AppendixRepository $appendixRepository, $id)
	{
		$user = $this->getUser();

		if( !$appendix = $appendixRepository->findOneByUser($user, $id) )
			return $this->respondNotFound('Unable to find appendix');

		return $this->respondOK($appendixRepository->hydrate($appendix, $appendixRepository::$HYDRATE_FULL));
	}


	/**
	 * Get appendices list
	 *
	 * @Route("/appendix", methods={"GET"})
	 *
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of appendices per page", default=10, maximum=100, minimum=2)
	 * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
	 * @SWG\Parameter(name="filter", in="query", type="string", description="Filter result", enum={"favorite"})
	 * @SWG\Parameter(name="sort", in="query", type="string", description="Order result", default="createdAt", enum={"popular","createdAt","category"})
	 * @SWG\Parameter(name="order", in="query", type="string", description="Order result", default="desc", enum={"asc", "desc"})
	 * @SWG\Parameter(name="search", in="query", type="string", description="Query")
	 * @SWG\Parameter(name="category[]", in="query", type="string", description="Term", enum={"certificate", "invoice", "convention", "fund-raising"})
	 *
	 * @SWG\Response(response=200, description="Return a list of appendices")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param AppendixRepository $appendixRepository
	 * @return JsonResponse
	 */
	public function list(Request $request, AppendixRepository $appendixRepository)
	{
		$user = $this->getUser();

		list($limit, $offset) = $this->getPagination($request);

		$form = $this->submitForm(AppendixReadType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$criteria = $form->getData();

		$appendices = $appendixRepository->query($user, $limit, $offset, $criteria);

		return $this->respondOK([
			'items'=>$appendixRepository->hydrateAll($appendices),
			'count'=>count($appendices),
			'limit'=>$limit,
			'offset'=>$offset
		]);
	}
}
