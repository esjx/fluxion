<?php
namespace Fluxion;

use Psr\Http\Message\{RequestInterface};

class Config
{

    private static ?Connector $connector = null;

    /** @throws FluxionException */
    public static function getConnector(): Connector
    {

        if (is_null(self::$connector)) {

            if (isset($_ENV['DB_TYPE']) && $_ENV['DB_TYPE'] == 'sqlsrv') {
                self::$connector = new Connector\SQLServer();
            }

            else {
                throw new FluxionException('Dados de conexão não encontrados.');
            }

        }

        return self::$connector;

    }

    private static ?Auth $auth = null;

    /** @throws FluxionException */
    public static function getAuth(?RequestInterface $request = null): Auth
    {

        if (is_null(self::$auth)) {

            if (isset($_ENV['AUTH_CLASS'])) {

                $class = $_ENV['AUTH_CLASS'];

                if (!class_exists($class)) {
                    throw new FluxionException("Classe '$class' não encontrada!");
                }

                self::$auth = new $class($request);

            }

            else {
                throw new FluxionException('Dados de conexão não encontrados.');
            }

        }

        return self::$auth;

    }

    private static ?string $permission_class = null;

    public static function getPermissionModel(): ?Model
    {

        if (is_null(self::$permission_class)) {
            return null;
        }

        return new self::$permission_class();

    }

    /**
     * @throws FluxionException
     * @noinspection PhpUnused
     */
    public static function setPermissionModel(string $class): void
    {

        if (!class_exists($class)) {
            throw new FluxionException("Classe '$class' não existe!");
        }

        $obj = new $class();

        if (!$obj instanceof Model) {
            throw new FluxionException("Classe '$class' não é um Model!");
        }

        self::$permission_class = $class;

    }

    public static function mapUploadDir(string $local): ?string
    {

        $dir = str_replace(['/', '\\'], '/', $_ENV['LOCAL_UPLOAD'] ?? '');
        $local = str_replace(['/', '\\'], '/', $local);

        if ($dir != '') {
            $dir = preg_replace('/\/$/m', '', $dir) . '/';
        }

        $dir .= preg_replace('/^\/$/m', '', $local);

        $dir = preg_replace('/\/$/m', '', $dir) . '/';

        FileManager::createDir($dir);

        return $dir;

    }

}
