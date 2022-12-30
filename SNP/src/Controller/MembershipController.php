<?php

namespace App\Controller;

use App\Entity\Membership;
use Symfony\Component\HttpFoundation\Request;
use App\Form\Type\MembershipType;
use App\Service\EudonetAction;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Membership Controller
 *
 * @SWG\Tag(name="Membership")
 *
 */
class MembershipController extends AbstractController
{
    /**
     * Get current user membership
     *
     * @Route("/membership", methods={"GET"})
     *
     * @IsGranted("ROLE_CLIENT")
     * @Security(name="Authorization")
     *
     * @SWG\Response(response=200, description="Return current user")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param EudonetAction $eudonet
     * @return JsonResponse
     * @throws Exception
     */
    public function getMembership(EudonetAction $eudonet)
    {
        $user = $this->getUser();

        return $this->respondOK($eudonet->getMembershipStatus($user));
    }


    /**
     * Request company membership
     *
     * @Route("/membership", methods={"POST"})
     *
     * @SWG\Response(response=200, description="Return ok")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     * @param EudonetAction $eudonet
     * @return JsonResponse
     * @throws ExceptionInterface
     */
    public function askMembership( Request $request, EudonetAction $eudonet)
    {
        $membership = new Membership();

        $form = $this->submitForm(MembershipType::class, $request, $membership);

        if( !$form->isValid() )
            return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

        $eudonet->push($membership, 'application', false);

        return $this->respondOK();
    }
}
