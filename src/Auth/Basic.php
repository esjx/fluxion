<?php
namespace Fluxion\Auth;

use Fluxion\{Auth, Exception};
use Fluxion\Exception\AuthException;
use Fluxion\Database\Field\PasswordField;
use Psr\Http\Message\{RequestInterface};

class Basic extends Auth
{

    /**
     * @throws Exception
     */
    public function __construct(RequestInterface $request)
    {

        parent::__construct($request);

        $auth = $request->getHeader('Authorization')[0] ?? null;

        $model = $_ENV[$this->_env_model];

        if (!is_null($auth)) {

            $basic = explode(' ', $auth);

            $parts = explode(':', base64_decode($basic[1] ?? ''));

            $login = $parts[0] ?? '';
            $password = $parts[1] ?? '';

            /** @var UserModel $user */
            $user = $model::loadById($login);

            if (is_null($user->login)) {
                throw new AuthException("Usuário '$login' não encontrado!");
            }

            /** @var PasswordField $password_field */
            $password_field = $user->getField('password');

            if (!$password_field->validadePassword($password)) {
                throw new AuthException('Senha incorreta!');
            }

            $this->_user = $user;
            $this->_authenticated = true;

        }

        else {

            /** @var UserModel $user */
            $user = new $model();

            $this->_user = $user;
            $this->_authenticated = false;

            /*header('WWW-Authenticate: Basic realm="Intranet"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Não logado!';
            exit;*/

        }

    }

}
