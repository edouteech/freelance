<?php

namespace App\Controller;

use App\Form\Type\ShareType;
use App\Repository\DocumentRepository;
use App\Repository\FormationCourseRepository;
use App\Repository\NewsRepository;
use App\Repository\PageRepository;
use App\Service\Mailer;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Nelmio\ApiDocBundle\Annotation\Security;

/**
 * Share Controller
 *
 * @IsGranted("ROLE_CLIENT")
 *
 * @SWG\Tag(name="Misc")
 * @Security(name="Authorization")
 */
class ShareController extends AbstractController
{

	/**
	 * Share resource
	 *
	 * @Route("/share", methods={"POST"})
	 *
	 * @SWG\Parameter( name="contact", in="body", required=true, description="Resource information", @SWG\Schema( type="object",
	 *     @SWG\Property(property="subject", type="string"),
	 *     @SWG\Property(property="message", type="string"),
	 *     @SWG\Property(property="type", type="string", enum={"document", "page", "formation", "news"}),
	 *     @SWG\Property(property="id", type="integer"),
	 *     @SWG\Property(property="emails", type="array", @SWG\Items(type="string"))
	 * ))
	 *
	 * @SWG\Response(response=200, description="Email sent")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=404, description="Resource not found")
	 *
	 * @param DocumentRepository $documentRepository
	 * @param NewsRepository $newsRepository
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param PageRepository $pageRepository
	 * @param Request $request
	 * @param Mailer $mailer
	 *
	 * @return JsonResponse
	 * @throws Exception
	 * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
	 */
	public function share(DocumentRepository $documentRepository, NewsRepository $newsRepository, FormationCourseRepository $formationCourseRepository, PageRepository $pageRepository, Request $request, Mailer $mailer)
	{
		$user = $this->getUser();

		$form = $this->submitForm(ShareType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$criteria = $form->getData();

		//todo: generic way to get front url

		switch ($criteria['type']){

			case 'document':

				if( !$document = $documentRepository->findOneByUserRole($criteria['id'], $user) )
					return $this->respondNotFound('Document not found');

				if( !$document->getTerms('category', null, 0) )
					return $this->respondNotFound('Document category not found');

				$title = $document->getTitle();
                $link = $document->getDashboardLink();
				$type = 'le document';

				break;

			case 'page':

				if( !$page = $pageRepository->findOneByUser($criteria['id'], $user) )
					return $this->respondNotFound('Page not found');

				$title = $page->getTitle();
				$link = $page->getDashboardLink();
				$type = 'la page';

				break;

			case 'news':

				if( !$news = $newsRepository->findOneByUser($criteria['id'], $user) )
					return $this->respondNotFound('News not found');

				$title = $news->getTitle();
                $link = $news->getDashboardLink();
				$type = "l'actualité";

				break;

			case 'formation':

				if( !$formationCourse = $formationCourseRepository->find($criteria['id']) )
					return $this->respondNotFound('Formation course not found');

				$title = $formationCourse->getFormation()->getTitle();
				$link = $_ENV['DASHBOARD_URL'].'/formations/formation/'.$formationCourse->getId();
				$type = "la formation";

				break;

			default:
				return $this->respondBadRequest('Invalid arguments');
		}

		$body = $mailer->createBodyMail('share/resource.html.twig', ['form'=>$criteria, 'link'=>$link, 'title'=>$title, 'name'=>$user->getName(), 'type'=>$type]);
		$mailer->sendMessage($criteria['emails'], $criteria['subject'], $body, $user->getEmail());

		return $this->respondOK();
	}
}
