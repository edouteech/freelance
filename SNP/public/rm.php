<?php

function rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
					rrmdir($dir. DIRECTORY_SEPARATOR .$object);
				else
					unlink($dir. DIRECTORY_SEPARATOR .$object);
			}
		}
		rmdir($dir);
	}
}

if( is_dir(__DIR__.'/../var/cache')){

	rrmdir(__DIR__.'/../var/cache');
	echo 'removed var/cache<br/>';
}

if( is_dir(__DIR__.'/../var/log')){

	rrmdir(__DIR__.'/../var/log');
	echo 'removed var/log<br/>';
}

if( is_dir(__DIR__.'/../var/www/cache')){

	rrmdir(__DIR__.'/../var/www/cache');
	echo 'removed var/www/cache<br/>';
}

if( is_dir(__DIR__.'/../deploy-cache')){

	rrmdir(__DIR__.'/../deploy-cache');
	echo 'removed deploy-cache<br/>';
}

if( is_dir(__DIR__.'/../releases')){

	rrmdir(__DIR__.'/../releases');
	echo 'removed releases<br/>';
}

