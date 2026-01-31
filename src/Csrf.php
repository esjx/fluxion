<?php
namespace Fluxion;

//use Attribute;
use Exception;

//#[Attribute]
class Csrf
{

    const COOKIE_NAME = 'XSRF-TOKEN';

    public static function createToken()
    {

        self::generateToken();

        self::sendToken();

    }

    public static function generateToken()
    {

        try {

            if (empty($_SESSION['token'])) {

                $_SESSION['token'] = bin2hex(random_bytes(35));

            }

        } catch (Exception $e) {

            Application::error('Erro ao gerar o token CSRF!');

        }

    }

    public static function sendToken()
    {

        setcookie(self::COOKIE_NAME, $_SESSION['token'], [
            'expires' => time() + 3600 * 24 * 365,
            'path' => '/',
            //'domain' => 'agro.caixa',
            //'secure' => false,
            //'httponly' => true,
            'samesite' => 'Strict' // None|Lax|Strict
        ]);

    }

    public static function getToken(): string
    {

        $headers = Application::getRequestHeaders();

        return $headers['X-Xsrf-Token'] ?? $headers['X-XSRF-TOKEN'] /*?? $_COOKIE[self::COOKIE_NAME]*/ ?? '#ERRO';

    }

    public static function verifyToken()
    {

        if (self::getToken() != $_SESSION['token']) {

            Application::error('Erro na autenticação! <br><br>Atualize a página e realize uma nova tentativa!' . print_r(Application::getRequestHeaders(), true), 500, false, false);

        }

    }

}
