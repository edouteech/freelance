<?php

namespace App\Controller;

use App\Repository\DownloadRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\ORMException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;

/**
 * Download Controller
 *
 * @SWG\Tag(name="Misc")
 *
 */
class DownloadController extends AbstractController
{
	/**
	 * Download file
	 *
	 * @Route("/download/{uuid}/{filename}", name="download_attachment", methods={"GET"})
	 *
	 * @SWG\Parameter(name="disposition", in="query", type="string", enum={"inline", "attachment"}, description="File disposition", default="inline")
	 *
	 * @SWG\Response(response=200, description="Returns resource")
	 * @SWG\Response(response=404, description="Download not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 *
	 * @param DownloadRepository $downloadRepository
	 * @param $uuid
	 * @param $filename
	 * @return Response
	 * @throws NonUniqueResultException
	 * @throws ORMException
	 */
	public function download(Request $request, DownloadRepository $downloadRepository, $uuid, $filename)
	{
        $downloadRepository->deleteExpired();

        $download = $downloadRepository->findByUuid($uuid);

	    if( !$download || !file_exists($download->getPath()) )
	        return $this->respondHtmlError('Download link has expired');

	    if( $download->getIpHash() != $downloadRepository->getHash($request->getClientIp()) )
            return $this->respondHtmlError('You are not allowed to access this download link');

	    $disposition = $request->get('disposition');

	    if( $disposition != 'attachment')
            $disposition = 'inline';

		return $this->respondFile($download->getPath(), $download->getFilename(), $disposition)->deleteFileAfterSend($download->getDeleteFile());
	}
}