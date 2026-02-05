<?php
namespace Fluxion;

class Environment
{

    /**
     * @throws CustomException
     */
    public static function load(string $file): void
    {

        # Load environment variables

        if (!file_exists($file)) {
            throw new CustomException("File $file not found.");
        }

        $parsed = parse_ini_file($file, true);

        $_ENV['ENVIRONMENT'] = $_ENV['ENVIRONMENT'] ?? $parsed['ENVIRONMENT'];

        foreach ($parsed[$_ENV['ENVIRONMENT']] as $key => $value) {
            $_ENV[$key] = $value;
        }

        # Error reporting settings

        $error_reporting = $_ENV['ERROR_REPORTING'] ?? 'E_ALL';

        if ($error_reporting == 'E_ERROR') {
            error_reporting(E_ERROR);
        }

        elseif ($error_reporting == 'E_WARNING') {
            error_reporting(E_ERROR | E_WARNING);
        }

        elseif ($error_reporting == 'E_PARSE') {
            error_reporting(E_ERROR | E_WARNING | E_PARSE);
        }

        elseif ($error_reporting == 'E_NOTICE') {
            error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
        }

        elseif ($error_reporting == 'E_ALL') {
            error_reporting(E_ALL);
        }

        if (isset($_ENV['APP_TIMEZONE'])) {
            date_default_timezone_set($_ENV['APP_TIMEZONE']);
        }

        # Language settings

        if (isset($_ENV['APP_LANG'])) {

            $language = explode(';', $_ENV['APP_LANG']);

            switch (count($language)) {

                case 3:
                    setlocale(LC_TIME, $language[0], $language[1], $language[2]);
                    break;

                case 2:
                    setlocale(LC_TIME, $language[0], $language[1]);
                    break;

                default:
                    setlocale(LC_TIME, $language[0]);

            }

        }

    }

}
