<?php

namespace App\Service;

use Exception;

/**
 * @property array options
 * @property resource resource
 */
class FtpService
{

    public $options;
    public $resource = null;

    /**
     * Ftp constructor.
     *
     * @param array $options
     *
     * @throws Exception if we cannot connect to the ftp
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'sftp' => false, 'login' => null, 'password' => null,
            'host' => null,  'port' => 21,   'timeout' => 10,
        ], $options);
    }

    /**
     * Connects to the ftp
     *
     * @throws Exception
     */
    protected function connect()
    {
        $options = $this->options;

        if (!$options['sftp']) {

            $this->resource = ftp_connect($options['host'], $options['port'], $options['timeout']);

            if (!$this->resource) {
                throw new Exception("Impossible de se connecter au serveur FTP avec l'hôte : '{$options['host']}', port : '{$options['port']}' et timeout : '{$options['timeout']}'");
            }

            if (! @ftp_login($this->resource, $options['login'], $options['password'])) {
                throw new Exception("Impossible de s'authentifier au serveur FTP avec l'hôte : '{$options['host']}', port : '{$options['port']}' et l'utilisateur : '{$options['login']}'");
            }

            // go into passive mode
            if (! @ftp_pasv($this->resource, true)) {
                throw new Exception("Impossible de passer en mode passif avec le serveur FTP hôte : '{$options['host']}', port '{$options['port']}' et l'utilisateur : '{$options['login']} ");
            }

        } else {

            throw new Exception("le SFTP n'est pas implémenté");

        }
    }

	/**
	 * @param string $directory
	 *
	 * @return array
	 * @throws Exception
	 */
    public function ls(string $directory = '.'): array
    {
        if (!$this->resource) {
            $this->connect();
        }

        $files = ftp_nlist($this->resource, $directory);

        return $files ? $files : [];
    }

	/**
	 * @param string $filename
	 *
	 * @return bool
	 * @throws Exception
	 */
    public function get(string $filename)
    {
        if (!$this->resource)
            $this->connect();

        $targetFilepathAndName = sys_get_temp_dir() . '/' . pathinfo($filename, PATHINFO_BASENAME);

        return ftp_get($this->resource, $targetFilepathAndName, $filename, FTP_BINARY) ? $targetFilepathAndName : false;
    }

	/**
	 * @param string $remoteFilepath
	 * @param string $localFilepath
	 * @return bool
	 * @throws Exception
	 */
    public function put(string $remoteFilepath, string $localFilepath)
    {
        if (!$this->resource) {
            $this->connect();
        }

        return ftp_put($this->resource, $remoteFilepath, $localFilepath, FTP_BINARY) ? $localFilepath : false;
    }

    /**
     * Close ftp on destroy
     */
    public function __destruct()
    {
        if (is_resource($this->resource)) {
            ftp_close($this->resource);
        }
    }
}
