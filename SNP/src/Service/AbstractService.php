<?php

namespace App\Service;

use Exception;
use SimpleXMLElement;
use Symfony\Component\Mime\Part\DataPart;

abstract class AbstractService {

    /**
     * @param $value
     * @param int $empty
     * @return float|int
     */
    protected function formatFloat($value, $empty=0 ){

        if( empty($value) )
            return $empty;

        if( is_string($value) )
            $value = str_replace(',','.', str_replace(' ','', $value));

        return floatval($value);
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

	/**
	 * @param $userInput
	 * @param $reference
	 * @return array
	 * @throws Exception
	 */
	public function format(array $userInput, array $reference){

		$data = [];

		foreach ($reference as $key=>$settings){

			if( !isset( $userInput[$key] ) ){

				if( $settings['required']??false )
					throw new Exception($key.' is empty');

				if( isset($settings['default']) )
					$userInput[$key] = $settings['default'];
			}

			if( isset( $userInput[$key] ) ){

				$value = $userInput[$key];

				switch ( $settings['type'] ){

					case 'string':

						$value = (string)$value;

						if( $settings['max']??false )
							$value = substr($value, 0, $settings['max']);

						break;

					case 'multipart/form-data':

						if( file_exists($value) )
							$value = DataPart::fromPath($value);
						else
							throw new Exception('File '.$value.' does not exist');

						break;

					case 'int':

						$value = intval($value);

						if( $settings['max']??false )
							$value = max($value,  $settings['max']);

						if( $settings['min']??false )
							$value = min($value,  $settings['min']);
						break;

					case 'enum':

						if( !in_array($value, $settings['allowed']) )
							$value = '';
						break;

					case 'array':

						if( !is_array($value) )
							$value = '';
						break;

					case 'xml':

						if( is_array($value) ){

							$xml = new SimpleXMLElement('<'.$settings['root'].'/>');

							if( $settings['xmlns']??false )
								$xml->addAttribute('xmlns', $settings['xmlns']);

							array_walk_recursive($value, array ($xml, 'addChild'));
							$value = $xml->asXML();
						}
						break;

					case 'bool':
						$value = $value||$value=='true'?'true':'false';
						break;
				}

				$data[$key] = $value;
			}
		}

		return $data;
	}

    /**
     * @param $value
     * @return float
     */
    public function parseFloat($value){

        if( is_string($value) )
            $value = str_replace(',','.', $value);

        return floatval($value);
    }
}