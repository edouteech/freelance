<?php

namespace App;

use Exception;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Exception\LoaderLoadException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;
use function dirname;
use const PHP_VERSION_ID;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private const CONFIG_EXTS = '.{php,xml,yaml,yml}';

	public function getCacheDir()
	{
		if( PHP_SAPI === 'cli' )
			return $this->getProjectDir().'/var/cli/cache';
		else
			return $this->getProjectDir().'/var/www/cache';
	}

	public function getLogDir()
	{
		if( PHP_SAPI === 'cli' )
			return $this->getProjectDir().'/var/cli/log';
		else
			return $this->getProjectDir().'/var/www/log';
	}

    public function registerBundles(): iterable
    {
	    date_default_timezone_set("Europe/Paris");

	    $contents = require $this->getProjectDir().'/config/bundles.php';

	    foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }

	/**
	 * @param ContainerBuilder $container
	 * @param LoaderInterface $loader
	 * @throws Exception
	 */
	protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
	    $container->addResource(new FileResource($this->getProjectDir().'/config/bundles.php'));
	    $container->setParameter('container.dumper.inline_class_loader', PHP_VERSION_ID < 70400 || $this->debug);
	    $container->setParameter('container.dumper.inline_factories', true);
        $confDir = $this->getProjectDir().'/config';

        $loader->load($confDir.'/{packages}/*'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{packages}/'.$this->environment.'/*'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{services}'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{services}_'.$this->environment.self::CONFIG_EXTS, 'glob');
    }

	/**
	 * @param RouteCollectionBuilder $routes
	 * @throws LoaderLoadException
	 */
	protected function configureRoutes(RouteCollectionBuilder $routes): void
    {
        $confDir = $this->getProjectDir().'/config';

        $routes->import($confDir.'/{routes}/'.$this->environment.'/*'.self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir.'/{routes}/*'.self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir.'/{routes}'.self::CONFIG_EXTS, '/', 'glob');
    }
}
