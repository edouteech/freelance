<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Exception;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @MappedSuperclass
 */
abstract class AbstractEntity
{
	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 */
	protected $id;

	public function __toString()
	{
		return get_class($this).'@'.$this->getId();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function isLegacy(): ?bool
	{
		return $this->getType()=='company' || $this->getType()=='contact';
	}

    /**
     * @param int $length
     * @return string|string[]|null
     * @throws Exception
     */
    public function generateToken($length=6){

		$token = base64_encode(random_bytes($length));
		$token = preg_replace("/[^A-Za-z0-9]/", '', $token);

		return $token;
	}

	public function merge( $data )
	{
		foreach ($data as $key=>$value){

			$setter = 'set_'.ucwords($key);
			$setter = str_replace('_', '', ucwords($setter, '_'));

			$getter = 'get_'.ucwords($key);
			$getter = str_replace('_', '', ucwords($getter, '_'));

			if( method_exists($this, $setter) && method_exists($this, $getter) ){

				$currentValue = $this->$getter();

				if( !is_object($currentValue) )
					$this->$setter($value);
			}
		}
	}

	public function denormalize( $entity, $className )
	{
		$serializer = new Serializer([new ObjectNormalizer()]);

		try {
			return $serializer->denormalize($entity, $className, null);
		} catch (ExceptionInterface $e) {
			return NULL;
		}
	}

	/**
	 * @param $datetime
	 * @param bool $create
	 * @return null|DateTime
	 * @throws Exception
	 */
	protected function formatDateTime($datetime, $create=false ){

		if( is_string($datetime) ){

			try {
				$datetime = new DateTime($datetime);
			} catch (Exception $e) {}
		}
		elseif( is_numeric($datetime) ){

			$_datetime = $datetime;
			$datetime = new DateTime();
			$datetime->setTimestamp($_datetime);
		}

		if( $datetime instanceof DateTime)
			return $datetime;
		else
			return $create ? new DateTime() : NULL;
	}

	/**
	 * @param $value
	 * @param int $empty
	 * @return int
	 */
	protected function formatInt($value, $empty=0 ){

		if( empty($value) )
			return $empty;

		if( is_string($value) )
			$value = str_replace(' ','', $value);

		return intval($value);
	}

    /**
     * @param $value
     * @param int $empty
     * @return float
     */
    protected function formatFloat($value, $empty=0 ){

        if( empty($value) )
            return $empty;

        if( is_string($value) )
            $value = str_replace(',','.', str_replace(' ','', $value));

        return floatval($value);
    }

	/**
	 * @param $value
	 * @param bool $allowNull
	 * @return boolean
	 */
    protected function formatBool($value, $allowNull=false){

    	if( is_null($value) && $allowNull )
    		return null;

	    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param $value
     * @return string
     */
    protected function formatText($value){

        if( is_string($value) ){

	        $value = str_replace("<br>", "\n", str_replace("<br/>", "\n", str_replace("<br />", "\n", $value)));
	        $value = trim(strip_tags(html_entity_decode($value)));

	       return $this->ucSentence($value);
        }

        return NULL;
    }

	/**
	 * @param $value
	 * @param int $length
	 * @return string
	 */
    protected function formatString($value, $length=255){

        if( is_string($value) ){

        	$value = substr(str_replace("\n", " ", trim(strip_tags(html_entity_decode($value)))), 0, $length);

	        return $this->ucSentence($value);
        }

        return NULL;
    }

	/**
	 * @param $point
	 * @return array
	 */
	protected function formatPoint($point){

		if( !$point )
			return null;

		$lng_lat = explode(',', str_replace(' ', ',', str_replace('POINT (', '', str_replace(')', '', $point))));

		if( count($lng_lat) == 2 )
			return [floatval($lng_lat[1]), floatval($lng_lat[0])];

		return null;
	}

	/**
	 * @param $status
	 * @return string
	 */
	protected function formatStatus( $status ){

		switch ($status){
			case 'Radié': return 'removed';
			case 'Adhérent': return 'member';
			case 'Non adhérent': return 'not_member';
			case 'Adhésion refusée': return 'refused';
		}

		return NULL;
	}

	/**
	 * @param $url
	 * @return string|null
	 */
	protected function formatUrl( $url ){

		if( !$url )
			return null;

		$url = $this->formatString($url);

		if(preg_match("#^w#", $url)){
			$is_clean = false;
			if(preg_match("#^www\.#", $url)){
				$is_clean = true;
			}
		}

		if(preg_match("#;#", $url))
			$is_clean = false;

		if(preg_match("#\\\.#", $url))
			$is_clean = false;

		if(preg_match("#/\.#", $url))
			$is_clean = false;

		if(preg_match("#^/#", $url, $matches))
			$url = str_replace($matches[0],"",$url);

		if(preg_match("#^www#", $url)){
			if(!preg_match("#\.#", substr($url,4)))
				$is_clean = false;
		}
		else{
			if(!preg_match("#\.#", $url))
				$is_clean = false;
		}

		if(preg_match("#@#", $url))
			$is_clean = false;

		return (isset($is_clean) && $is_clean===false) ? null : $url;
	}

    /**
     * @param $phone
     * @return string
     */
    protected function formatPhone($phone ){

	    if( !$phone )
		    return null;

        $phone = str_replace(" ","", $phone);
        $phone = str_replace(".","", $phone);
        $phone = str_replace("+33","0", $phone);
        $phone = preg_replace("/[^0-9+]/", "", $phone);

        return $phone;
    }


    /**
     * @param $city
     * @return string
     */
    protected function formatCity($city){

    	if( !$city )
    		return null;

	    $city = str_replace(array(" - "," -", "- ",'–'),"-", $city);
	    $city = str_replace(array("  ","\r","\n","\r\n")," ", $city);
	    $city = str_ireplace(array(" ST "," ST-","-ST ","-ST-"),array(" SAINT "," SAINT-","-SAINT ","-SAINT-"), $city);
	    $city = str_ireplace(array(" STE "," STE-","-STE ","-STE-"),array(" SAINTE "," SAINTE-","-SAINTE ","-SAINTE-"), $city);
	    if(preg_match("#^ST #i",$city, $matches)){
		    $city = str_ireplace($matches[0], "SAINT ",$city);
	    }
	    if(preg_match("#^ST-#i",$city, $matches)){
		    $city = str_ireplace($matches[0], "SAINT-",$city);
	    }

	    $city = mb_strtolower($city, 'UTF-8');
	    $city = ucwords($city,"-");
	    $city = ucwords($city);

	    return $city;
    }


    /**
     * @param $street
     * @return string
     */
    protected function formatStreet($street){

	    if( !$street )
			return null;

        $street = str_replace(array("    ","   ","  ","\r","\n","\r\n")," ", $street);
        $street = str_replace(";","", $street);
        $street = str_replace(array("\"\"\"\"","\"\"\"", "\"\""),"\"", $street);

        if(preg_match("#^[-/0-9]+#", $street) && (preg_match("#bis#i", $street) || preg_match("# b #i", $street))){
            if(!preg_match("#^[-/0-9]+ bis,#i", $street)){
                $street = str_ireplace(array(", B ",", B,",", bis ",", bis,"," bis ",",bis ",".bis "," bis,"," bis.",",bis,",",bis.",".bis,",".bis.",
                    " B "),"",$street, $cntreplace);
                if($cntreplace > 0){
                    if(preg_match("#^[-/0-9]+#i", $street, $matches)){
                        $street = str_replace($matches[0], $matches[0]." bis, ",$street);
                    }
                }
                else{
                    if(!preg_match("#^[-/0-9]+, #", $street, $matches)){
                        if(preg_match("#^[-/0-9]+#i", $street, $matches)){
                            $street = str_replace($matches[0], $matches[0].", ",$street);
                        }
                    }
                }
            }
        }
        else{
            if(preg_match("#^[-/0-9]+#i", $street) && (preg_match("#ter#i", $street) || preg_match("# t #i", $street))){
                if(!preg_match("#^[-/0-9]+ ter,#i", $street)){
                    $street = str_ireplace(array(", T ",", T,",", ter ",", ter,"," ter ",",ter ",".ter "," ter,"," ter.",",ter,",",ter.",".ter,",".ter.",
                        " T "),"",$street, $cntreplace);
                    if($cntreplace > 0){
                        if(preg_match("#^[-/0-9]+#i", $street, $matches)){
                            $street = str_replace($matches[0], $matches[0]." ter, ",$street);
                        }
                    }
                    else{
                        if(!preg_match("#^[-/0-9]+, #", $street, $matches)){
                            if(preg_match("#^[-/0-9]+#i", $street, $matches)){
                                $street = str_replace($matches[0], $matches[0].", ",$street);
                            }
                        }
                    }
                }
            }
            else{
                if(!preg_match("#^[-/0-9]+, #", $street, $matches)){
                    if(preg_match("#^[-/0-9]+#i", $street, $matches)){
                        $street = str_replace($matches[0], $matches[0].", ",$street);
                    }
                }
            }
        }

        $street = str_replace(array("    ","   ","  ","\r","\n","\r\n")," ", $street);

        $street = str_ireplace(" rue", " rue", $street);
        $street = str_ireplace(array(" l' "," l ' "," l '")," l'",$street);
        $street = str_ireplace(array(" d' "," d ' "," d '")," d'",$street);
        $street = str_ireplace(array(" boulevard"," bd", " bld"), " boulevard", $street);
        $street = str_ireplace(array(" avenue"," av "," ave "," av. "), array(" avenue"," avenue "," avenue "," avenue "), $street);
        $street = str_ireplace(" chemin", " chemin", $street);
        $street = str_ireplace(" quai", " quai", $street);
        $street = str_ireplace(" place", " place", $street);
        $street = str_ireplace(" route", " route", $street);
        $street = str_ireplace(" impasse", " impasse", $street);
        $street = str_ireplace(array(" alle "," allee "," allees "," allé "," allée "," allées "), " allée ", $street);
        $street = str_ireplace(" cours", " cours", $street);
        $street = str_ireplace(array(" rond point", " rond-point"), " rond-point", $street);
        $street = str_ireplace(array(" traverse ", " traversee ", " traversé ", " traversée "), " traversée ", $street);
        $street = str_ireplace(" promenade", " promenade", $street);
        $street = str_ireplace(array(" monte ", " montee ", " monté ", " montée "), " montée ", $street);
        $street = str_ireplace(array(" residence "," résidence "), " résidence ", $street);
        $street = str_ireplace(" voie ", " voie ", $street);
        $street = str_ireplace(array(" chausse ", " chaussee ", " chaussé ", " chaussée "), " chaussée ", $street);
        $street = str_ireplace(" square ", " square ", $street);
        $street = str_ireplace(" passage ", " passage ", $street);
        $street = str_ireplace(" villa ", " villa ", $street);
        $street = str_ireplace(" esplanade ", " esplanade ", $street);
        $street = str_ireplace(" port ", " port ", $street);
        $street = str_ireplace(" faubourg ", " faubourg ", $street);
        $street = str_ireplace(" espace ", " espace ", $street);
        $street = str_ireplace(" corniche ", " corniche ", $street);
        $street = str_ireplace(" tour ", " tour ", $street);
        $street = str_ireplace(" ZAC ", " ZAC ", $street);
        $street = str_ireplace(" moulin ", " moulin ", $street);
        $street = str_ireplace(" pont ", " pont ", $street);
        $street = str_ireplace(" sente ", " sente ", $street);
        $street = str_ireplace(", grand", ", grand", $street);
        $street = str_ireplace(" sentier ", " sentier ", $street);
        $street = str_ireplace(array(" arcade "," arcades "), " arcades ", $street);
        $street = str_ireplace(" lotissement ", " lotissement ", $street);
        $street = str_ireplace(" parc ", " parc ", $street);
        $street = str_ireplace(array(" centre cial ", " centre commercial ")," centre commercial ", $street);
		$street = str_ireplace(array(" cite ", " cité ")," cité ", $street);
		
		if(strlen($street) > 60)
			$street = substr($street, 0, 60);

        return $street;
    }

	/**
	 * @param $str
	 * @return string|null
	 */
	private function ucSentence($str){

		if( !$str )
			return null;

    	if( preg_match("/[a-z]/", $str) )
    		return $str;

	    $str = ucfirst(mb_strtolower($str, 'UTF-8'));
	    $str = preg_replace_callback('/([.!?-])\s*(\w)/', function($matches) { return mb_strtoupper($matches[0], 'UTF-8'); }, $str);
	    $str = stripslashes($str);

	    return $str;
    }
}
