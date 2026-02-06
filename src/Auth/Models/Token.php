<?php
namespace Fluxion\Auth\Models;

use Fluxion\ModelOld;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;

class Token extends ModelOld {

    protected $_verbose_name_plural = 'Autenticação - Tokens';

    protected $_table = 'auth.token';

    protected $_fields = [
        'login' => [
            'type' => 'string',
            'foreign_key' => __NAMESPACE__ . '\User',
            'foreign_key_fake' => true,
            'foreign_key_show' => false,
            'required' => true,
        ],
        'host' => [
            'type' => 'string',
            'required' => true,
        ],
        'active' => [
            'type' => 'boolean',
        ],
    ];

    public function getToken(): string
    {

        return JWT::encode([
            'id' => $this->id,
            'login' => $this->login,
            'host' => $this->host,
        ], $_ENV['APP_HASH']);

    }

    public static function loadFromAuthorizarion($config, $auth)
    {

        $headers = apache_request_headers();

        if (!isset($headers['Authorization']))
            return new self(); // Autorização não enviada

        if (substr($headers['Authorization'], 0, 5) == 'Basic')
            return new self(); // Autorização não enviada

        $hash = $headers['Authorization'];

        try {

            $token = JWT::decode($hash, $_ENV['APP_HASH'], ['HS256']);

            if ($token->host != gethostbyaddr($_SERVER['REMOTE_ADDR']))
                return new self(); // Host diferente do criado junto com o hash

            $test = self
                ::filter('login', $token->login)
                ->filter('id', $token->id)
                ->filter('host', $token->host)
                ->firstOrNew($config, $auth);

            $test->_update = date('Y-m-d H:i:s');

            if ($test->active == true)
                return $test; // VALIDADO

        } catch (SignatureInvalidException $e) {

            return new self(); // Falha na assinatura do HASH

        }

        return new self(); // Não localizado no banco, ou não ativa

    }

}
