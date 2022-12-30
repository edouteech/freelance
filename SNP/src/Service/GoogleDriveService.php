<?php

namespace App\Service;

use DOMDocument;
use Exception;
use Google\Client as GoogleClient;
use Google_Service_Drive as GoogleDrive;
use HTMLPurifier;
use HTMLPurifier_Config;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use Symfony\Component\Filesystem\Filesystem;
use Google_Service_Drive_DriveFile as GoogleDriveFile;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class GoogleDriveService extends AbstractService
{
    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    /**
     * Returns an authorized API client.
     * @return GoogleClient the authorized client object
     * @throws Exception
     */
    function getClient()
    {
        $client = new GoogleClient();

        $client->setApplicationName('SNPI Eudo');
        $client->setScopes(GoogleDrive::DRIVE);
        $client->setRedirectUri("urn:ietf:wg:oauth:2.0:oob");

        // Download credentials.json from client configuration
        $client->setAuthConfig(
            sprintf('%s/config/google-api/credentials.json', $this->parameterBag->get('kernel.project_dir'))
        );
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
        $tokenPath = sprintf('%s/config/google-api/token.json', $this->parameterBag->get('kernel.project_dir'));

        if (file_exists($tokenPath)) {

            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);

        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {

            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {

                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

            } else {

                // Request authorization from the user.
                printf("Open the following link in your browser:\n%s\n", $client->createAuthUrl());
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));
                }
            }

            $filesystem = new Filesystem();

            $dir = dirname($tokenPath);

            if(!$filesystem->exists($dir))
                $filesystem->mkdir($dir, 0755);

            // Save the token to a file.
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }

        return $client;
    }

    /**
     * @param $driveFileId
     * @param string $filename
     * @param string $filepath
     *
     * @return GoogleDriveFile google drive file object
     * @throws Exception
     */
    public function pushDocument($driveFileId, $filename, $filepath)
    {
        $service = new GoogleDrive(
            $this->getClient()
        );

        $file = new GoogleDriveFile();
        $mimeType = 'application/vnd.google-apps.document';

        $file->setName($filename);
        $file->setMimeType($mimeType);

        if( $driveFileId ){

            $driveFile = $service->files->update($driveFileId, $file, [
                'data' => file_get_contents($filepath),
                'mimeType' => $mimeType
            ]);
        }
        else{

            $driveFile = $service->files->create($file, [
                'data' => file_get_contents($filepath),
                'mimeType' => $mimeType
            ]);
        }

        return $driveFile;
    }

    /**
     * @param string $filename
     * @param string $content
     *
     * @return GoogleDriveFile google drive file object
     */
    public function pushHtmlContent($driveFileId, $filename, $content)
	{
		$config = HTMLPurifier_Config::createDefault();
		$purifier = new HTMLPurifier($config);
		$content = $purifier->purify($content);

	    $dir = $this->parameterBag->get('kernel.cache_dir').'/export/tmp';

        $filesystem = new Filesystem();

        if(!$filesystem->exists($dir))
            $filesystem->mkdir($dir, 0755);

        $filepath = sprintf('%s/%s', $dir, $this->sanitizeFilename($filename));

        $pw = new PhpWord();

		$pw->setDefaultFontName('HelveticaNeueLT');
		$pw->setDefaultFontSize(11);

        $pw->addTitleStyle(1, array('name'=>'Arial', 'size'=>28, 'color'=>'00000', 'bold'=>true, 'contextualSpacing'=>true)); //h1
        $pw->addTitleStyle(2, array('name'=>'Arial', 'size'=>24, 'color'=>'00000', 'bold'=>true, 'contextualSpacing'=>true)); //h2
        $pw->addTitleStyle(3, array('name'=>'Arial', 'size'=>20, 'color'=>'00000', 'bold'=>true, 'contextualSpacing'=>true)); //h3
        $pw->addTitleStyle(4, array('name'=>'Arial', 'size'=>16, 'color'=>'00000', 'bold'=>true, 'contextualSpacing'=>true)); //h4

        $section = $pw->addSection(['contextualSpacing'=>true]);
        Html::addHtml($section, $content, false, false);

        $pw->save($filepath);

        $driveFile = $this->pushDocument($driveFileId, $filename, $filepath);

        $filesystem = new Filesystem();
        $filesystem->remove($filepath);

        return $driveFile;
	}

    /**
     * @param string $id
     * 
     * @return GoogleDriveFile google drive file object
     * 
     * @throws Exception
     */
    public function getDocument(string $id)
    {
        $service = new GoogleDrive(
            $this->getClient()
        );

        $driveFile = null;

        try {

            $driveFile = $service->files->get($id);

        } catch (Exception $e) {

            $error = json_decode($e->getMessage(), true);

            throw new Exception($error['error']['message'], $error['error']['code']);

        }

        return  $driveFile;
    }
}