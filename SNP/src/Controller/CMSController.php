<?php

namespace App\Controller;

use App\Service\ImageResizer;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;


/**
 * CMS Controller
 *
 * @SWG\Tag(name="Misc")
 *
 */
class CMSController extends AbstractController
{
    /**
     * Get uploads
     *
     * @Route("/cms/uploads/{path}", methods={"GET"}, requirements={"path"=".+"})
     *
     * @SWG\Response(response=200, description="Return cached upload")
     * @SWG\Response(response=404, description="Invalid parameters")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     * @param ImageResizer $imageResizer
     * @param $path
     * @return Response
     * @throws Exception
     */
    public function cmsUploads(Request $request, ImageResizer $imageResizer, $path)
    {
        $url = $_ENV['CMS_URL'].'/uploads/'.$path;
        $filename = basename($path);

        $path = $this->storeRemoteFile($url, null, null, '/uploads');

        if( $request->get('w') || $request->get('h') ){

            $w = min(1920, max(0, intval($request->get('w'))));
            $h = min(1080, max(0, intval($request->get('h'))));

            $path = $imageResizer->process($path, ['resize'=>[$w, $h]]);
        }

        $disposition = $request->get('disposition') == 'inline' || $request->get('w') || $request->get('h') ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        return $this->respondFile($path, $filename, $disposition);
    }
}