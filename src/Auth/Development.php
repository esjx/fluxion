<?php
namespace Fluxion\Auth;

use Fluxion\{Auth, Exception};
use Fluxion\Exception\{AuthException};
use Psr\Http\Message\{RequestInterface};

class Development extends Auth
{

    private string $_env_login = 'SETUP_LOGIN';

    /**
     * @throws AuthException
     * @throws Exception
     */
    public function __construct(?RequestInterface $request)
    {

        parent::__construct($request);

        if (empty($_ENV[$this->_env_login])) {
            throw new AuthException("Variável '$this->_env_login' não encontrada!");
        }

        $this->_user = new UserModel();
        $this->_authenticated = true;

    }

}
