<?php
namespace Fluxion\Auth;

use Fluxion\{Auth, FluxionException};
use Fluxion\Exception\{AuthFluxionException};
use Psr\Http\Message\{RequestInterface};

class Development extends Auth
{

    private string $_env_login = 'SETUP_LOGIN';

    /**
     * @throws AuthFluxionException
     * @throws FluxionException
     */
    public function __construct(?RequestInterface $request)
    {

        parent::__construct($request);

        if (empty($_ENV[$this->_env_login])) {
            throw new AuthFluxionException("Variável '$this->_env_login' não encontrada!");
        }

        $this->_user = new UserModel();
        $this->_user->login = $_ENV[$this->_env_login] ?? 'admin';
        $this->_user->cost_center = 1;

        $this->_authenticated = true;

    }

}
