<?php
namespace Esj\Core\Auth;

use Esj\Core\Auth\Models\CostCenter;
use Esj\Core\Auth\Models\User;
use Esj\Core\Config;

class Development extends LdapIisCaixa
{

    public function authenticate(Config $config): bool
    {

        if ($this->_authenticated)
            return true;

        $this->_config = $config;

        $user = User::loadById($_ENV['MASTER_USER'], $config, $this);

        $cs = CostCenter::loadById($user->cost_center, $config, $this);

        $this->_user = $user;
        $this->_cost_center = $cs;

        return $this->_authenticated = true;

    }

    public function loadLDAP(string $login)
    {

    }

}
