<?php

namespace App\Controller;

use App\Entity\AbstractEntity;
use App\Response\TransparentPixelResponse;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use ZipArchive;


abstract class AbstractController extends SymfonyAbstractController
{
    public $translator;
    public $entityManager;

    public function __construct(TranslatorInterface $translator, EntityManagerInterface $entityManager)
    {
        $this->translator = $translator;
        $this->entityManager = $entityManager;
    }

    /**
     * @param $string
     * @return string
     */
    public function __($string)
    {
        return $this->translator->trans($string);
    }

    /**
     * @param $directory_parameter
     * @return string
     */
    public function getPath($directory_parameter){

        $path = $this->getParameter('kernel.project_dir').$this->getParameter($directory_parameter);

        $filesystem = new Filesystem();

        if(!$filesystem->exists($path))
            $filesystem->mkdir($path, 0755);

        return $path;
    }

    /**
     * Optimized algorithm from http://www.codexworld.com
     *
     * @param AbstractEntity $entity
     * @param array latLng
     * @return float [km]
     */
    public function getDistance($entity, $latLng)
    {
        if( !$entity || !is_array($latLng) || count($latLng) != 2 )
            return false;

        $latitudeFrom = $longitudeFrom = 0;

        if( is_object($entity) ){

            if( !method_exists($entity, 'getLat') || !method_exists($entity, 'getLng') )
                return false;

            $latitudeFrom = $entity->getLat();
            $longitudeFrom = $entity->getLng();
        }
        elseif( is_array($entity) ){

            if( !isset($entity['latLng']) || !is_array($entity['latLng']) || count($entity['latLng']) != 2 )
                return false;

            $latitudeFrom = $entity['latLng'][0];
            $longitudeFrom = $entity['latLng'][1];
        }

        $latitudeTo = $latLng[0];
        $longitudeTo = $latLng[1];

        if( ($latitudeFrom==0 && $longitudeFrom == 0) || ($latitudeTo==0 && $longitudeTo == 0))
            return false;

        $rad = M_PI / 180;

        $theta = $longitudeFrom - $longitudeTo;
        $dist = sin($latitudeFrom * $rad)
            * sin($latitudeTo * $rad) +  cos($latitudeFrom * $rad)
            * cos($latitudeTo * $rad) * cos($theta * $rad);

        $distance = round(acos($dist) / $rad * 60 *  1.853);

        if( is_nan($distance) )
            return false;

        return $distance;
    }

    /**
     *
     * @param $entity
     * @param $user
     * @return float [km]
     */
    public function getUserDistance(?AbstractEntity $entity, UserInterface $user)
    {
        $latLng = [];

        if( $user->isLegalRepresentative() && $company = $user->getCompany() ){

            $latLng = [$company->getLat(), $company->getLng()];
        }
        elseif( $contact = $user->getContact() )
        {
            if( $address = $contact->getHomeAddress() )
                $latLng = [$address->getLat(), $address->getLng()];
        }

        if( empty($latLng) )
            return false;

        return $this->getDistance($entity, $latLng);
    }


    /**
     * @param Request $request
     * @return array
     */
    protected function getPagination(Request $request){

        $limit = max(2, min(100, intval($request->get('limit', $_ENV['DEFAULT_LIMIT']??10))));
        $offset = max(0, intval($request->get('offset', 0)));

        return [$limit, $offset];
    }


    /**
     * @param UploadedFile $uploadedFile
     * @param $directory_parameter
     * @param bool $filename
     * @return array
     */
    protected function moveUploadedFile(UploadedFile $uploadedFile, $directory_parameter, $filename=false){

        if( !$filename )
            $filename = uniqid().'.'.$uploadedFile->guessExtension();

        $directory_parameter = explode('.', $directory_parameter);
        $directory = $this->getParameter('kernel.project_dir').$this->getParameter($directory_parameter[0]);

        if( count($directory_parameter) == 2 )
            $directory.= '/'.$directory_parameter[1];

        $uploadedFile->move($directory, $filename);

        $filepath = count($directory_parameter) == 2 ? $directory_parameter[1].'/'.$filename : $filename;

        return [$directory, $filepath];
    }


    /**
     * @param $directory_parameter
     * @param bool $filename
     * @return void
     * @throws IOException
     */
    protected function deleteUploadedFile($directory_parameter, $filename){

        $directory = $this->getParameter('kernel.project_dir').$this->getParameter($directory_parameter);
        $filesystem = new Filesystem();

        $filesystem->remove($directory.'/'.$filename);
    }



    /**
     * @param FormInterface $form
     * @return array
     */
    protected function getErrors(FormInterface $form)
    {
        $errors = array();

        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }

        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childErrors = $this->getErrors($childForm)) {
                    $errors[$childForm->getName()] = $childErrors;
                }
            }
        }

        return $errors;
    }


    /**
     * @param $statusText
     * @param array $exception
     * @param int $code
     * @return JsonResponse
     */
    protected function respondError($statusText, $exception=[], $code=Response::HTTP_INTERNAL_SERVER_ERROR)
    {
        if( is_array($exception) ){
            foreach ($exception as $key=>&$value){
                if( is_string($value) )
                    $value = $this->__($value);
            }
        }

        $response = [
            'status' => 'error',
            'status_code' => $code,
            'status_text' => $this->__($statusText)
        ];

        if( !empty($exception) )
            $response['exception'] = $exception;

        return $this->json($response, $code);
    }


    /**
     * @param $response
     * @param int $code
     * @return JsonResponse
     */
    protected function respondSuccess($response, $code=Response::HTTP_OK)
    {
        return $this->json([
            'status' => 'success',
            'status_code' => $code,
            'response' => $response
        ], $code);
    }


    /**
     * @param $statusText
     * @return JsonResponse
     */
    protected function respondInternalServerError($statusText="Internal server error")
    {
        return $this->respondError($statusText, null, Response::HTTP_INTERNAL_SERVER_ERROR);
    }


    /**
     * @param string $statusText
     * @param array $exception
     * @return JsonResponse
     */
    protected function respondBadRequest($statusText="Bad request", $exception=[])
    {
        return $this->respondError($statusText, $exception, Response::HTTP_BAD_REQUEST);
    }


    /**
     * @param string $statusText
     * @param array $params
     * @return JsonResponse
     */
    protected function respondNotFound($statusText="Not found", $params=[])
    {
        if( !empty($params) ){

            $statusText = $this->translator->trans($statusText);
            $statusText = @vsprintf($statusText, $params);
        }

        return $this->respondError($statusText, null, Response::HTTP_NOT_FOUND);
    }


    /**
     * @param $file
     * @param bool $filename
     * @param string $disposition
     * @param int $maxAge
     * @return Response
     */
    protected function respondFile($file, $filename=false, $disposition=ResponseHeaderBag::DISPOSITION_ATTACHMENT, $maxAge=0)
    {
        $filename = !$filename ? basename($file) : $filename;
        $response = $this->file($file, $filename, $disposition);

        if($maxAge)
            $response->setMaxAge($maxAge);

        //todo: remove - fix symfony 4.4.46
        $response->headers->set('Content-Type', $response->getFile()->getMimeType() ?: 'application/octet-stream');

        return $response;
    }


    /**
     * @param $rows
     * @param $filename
     * @return Response
     */
    protected function respondCSV($rows, $filename)
    {
        $content = implode("\n", $rows);
        $content = "\xEF\xBB\xBF".$content; //BOM

        return $this->respondContent($content, ['Content-Type'=>'text/csv', 'Content-Disposition'=>'attachment;filename="'.$this->sanitizeFilename($filename).'"']);
    }


    /**
     * @param $files
     * @param bool $filename
     * @param bool $delete
     * @return string
     */
    protected function createZip($files, $filename, $delete=true)
    {
        $export_path = $this->getParameter('kernel.cache_dir').'/export';
        $filesystem = new Filesystem();

        if( !is_dir($export_path ) )
            $filesystem->mkdir($export_path);

        $zip_path = $export_path.'/'.$filename;

        if( file_exists($zip_path) )
            unlink($zip_path);

        $zip = new ZipArchive();
        $zip->open($zip_path,  ZipArchive::CREATE);

        foreach ($files as $localname=>$file) {

            $zip->addFromString(basename($localname), file_get_contents($file));

            if( $delete )
                unlink($file);
        }

        $zip->close();

        return $zip_path;
    }


    /**
     * @param $url
     * @param DateTimeInterface|null $modifiedAt
     * @param string $cache_dir
     * @param bool $returnPathOnly
     * @param bool $filename
     * @return array|string
     * @throws Exception
     * todo: readable name for stored file
     * todo: garbage collector
     */
    protected function storeRemoteFile($url, $filename=false, DateTimeInterface $modifiedAt=null, $cache_dir='', $returnPathOnly=true)
    {
        try {

            $cacheResourcesFolderPath = $this->getParameter('kernel.cache_dir') . $cache_dir;

            $filesystem = new Filesystem();

            $env = $_ENV['APP_ENV']??'prod';
            $curlHttpClient = new CurlHttpClient(['verify_peer'=>($env!='dev')]);

            if( !is_dir($cacheResourcesFolderPath ) )
                $filesystem->mkdir($cacheResourcesFolderPath);

            $path_parts = pathinfo($url);
            $url_parts = parse_url($url);

            $cacheResourcesFolderPath .= '/'.$url_parts['host'];

            if( !is_dir($cacheResourcesFolderPath ) )
                $filesystem->mkdir($cacheResourcesFolderPath);

            $filedir = $cacheResourcesFolderPath.'/'.md5($url);

            if( !is_dir($filedir ) )
                $filesystem->mkdir($filedir);

            $basename = strtok($path_parts['basename'], '?');

            $cache_enabled = intval($_ENV['CACHE_ENABLED']??0);

            if( $cache_enabled ){

                $finder = new Finder();
                $finder->depth('== 0')->files()->in($filedir);

                if( $finder->hasResults() ){

                    $files = iterator_to_array($finder);

                    /** @var SplFileInfo $file */
                    $file = end($files);

                    if( !$modifiedAt || $file->getMTime() >= $modifiedAt->getTimestamp() ){

                        if( $returnPathOnly )
                            return $file->getRealPath();
                        else
                            return [$file->getRealPath(), $file->getFilename()];
                    }
                }
            }

            $response = $curlHttpClient->request('GET', $url);
            $headers = $response->getHeaders();

            $content_disposition = $headers['content-disposition'][0]??false;
            $content_type = $headers['content-type'][0]??false;

            if( !$filename && $content_disposition ){

                preg_match('/.*filename=[\\\'\"]?([^\"]+)/', $content_disposition, $filename );

                if( $filename && is_array($filename) && count($filename) == 2 )
                    $filename = $filename[1];
                else
                    $filename = false;
            }

            $content = $response->getContent();

            //todo: remove when doing signature refacto
            if( $content_type && $content_type == 'application/json'){

                $decoded_content = json_decode($content, true);
                if( ($decoded_content['status']??'') === 'error')
                    throw new Exception($decoded_content['error_string']??'');
            }

            if(!$filename )
                $filename = $basename;

            $filepath = $filedir.'/'.$filename;

            $filesystem->dumpFile($filepath, $content);

            if( $modifiedAt )
                touch($filepath, $modifiedAt->getTimestamp());

            if( $returnPathOnly )
                return $filepath;
            else
                return [$filepath, $filename];

        } catch (Throwable $t) {

            throw new Exception($t->getMessage());
        }
    }


    /**
     * @param $statusText
     * @return JsonResponse
     */
    protected function respondCreated($statusText="Item created")
    {
        return $this->respondSuccess($statusText, Response::HTTP_CREATED);
    }


    /**
     * @param string $statusText
     * @param int $maxAge
     * @return JsonResponse
     */
    protected function respondOK($statusText="OK", $maxAge=0)
    {
        $response = $this->respondSuccess($statusText);

        if( $maxAge ){

            $response->setPublic();
            $response->setMaxAge($maxAge);
        }

        return $response;
    }


    /**
     * @return TransparentPixelResponse
     */
    protected function respondTransparentPixel()
    {
        return new TransparentPixelResponse();
    }


    /**
     * @param $view
     * @param $parameters
     * @return Response
     */
    protected function respondHtml($view, $parameters)
    {
        $parameters['env'] = $_ENV;
        $parameters['host'] = $_ENV['SECURE_URL'];

        return $this->render($view, $parameters);
    }


    /**
     * @param $message
     * @param string $title
     * @return Response
     */
    protected function respondHtmlError($message, $title='Une erreur est survenue')
    {
        return $this->respondHtml('misc/general.html.twig', ['title'=>$title, 'message'=> $this->translator->trans($message)]);
    }


    /**
     * @param $message
     * @param string $title
     * @return Response
     */
    protected function respondHtmlOk($message, $title='Information')
    {
        return $this->respondHtml('misc/general.html.twig', ['title'=>$title, 'message'=> $this->translator->trans($message)]);
    }


    /**
     * @param $content
     * @param array $headers
     * @return Response
     */
    protected function respondContent($content, $headers=[])
    {
        if( empty($content) || is_bool($content) )
            return $this->respondError('Content is empty');

        $response = new Response($content);

        foreach ($headers as $key=>$value)
            $response->headers->set($key, $value);

        return $response;
    }


    /**
     * @param $statusText
     * @return JsonResponse
     */
    protected function respondGone($statusText="Item is gone")
    {
        return $this->respondSuccess($statusText, Response::HTTP_GONE);
    }


    /**
     * Lazy loading repository hydration
     *
     * @param $items
     * @return array
     */
    protected function hydrateAll( $items ){

        foreach ($items as &$item)
            $item = $this->entityManager->getRepository(get_class($item))->hydrate($item);

        return $items;
    }


    /**
     * @param $formType
     * @param Request $request
     * @param $entity
     * @param bool $clearMissing
     * @param array $options
     * @return FormInterface
     */
    protected function submitForm($formType, Request $request, $entity=null, $clearMissing=true, $options=[])
    {
        $options = array_merge(['allow_extra_fields'=>true], $options);

        $form = $this->createForm($formType, $entity, $options);

        if( $request->getMethod() == 'GET'){

            $data = $request->query->all();
        }
        else{
            $data = $request->request->all();
            $data = array_merge($data, $request->files->all());
        }

        $form->submit($data, $clearMissing);

        return $form;
    }


    /**
     * @param $filename
     * @return mixed
     */
    protected function sanitizeFilename($filename) {

        /* Force the file name in UTF-8 (encoding Windows / OS X / Linux) */
        $filename = mb_convert_encoding($filename, "UTF-8");

        $char_not_clean = array('/À/','/Á/','/Â/','/Ã/','/Ä/','/Å/','/Ç/','/È/','/É/','/Ê/','/Ë/','/Ì/','/Í/','/Î/','/Ï/','/Ò/','/Ó/','/Ô/','/Õ/','/Ö/','/Ù/','/Ú/','/Û/','/Ü/','/Ý/','/à/','/á/','/â/','/ã/','/ä/','/å/','/ç/','/è/','/é/','/ê/','/ë/','/ì/','/í/','/î/','/ï/','/ð/','/ò/','/ó/','/ô/','/õ/','/ö/','/ù/','/ú/','/û/','/ü/','/ý/','/ÿ/', '/©/');
        $clean = array('a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','y','a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','o','o','o','o','o','o','u','u','u','u','y','y','copy');

        $friendly_filename = preg_replace($char_not_clean, $clean, $filename);

        /* After replacement, we destroy the last residues */
        $friendly_filename = utf8_decode($friendly_filename);
        $friendly_filename = preg_replace('/\?/', '', $friendly_filename);

        /* Remove non-alphanumeric characters */
        $friendly_filename = preg_replace('/[^A-Za-z0-9. ]/', '-', $friendly_filename);

        /* Lowercase */
        $friendly_filename = strtolower($friendly_filename);

        /* Replace space */
        $friendly_filename = str_replace(' ', '_', $friendly_filename);

        return trim($friendly_filename);
    }

    protected function obfuscateEmail($email){

        return preg_replace_callback('/(\w)(.*?)(\w)(@.*?)$/s', function ($matches){
            return $matches[1].preg_replace("/\w/", "*", $matches[2]).$matches[3].$matches[4];
        }, $email);
    }
}
