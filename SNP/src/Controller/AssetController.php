<?php

namespace App\Controller;

use App\Repository\OptionRepository;
use Exception;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Asset Controller
 *
 * @SWG\Tag(name="Misc")
 */
class AssetController extends AbstractController
{

    /**
     * get asset
     *
     * @Route("/asset/{id}", methods={"GET"})
     *
     * @SWG\Response(response=200, description="File")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Resource not found")
     *
     * @param OptionRepository $optionRepository
     * @param $id
     * @return Response
     * @throws Exception
     */
	public function find(OptionRepository $optionRepository, $id)
	{
		if( $option = $optionRepository->findOneBy(['name'=>$id]) ){

            $file = $option->getValue();

            if( strpos($file, $_ENV['CMS_URL']) === 0 ){

                $path = $this->storeRemoteFile($option->getValue(), null, null, '/uploads');
                $filename = basename($path);

                return $this->respondFile($path, $filename);
            }
        }

        return $this->respondHtmlError('The requested file does not exists');
	}
}
