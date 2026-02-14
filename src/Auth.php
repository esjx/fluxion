<?php
namespace Fluxion;

use Fluxion\Auth\UserModel;
use Fluxion\Exception\{AuthException};
use Psr\Http\Message\{RequestInterface};

abstract class Auth
{

    protected string $_env_model = 'AUTH_USER_MODEL';

    /**
     * @throws AuthException
     */
    public function __construct(?RequestInterface $request)
    {

        if (empty($_ENV[$this->_env_model])) {
            throw new AuthException("Variável '$this->_env_model' não encontrada!");
        }

    }

    protected ?UserModel $_user = null;

    public function getUser(): ?UserModel
    {
        return $this->_user;
    }

    protected bool $_authenticated = false;

    /** @noinspection PhpUnused */
    public function isAuthenticated(): bool
    {
        return $this->_authenticated;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function hasPermission(Model $model, Permission $permission): bool
    {
        return true;
    }

}
