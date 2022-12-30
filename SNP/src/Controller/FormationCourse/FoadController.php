<?php

namespace App\Controller\FormationCourse;

use App\Controller\AbstractController;
use App\Entity\Formation;
use App\Entity\FormationCourse;
use App\Entity\FormationParticipant;
use App\Entity\FormationParticipantProgress;
use App\Form\Type\FormationParticipantProgressType;
use App\Repository\DownloadRepository;
use App\Repository\FormationFoadRepository;
use App\Repository\FormationParticipantProgressRepository;
use App\Repository\FormationParticipantRepository;
use App\Repository\FormationRepository;
use Doctrine\ORM\ORMException;
use Nelmio\ApiDocBundle\Annotation\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;


/**
 * Formation course Controller
 *
 * @SWG\Tag(name="Formations Course")

 * @Security(name="Authorization")
*/
class FoadController extends AbstractController
{

    /**
     * @param $formationCourseId
     * @return FormationCourse
     */
    private function getFormationCourse($formationCourseId){

        $formationCourseRepository = $this->entityManager->getRepository(FormationCourse::class);

        if( !$formationCourse = $formationCourseRepository->findOneBy(['id'=>$formationCourseId]) )
            throw new NotFoundHttpException('Unable to find formation');

        if( $formationCourse->getFormat() != Formation::FORMAT_E_LEARNING )
            throw new NotFoundHttpException('Formation course is not e-learning');

        return $formationCourse;
    }

    /**
     * @param UserInterface $user
     * @param FormationCourse $formationCourse
     * @return FormationParticipant
     */
    private function getFormationParticipant(UserInterface $user, FormationCourse $formationCourse){

        if( !$contact = $user->getContact() )
            throw new NotFoundHttpException('Contact not found');

        $formationParticipantRepository = $this->entityManager->getRepository(FormationParticipant::class);

        if( !$formationParticipant = $formationParticipantRepository->findOneBy(['contact'=>$contact, 'formationCourse'=>$formationCourse, 'registered'=>1]) )
            throw new NotFoundHttpException('Formation participant not found');

        return $formationParticipant;
    }

    /**
     * @param Formation $formation
     * @param FormationParticipantProgress $progress
     * @return array|bool
     */
    private function getFormationSubchapter(Formation $formation, FormationParticipantProgress $progress){

        $formationFoad = $formation->getFoad();

        $write = $formationFoad->getWrite();
        $introduction = $write['introduction']??[];
        $chapters = $write['chapters']??[];

        if($progress->getChapter() < -1 || $progress->getChapter() >= count($chapters) )
            return false;

        if( $progress->getChapter() >= 0 ){

            $chapter = $chapters[$progress->getChapter()];
            $subchapters = $chapter['subchapters']??[];
        }
        else{

            $subchapters = $introduction;
        }

        if( $progress->getSubchapter() >= count($subchapters) )
            return false;

        return $subchapters[$progress->getSubchapter()];
    }

    /**
     * Get one formation course content
     *
     * @Route("/formation/course/{id}/foad", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @IsGranted("ROLE_CLIENT")
     *
     * @SWG\Response(response=200, description="Returns formation foad")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Formation not found")
     *
     * @param FormationRepository $formationRepository
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param FormationFoadRepository $formationFoadRepository
     * @param int $id
     * @return JsonResponse
     */
    public function find(FormationRepository $formationRepository, FormationParticipantRepository $formationParticipantRepository, FormationFoadRepository $formationFoadRepository, $id)
    {
        $user = $this->getUser();

        $formationCourse = $this->getFormationCourse($id);
        $formation = $formationCourse->getFormation();
        $formationFoad = $formation->getFoad();
        $formationParticipant = $this->getFormationParticipant($user, $formationCourse);
	    $content = $formationFoadRepository->hydrate($formationFoad, $formationCourse->getFormat());

        return $this->respondOK([
            'id' => $formationCourse->getId(),
            'participant' => $formationParticipantRepository->hydrate($formationParticipant),
            'formation' => $formationRepository->hydrate($formation),
            'content' => $content,
            'hash' => md5(json_encode($content))
        ]);
    }

    /**
     * Get formation course hash
     *
     * @Route("/formation/course/{id}/foad/hash", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @IsGranted("ROLE_CLIENT")
     *
     * @SWG\Response(response=200, description="Returns formation foad hash")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Formation not found")
     *
     * @param FormationFoadRepository $formationFoadRepository
     * @param int $id
     * @return JsonResponse
     */
    public function getHash(FormationFoadRepository $formationFoadRepository, $id)
    {
        $formationCourse = $this->getFormationCourse($id);
        $formation = $formationCourse->getFormation();
        $formationFoad = $formation->getFoad();

	    $content = $formationFoadRepository->hydrate($formationFoad, $formationCourse->getFormat());

        return $this->respondOK(md5(json_encode($content)));
    }

    /**
     * Store progress
     *
     * @Route("/formation/course/{id}/foad", methods={"POST"}, requirements={"id"="\d+"})
     *
     * @IsGranted("ROLE_CLIENT")
     *
     * @SWG\Response(response=200, description="Returns formation foad")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Formation not found")
     *
     * @param Request $request
     * @param FormationParticipantProgressRepository $formationParticipantProgressRepository
     * @param int $id
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     */
    public function progress(Request $request, FormationParticipantProgressRepository $formationParticipantProgressRepository, $id)
    {
        $user = $this->getUser();

        $formationCourse = $this->getFormationCourse($id);
        $formation = $formationCourse->getFormation();
        $formationParticipant = $this->getFormationParticipant($user, $formationCourse);

        if( !$progress = $formationParticipant->getProgress() ){

            $progress = new FormationParticipantProgress();
            $formationParticipant->setProgress($progress);
        }

        $chapterRead = $progress->getChapterRead();

        $progressForm = $this->submitForm(FormationParticipantProgressType::class, $request, $progress);

        if( !$progressForm->isValid() )
            return $this->respondBadRequest('Invalid arguments', $this->getErrors($progressForm));

        if( !$this->getFormationSubchapter($formation, $progress) )
            return $this->respondBadRequest('Invalid arguments', ['subchapter'=>'Invalid']);

        if( $request->get('key') != sha1(json_encode($progress->__toArray())) )
            return $this->respondBadRequest('Invalid arguments', ['key'=>'Invalid']);

        if( $chapterRead < $progress->getChapter() )
            $progress->setSubchapterRead(0);

        $formationParticipantProgressRepository->save($progress);

        return $this->respondOK();
    }

    /**
     * Get one formation course foad as pdf
     *
     * @Route("/formation/course/{id}/foad/pdf", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @IsGranted("ROLE_CLIENT")
     *
     * @SWG\Response(response=200, description="Returns formation foad")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Formation not found")
     *
     * @param Request $request
     * @param DownloadRepository $downloadRepository
     * @param int $id
     * @return JsonResponse
     * @throws ORMException
     * @throws ExceptionInterface
     */
    public function getPdf(Request $request, DownloadRepository $downloadRepository, $id)
    {
        $user = $this->getUser();

        $formationCourse = $this->getFormationCourse($id);
        $formation = $formationCourse->getFormation();

        $this->getFormationParticipant($user, $formationCourse);

        $pdf = $this->getPath('formation_directory').'/'.$formation->getId().'.pdf';

        if(!file_exists($pdf) )
            return $this->respondNotFound('Formation foad pdf not found');

        $download = $downloadRepository->create($request, $pdf);

        return $this->respondOK($downloadRepository->hydrate($download));
    }
}
