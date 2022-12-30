<?php

namespace App\Controller;

use App\Entity\Request;
use App\Form\Type\RequestType;
use App\Service\EudonetAction;
use App\Service\Mailer;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;


/**
 * Request Controller
 *
 * @SWG\Tag(name="Misc")
 *
 * @IsGranted("ROLE_CLIENT")
 *
 * @Security(name="Authorization")
 *
 */
class RequestController extends AbstractController
{
	/**
	 * Send request
	 *
	 * @Route("/request", methods={"POST"})
	 *
	 * @SWG\Parameter( name="user", in="body", required=true, description="Request", @SWG\Schema( type="object",
	 *     @SWG\Property(property="firstname", type="string"),
	 *     @SWG\Property(property="lastname", type="string"),
	 *     @SWG\Property(property="civility", type="string", enum={"Monsieur", "Madame"}),
	 *     @SWG\Property(property="email", type="string"),
	 *     @SWG\Property(property="message", type="string"),
	 *     @SWG\Property(property="subject", type="string"),
	 *     @SWG\Property(property="type", type="string", enum={"activation", "contact"}),
	 *     @SWG\Property(property="recipient", type="string", enum={"juridique_social", "administratif_snpi", "administratif_vhs", "administratif_asseris", "formation", "sinistres_vhs", "sinistres_asseris", "communication", "syndicale", "technique"}),
	 * ))
	 *
	 * @SWG\Response(response=200, description="Return ok")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param HttpRequest $httpRequest
	 * @param EudonetAction $eudonet
	 * @param Mailer $mailer
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws Exception
	 */
	public function send(HttpRequest $httpRequest, EudonetAction $eudonet, Mailer $mailer)
	{
		//todo: captcha

		$user = $this->getUser();

		$request = new Request();

		$form = $this->submitForm(RequestType::class, $httpRequest, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		if( $request->getType() == 'activation' ){

			$request->setTitle($_ENV['ACTIVATION_SUBJECT']);
			$request->setMemberId($user->getMemberId());

			$body = $mailer->createBodyMail('activation/generic.html.twig', $request);
			$mailer->sendMessage($_ENV['ACTIVATION_TO'], $_ENV['ACTIVATION_SUBJECT'], $body, $request->getEmail());
		}
		else{

			if( $contact = $user->getContact() )
				$request->setContact($contact);

			if( $company = $user->getCompany() )
				$request->setCompany($company);

			$eudonet->push($request, null, false);
		}

		return $this->respondOK();
	}
}
