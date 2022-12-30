<?php

namespace App\Service;

use Intervention\Image\ImageManagerStatic;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

class ImageResizer extends AbstractService
{
	/**
	 * Edit image using Intervention library
	 *
	 * @param $src
	 * @param array $params
	 * @param null $ext
	 * @param int $compression
	 * @return string
	 */
	public function process($src, $params, $ext=null, $compression=95){

		//redefine ext if webp is not supported
		if( $ext === 'webp' && !function_exists('imagewebp'))
			$ext = null;

		//get size from params
		if( isset($params['resize']) ){

			$params['resize'] = (array)$params['resize'];
			$w = $params['resize'][0];
			$h = count($params['resize'])>1?$params['resize'][1]:0;
		}
		else{

			if( file_exists($src) && $image_size = getimagesize($src) ){
				$w = $image_size[0];
				$h = $image_size[1];
			}
			else{
				$w = 800;
				$h = 600;
			}
		}

		if( isset($params['gcd']) ){

			if( ($w == 0 || $h == 0) && file_exists($src) && $image_size = getimagesize($src) ){

				$ratio = $image_size[0]/$image_size[1];

				if( $w == 0 )
					$w = round($h*$ratio);
				else
					$h = round($w/$ratio);
			}

			$w = round($w/10);
			$h = round($h/10);
		}


		//return placeholder if image is empty
		if( empty($src) || !file_exists($src) )
			return $this->placeholder($w, $h);

		$mime_type = mime_content_type($src);

		//remove focus point if invalid
		if( !isset($params['focus_point']) || !is_array($params['focus_point']) || !isset($params['focus_point']['x'], $params['focus_point']['y']) )
			$params['focus_point'] = false;

		//return if not image
		if( $mime_type != 'image/jpeg' && $mime_type != 'image/png' )
			return $src;

		// get src ext
		$src_ext = pathinfo($src, PATHINFO_EXTENSION);

		// define $dest_ext if not defined
		if( $ext == null )
			$ext = $src_ext;

		// get suffix
		// add width height
		$suffix = '-'.round($w).'x'.round($h);

		// add focus point
		if( $params['focus_point'] )
			$suffix .= '-c-'.round($params['focus_point']['x']).'x'.round($params['focus_point']['y']);

		// add params
		$filtered_params = $params;
		unset($filtered_params['resize']);

		if( count($filtered_params) )
			$suffix .= '-'.substr(md5(json_encode($filtered_params)), 0, 6);

		//add subfolder
		$basename = pathinfo($src, PATHINFO_BASENAME);
		$dest_dir = str_replace($basename, 'resized', $src);

		//append suffix to filename
		$filename = str_replace('.'.$src_ext, $suffix.'.'.$ext, $basename);

		$filesystem = new Filesystem();

		if( !is_dir($dest_dir) )
			$filesystem->mkdir($dest_dir);

		$dest = $dest_dir.'/'.$filename;

		if( file_exists($dest) ){

			if( filemtime($dest) > filemtime($src) )
				return $dest;
			else
				unlink($dest);
		}

		try
		{
			$image = ImageManagerStatic::make($src);

			foreach ($params as $type=>$param){

				$param = (array)$param;

				switch ($type){

					case 'resize':
						$this->crop($image, $w, $h, $params['focus_point']);
						break;

					case 'insert':
						$image->insert($param[0], count($param)>1?$param[1]:'top-left', count($param)>2?$param[2]:0, count($param)>3?$param[3]:0);
						break;

					case 'colorize':
						$image->colorize($param[0], $param[1], $param[2]);
						break;

					case 'blur':
						$image->blur(count($param)?$param[0]:1);
						break;

					case 'flip':
						$image->flip(count($param)?$param[0]:'v');
						break;

					case 'brightness':
						$image->brightness($param[0]);
						break;

					case 'invert':
						$image->invert();
						break;

					case 'mask':
						$image->mask($param[0], count($param)>1?$param[1]:false);
						break;

					case 'gamma':
						$image->gamma($param[0]);
						break;

					case 'rotate':
						$image->rotate($param[0]);
						break;

					case 'text':
						$image->text($param[0], count($param)>1?$param[1]:0, count($param)>2?$param[2]:0, function($font) use($param) {

							$params = count($param)>3?$param[3]:[];

							if( isset($params['file']) )
								$font->file($params['file']);

							if( isset($params['size']) )
								$font->size($params['size']);

							if( isset($params['color']) )
								$font->color($params['color']);

							if( isset($params['align']) )
								$font->align($params['align']);

							if( isset($params['valign']) )
								$font->valign($params['valign']);

							if( isset($params['angle']) )
								$font->angle($params['angle']);
						});

						break;

					case 'pixelate':
						$image->pixelate($param[0]);
						break;

					case 'greyscale':
						$image->greyscale();
						break;

					case 'rectangle':
						$image->rectangle($param[0], $param[1], $param[2], $param[3], function ($draw) use($param) {

							if( count($param) > 4 )
								$draw->background($param[4]);

							if( count($param) > 6 )
								$draw->border($param[5], $param[6]);
						});
						break;

					case 'circle':
						$image->circle($param[0], $param[1], $param[2], function ($draw) use($param) {

							if( count($param) > 3 )
								$draw->background($param[3]);

							if( count($param) > 5 )
								$draw->border($param[4], $param[5]);
						});
						break;

					case 'limitColors':
						$image->limitColors($param[0], count($param)>1?$param[1]:null);
						break;
				}
			}

			$image->save($dest, $compression);

			return $dest;
		}
		catch(Throwable $t)
		{
			return $t->getMessage();
		}
	}


	/**
	 * @param int $w
	 * @param int $h
	 * @return string
	 */
	private function placeholder($w, $h=0){

		$width = $w == 0 ? 1280 : $w;
		$height = $h > 0 ? 'x'.$h : '';

		return 'https://via.placeholder.com/'.$width.$height.'.jpg';
	}


	/**
	 * @param $image
	 * @param $w
	 * @param int $h
	 * @param bool $focus_point
	 * @return void
	 */
	private function crop(&$image, $w, $h=0, $focus_point=false){

		if(!$w){

			$image->resize(null, $h, function ($constraint) {
				$constraint->aspectRatio();
			});
		}
		elseif(!$h){

			$image->resize($w, null, function ($constraint) {
				$constraint->aspectRatio();
			});
		}
		elseif($focus_point){

			$src_width = $image->getWidth();
			$src_height = $image->getHeight();
			$dest_ratio = $w/$h;

			$ratio_height = $src_height/$h;
			$ratio_width = $src_width/$w;

			if( $dest_ratio < 1)
			{
				$dest_width = $w*$ratio_height;
				$dest_height = $src_height;
			}
			else
			{
				$dest_width = $src_width;
				$dest_height = $h*$ratio_width;
			}

			if ($ratio_height < $ratio_width) {

				list($cropX1, $cropX2) = $this->calculateCrop($src_width, $dest_width, $focus_point['x']/100);
				$cropY1 = 0;
				$cropY2 = $src_height;
			} else {

				list($cropY1, $cropY2) = $this->calculateCrop($src_height, $dest_height, $focus_point['y']/100);
				$cropX1 = 0;
				$cropX2 = $src_width;
			}

			$image->crop($cropX2 - $cropX1, $cropY2 - $cropY1, $cropX1, $cropY1);

			$tmp = tempnam("/tmp", "II");

			$image->save($tmp, 100);

			$image = ImageManagerStatic::make($tmp);

			$image->fit($w, $h);
		}
		else{

			$image->fit($w, $h);
		}
	}

	/**
	 * @param $origSize
	 * @param $newSize
	 * @param $focalFactor
	 * @return array
	 */
	private function calculateCrop($origSize, $newSize, $focalFactor) {

		$focalPoint = $focalFactor * $origSize;
		$cropStart = $focalPoint - $newSize / 2;
		$cropEnd = $cropStart + $newSize;

		if ($cropStart < 0) {
			$cropEnd -= $cropStart;
			$cropStart = 0;
		} else if ($cropEnd > $origSize) {
			$cropStart -= ($cropEnd - $origSize);
			$cropEnd = $origSize;
		}

		return array(ceil($cropStart), ceil($cropEnd));
	}
}
