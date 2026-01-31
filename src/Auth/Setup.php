<?php
namespace Fluxion\Auth;

use Fluxion\Auth\Models\CostCenter;
use Fluxion\Auth\Models\User;
use Fluxion\Config;

class Setup extends Auth
{

    public function __construct(Config $config = null)
    {

        parent::__construct($config);

        $this->_user = User::loadById($_ENV['MASTER_USER'], $config, $this);
        $this->_cost_center = CostCenter::loadById($this->_user->cost_center ?? CostCenter::GENAG, $config, $this);

        $this->_user->cost_center2 = CostCenter::GENAG;
        $this->_user->login = $_ENV['MASTER_USER'];

    }

    public function authenticate(Config $config): bool
    {

        return true;

    }

    public function hasProfile($profile, $super_user = false): bool
    {

        return true;

    }

    public function hasPermission($model, $type): bool
    {

        return true;

    }

    public function testPermission($model, $type): bool
    {

        return true;

    }

}
