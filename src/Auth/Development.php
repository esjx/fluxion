<?php
namespace Fluxion\Auth;

use Fluxion\Auth\Models\CostCenter;
use Fluxion\Auth\Models\UserOld;
use Fluxion\Config;

class Development extends LdapIisCaixa
{

    public function authenticate(Config $config): bool
    {

        if ($this->_authenticated)
            return true;

        $this->_config = $config;

        $user = UserOld::loadById($_ENV['MASTER_USER'], $config, $this);

        $cs = CostCenter::loadById($user->cost_center, $config, $this);

        $this->_user = $user;
        $this->_cost_center = $cs;

        return $this->_authenticated = true;

    }

    public function loadLDAP(string $login)
    {

    }

}
