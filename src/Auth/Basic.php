<?php
namespace Fluxion\Auth;

use Fluxion\Application;
use Fluxion\Auth\Models\CostCenter;
use Fluxion\Auth\Models\User;
use Fluxion\Config;
use Fluxion\Sql;

class Basic extends Auth
{

    public function __construct(Config $config = null)
    {

        parent::__construct($config);

        $auth = Application::getRequestHeaders()['Authorization'] ?? null;

        if (is_null($auth)) {
            header('WWW-Authenticate: Basic realm="Intranet"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Não logado!';
            exit;
        }

        $basic = explode(' ', $auth);

        $partes = explode(':', base64_decode($basic[1] ?? ''));

        $usuario = $partes[0] ?? '';
        $senha = $partes[1] ?? '';

        $user = User::loadById($usuario, $config, $this);

        if (is_null($user->login)) {
            Application::error("Usuário $usuario não encontrado!", 401);
        }

        if (!password_verify($senha, $user->password)) {
            Application::error('Senha incorreta!', 401);
        }

        if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
            $user->password = password_hash($senha, PASSWORD_DEFAULT);
            $user->save();
        }

        $this->_user = $user;
        $this->_cost_center = CostCenter::loadById($user->cost_center, $config, $this);

        $this->validateDailyHits($user->login);

    }

    public function authenticate(Config $config): bool
    {
        return true;
    }

    public function getAllCostCenterAccess($cost_center = null)
    {

        if ($cost_center == null && isset($GLOBALS['__getAllCostCenterAccess'])) {
            return $GLOBALS['__getAllCostCenterAccess'];
        }

        //$config = $this->_config;

        if (is_null($cost_center)) {

            $list = [$this->getUser()->cost_center];

            if (!is_null($this->getUser()->cost_center2)) {
                $list[] = $this->getUser()->cost_center2;
            }

            if (!is_null($this->getUser()->cost_center3)) {
                $list[] = $this->getUser()->cost_center3;
            }

            if ($this->getCostCenter()->type == 'DI') {
                $list[] = $this->getCostCenter()->subordination3;
            }

            if (in_array(CostCenter::GETAT, $list)) {
                $list[] = CostCenter::VIVAR;
            }

        } elseif (is_array($cost_center)) {

            $list = $cost_center;

        } else {

            $list = [$cost_center];

        }

        $list = array_unique($list);

        $list = CostCenter::filter(Sql::_or([
            Sql::filter('id', $list),
            Sql::filter('subordination', $list),
            Sql::filter('subordination2', $list),
            Sql::filter('subordination3', $list),
            Sql::filter('subordination4', $list),
            Sql::filter('subordination0', $list),
            Sql::filter('subordination1', $list),
        ]))->only('id');

        /*for ($i = 1; $i <= 5; $i++) {

            $c = count($list);

            $inc = CostCenter::filter('subordination', $list)
                ->only('id')
                ->toArray($config, $this);

            $list = array_unique(array_merge($list, $inc));

            if ($c == count($list)) {
                break;
            }

        }

        if (count($list) == 1
            && $list[0] == $this->getUser()->cost_center
            && $this->getCostCenter()->type == 'GN') {

            $unidade_atual = $this->getCostCenter();

            while (!in_array($unidade_atual->type, ['SN', 'VP'])) {

                $unidade_atual = CostCenter::loadById($unidade_atual->subordination, $config, $this);

            }

            $list = $this->getAllCostCenterAccess($unidade_atual->id);

        }*/

        if ($cost_center == null) {
            $GLOBALS['__getAllCostCenterAccess'] = $list;
        }

        return $list;

    }

}
