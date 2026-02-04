<?php
namespace Fluxion;

class Config2
{

    private static ?Connector\Connector $connector = null;

    /** @throws CustomException */
    public static function getConnector(): Connector\Connector
    {

        if (is_null(self::$connector)) {

            if (isset($_ENV['DB_TYPE']) && $_ENV['DB_TYPE'] == 'sqlsrv') {

                self::$connector = new Connector\SQLServer2();

            }

            else {

                throw new CustomException('Dados de conexão não encontrados.');

            }

        }

        return self::$connector;

    }

}
