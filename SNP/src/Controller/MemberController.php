<?php

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\User;
use App\Form\Type\ContactReadType;
use App\Repository\AddressRepository;
use App\Repository\AppendixRepository;
use App\Repository\CompanyRepository;
use App\Repository\ContactRepository;
use App\Service\SnpiConnector;
use Symfony\Component\HttpFoundation\Request;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Member Controller
 *
 * @SWG\Tag(name="Members")
 *
 */
class MemberController extends AbstractController
{
    /**
     * Find member by id number
     *
     * @Route("/member/{memberId}", methods={"GET"})
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @SWG\Response(response=200, description="Returns updated contact")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param $memberId
     * @param SnpiConnector $snpiConnector
     * @param ContactRepository $contactRepository
     * @param CompanyRepository $companyRepository
     * @param AppendixRepository $appendixRepository
     * @return JsonResponse
     *
     * @throws ExceptionInterface
     */
    public function search($memberId, SnpiConnector $snpiConnector, ContactRepository $contactRepository, CompanyRepository $companyRepository, AppendixRepository $appendixRepository)
    {
        if( $contact = $contactRepository->findOneBy(['memberId'=>$memberId, 'status'=>'member']) ){

            $stock = $snpiConnector->getStock(['num_adherent'=>$memberId]);

            $appendices = $appendixRepository->findBy(['contact' => $contact->getId()]);

            return $this->respondOK([
                'type'=>'contact',
                'appendices'=>$appendixRepository->hydrateAll($appendices),
                'stock'=>$stock,
                'data'=>$contactRepository->hydrate($contact, $contactRepository::$HYDRATE_FULL)
            ]);
        }
        elseif( $company = $companyRepository->findOneBy(['memberId'=>$memberId, 'status'=>'member'])){

            $stock = $snpiConnector->getStock(['num_adherent'=>$memberId]);
            $appendices = $appendixRepository->findBy(['company' => $company->getId()]);

            return $this->respondOK([
                'type'=>'company',
                'appendices'=>$appendixRepository->hydrateAll($appendices),
                'stock'=>$stock,
                'data'=>$companyRepository->hydrate($company, $companyRepository::$HYDRATE_FULL)
            ]);
        }

        return $this->respondNotFound('Contact or company not found');
    }
}
