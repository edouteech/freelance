<?php

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\Entity\ContactMetadata;
use App\Form\Type\MetadataType;
use App\Repository\AppendixRepository;
use App\Repository\ResourceRepository;
use App\Repository\ContactMetadataRepository;
use Doctrine\ORM\ORMException;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Metadata Controller
 *
 * @SWG\Tag(name="User metadata")
 *
 * @Security(name="Authorization")
 * @IsGranted("ROLE_CLIENT")
 */
class MetadataController extends AbstractController
{
	/**
	 * Get user metadata
	 *
	 * @Route("/user/metadata", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Metadata")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param ContactMetadataRepository $contactMetadataRepository
	 * @return JsonResponse
	 * @throws ORMException
	 * @throws ExceptionInterface
	 */
	public function findAll(Request $request, ContactMetadataRepository $contactMetadataRepository)
	{
        $user = $this->getUser();

        if(!$contact = $user->getContact() )
            return $this->respondOK([]);

        $metadata = $contactMetadataRepository->hydrateAll($contact->getMetadata());

        return $this->respondOK($metadata);
    }


	/**
	 * Toggle user metadata
	 *
	 * @Route("/user/metadata", methods={"POST"})
	 *
	 * @SWG\Parameter(name="metadata", in="body", required=true, description="User metadata", @SWG\Schema( type="object",
	 *     @SWG\Property(property="state", type="string", enum={"read", "pinned", "favorite"}),
	 *     @SWG\Property(property="entityId", type="integer", example="1"),
	 *     @SWG\Property(property="type", type="string", enum={"resource", "appendix", "tour"})
	 * ))
	 *
	 * @SWG\Response(response=201, description="Metadata created")
	 * @SWG\Response(response=200, description="Metadata removed")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 *
	 * @param Request $request
	 * @param ContactMetadataRepository $contactMetadataRepository
	 * @param AppendixRepository $appendixRepository
	 * @param ResourceRepository $resourceRepository
	 * @return JsonResponse
	 * @throws ORMException
	 * @throws ExceptionInterface
	 */
	public function toggleMetadata(Request $request, ContactMetadataRepository $contactMetadataRepository, AppendixRepository $appendixRepository, ResourceRepository $resourceRepository)
	{
		$user = $this->getUser();

		if(!$contact = $user->getContact() )
			return $this->respondOK();

		$newMetadata = new ContactMetadata();

		$form = $this->submitForm(MetadataType::class, $request, $newMetadata);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

        if( $newMetadata->getType() == 'tour' && empty($newMetadata->getEntityId()) ){

            $existingMetadatas = $contactMetadataRepository->findBy([
                'state'=>$newMetadata->getState(),
                'type'=>$newMetadata->getType(),
                'contact'=>$contact
            ]);

            $contactMetadataRepository->deleteAll($existingMetadatas);

            return $this->respondOk();
        }

        if( empty($newMetadata->getEntityId()) )
            return $this->respondBadRequest('Entity is empty');

        $existingMetadata = $contactMetadataRepository->findOneBy([
			'state'=>$newMetadata->getState(),
			'entityId'=>$newMetadata->getEntityId(),
			'type'=>$newMetadata->getType(),
			'contact'=>$contact
		]);

		if( $existingMetadata ){

			$contactMetadataRepository->delete($existingMetadata);
			return $this->respondOk();
		}

		if( $newMetadata->getType() == 'resource' && !$resourceRepository->find($newMetadata->getentityId()) )
			return $this->respondNotFound("Resource not found.");
		elseif( $newMetadata->getType() == 'appendix' && !$appendixRepository->findOneByUser($user, $newMetadata->getentityId()) )
			return $this->respondNotFound("Appendix not found.");

		$newMetadata->setContact($contact);

		$contactMetadataRepository->save($newMetadata);

		return $this->respondCreated();
	}
}
