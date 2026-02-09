<?php
namespace Fluxion;

use Psr\Http\Message\{RequestInterface};

class Config
{

    private static ?Connector $connector = null;

    /** @throws Exception */
    public static function getConnector(): Connector
    {

        if (is_null(self::$connector)) {

            if (isset($_ENV['DB_TYPE']) && $_ENV['DB_TYPE'] == 'sqlsrv') {
                self::$connector = new Connector\SQLServer();
            }

            else {
                throw new Exception('Dados de conexão não encontrados.');
            }

        }

        return self::$connector;

    }

    private static ?Auth $auth = null;

    /** @throws Exception */
    public static function getAuth(RequestInterface $request): Auth
    {

        if (is_null(self::$auth)) {

            if (isset($_ENV['AUTH_CLASS'])) {

                $class = $_ENV['AUTH_CLASS'];

                if (!class_exists($class)) {
                    throw new Exception("Classe '$class' não encontrada!");
                }

                self::$auth = new $class($request);

            }

            else {
                throw new Exception('Dados de conexão não encontrados.');
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
     * @throws Exception
     */
    public static function setPermissionModel(string $class): void
    {

        if (class_exists(!$class)) {
            throw new Exception("Classe '$class' não existe!");
        }

        $obj = new $class();

        if (!$obj instanceof Model) {
            throw new Exception("Classe '$class' não é um Model!");
        }

        self::$permission_class = $class;

    }

}
