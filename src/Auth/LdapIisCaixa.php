<?php
namespace Fluxion\Auth;

use DateTime;
use Exception;
use Fluxion\Csrf;
use Fluxion\Application;
use Fluxion\Auth\Models\CostCenter;
use Fluxion\Auth\Models\Occupation;
use Fluxion\Auth\Models\Rule;
use Fluxion\Auth\Models\User;
use Fluxion\Auth\Models\UserGroup;
use Fluxion\Config;
use Fluxion\ImageManipulate;
use Fluxion\Sql;
use Fluxion\Util;

class LdapIisCaixa extends Auth
{

    public $_user_image_dir = 'user/';

    protected $_groups = [];

    public function authenticate(Config $config): bool
    {

        if ($this->_authenticated)
            return true;

        $this->_config = $config;

        if (isset($_SERVER['REMOTE_USER'])) {

            $login = strtolower($_SERVER['REMOTE_USER']);

            $login = preg_replace('/^CORPCAIXA\\\\/i', '', $login);

            if ($login == 's7436506') {
                $login = 's578101';
            }

            if ($login == 'c098422' && isset($_SESSION['ACESSO_SIMULADO'])) {
                $login = $_SESSION['ACESSO_SIMULADO'];
            }

            $this->loadLDAP($login);
			
			if (is_null($this->_user)) {
				Application::error('Usuário não encontrado!');
			}

            if (is_null($this->_user->token)) {

                Csrf::generateToken();

                $this->_user->token = $_SESSION['token'];
                $this->_user->save();

            } else {

                $_SESSION['token'] = $this->_user->token;

            }

            if (isset($_ENV['ALLOWED_USERS'])) {

                $allowed = explode(';', $_ENV['ALLOWED_USERS']);

                if (!in_array($login, $allowed)) {

                    if (isset($_ENV['ENVIRONMENT_TITLE'])) {

                        Application::error("Usuário não autorizado no ambiente <b>{$_ENV['ENVIRONMENT_TITLE']}</b>!");

                    } else {

                        Application::error('Usuário não autorizado neste ambiente!');

                    }

                }

            }

            if (isset($_ENV['ALLOWED_PROFILERS'])) {

                $allowed = explode(';', $_ENV['ALLOWED_PROFILERS']);

                if (!$this->hasProfile($allowed)) {

                    Application::redirect('https://concessao.agro.caixa' . $_SERVER['REQUEST_URI']);

                    if (isset($_ENV['ENVIRONMENT_TITLE'])) {

                        Application::error("Usuário não autorizado no ambiente <b>{$_ENV['ENVIRONMENT_TITLE']}</b>!");

                    } else {

                        Application::error('Usuário não autorizado neste ambiente!');

                    }

                }

            }

            $this->validateDailyHits($login);

        } else {

            Application::error('Usuário não localizado');

        }

        return $this->_authenticated;

    }

    public function ldapUserData($login): ?array
    {

        $lc = ldap_connect('10.123.8.180', 389);

        $user = $_ENV['SERVICE_USER'] ?? '';
        $pass = $_ENV['SERVICE_PASS'] ?? '';

        if ($lc && ldap_bind($lc, "corpcaixa\\$user", $pass)) {

            $ls = ldap_search($lc, "OU=CAIXA,DC=corp,DC=caixa,DC=gov,DC=br", "(sAMAccountName=$login)");
            $lge = ldap_get_entries($lc, $ls);

            $arr = [];

            if (!isset($lge[0])) {
                return null;
            }

            foreach ($lge[0] as $key => $value) {

                if (!is_numeric($key) && is_array($value)) {

                    if (count($value) == 2) {

                        $arr[$key] = $value[0];

                    } else {

                        $arr[$key] = [];

                        for ($i = 0; $i < $value['count']; $i++) {

                            $arr[$key][] = $value[$i];

                        }

                    }

                }

            }

            return $arr;

        }

        return null;

    }

    public function loadLDAP(string $login)
    {

        $config = $this->_config;

        $user = User::filter('login', $login)->firstOrNew($config, $this);

        try {

            $lc = ldap_connect('ldap://ldapcluster.corecaixa:489');
            ldap_set_option($lc, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($lc, LDAP_OPT_REFERRALS, 0);

            $limite = (new DateTime())->modify('-1 hour')->format('Y-m-d H:i:s');

            if ((is_null($user->login) || $user->_update < $limite)
                && $lc
                && @ldap_bind($lc)) {

                $ls = ldap_search($lc, "ou=People,o=caixa", "(uid=$login)");
                $lge = ldap_get_entries($lc, $ls);

                if ($login == 's578101') {

                    $user_full_name = 'Portal Agro';
                    $occupation = null;
                    $occupation_name = null;
                    $office = null;
                    $cost_center = 5551;
                    $cost_center2 = 5551;
                    /*$cost_center_name = 'GN FABRICA E OPERACOES DO AGRO';
                    $subordination = 5400;*/
                    $email = 'gefoa07@caixa.gov.br';
                    $dt_nascimento = null;

                } elseif (isset($lge[0])) {

                    $user_full_name = ucwords(strtolower($lge[0]['no-usuario'][0]));
                    $occupation = $lge[0]['nu-funcao'][0] ?? null;
                    $occupation_name = $lge[0]['no-funcao'][0] ?? null;
                    $office = $lge[0]['co-cargo'][0] ?? null;
                    $cost_center = $lge[0]['nu-lotacaofisica'][0] ?? $lge[0]['co-unidade'][0];
                    $cost_center2 = $lge[0]['co-unidade'][0];
                    /*$cost_center_name = $lge[0]['no-lotacaofisica'][0];
                    $subordination = $lge[0]['nu-unidade-sub'][0];*/
                    $email = $lge[0]['mail'][0] ?? '';

                    if (isset($lge[0]['dt-nascimento'])) {
                        $dt_nascimento = date_create_from_format('d/m/Y', $lge[0]['dt-nascimento'][0])->format('Y-m-d');
                    } else {
                        $dt_nascimento = null;
                    }

                } else {

                    $this->_user = null;

                    return;

                }

                ldap_close($lc);

                if (!is_null($occupation) && !is_null($occupation_name)) {

                    $oc = Occupation::filter('id', $occupation)->firstOrNew($config, $this);

                    if (is_null($oc->name)) {

                        $oc->id = $occupation;
                        $oc->name = $occupation_name;

                        $oc->save();

                    }

                }

                $cs = CostCenter::filter('id', $cost_center)->firstOrNew($config, $this);

                /*$cs->id = $cost_center;
                $cs->subordination = $subordination;
                $cs->name = $cost_center_name;
                $cs->type = '';

                $cs->save();*/

                $cargos = [
                    'TB' => 1,
                    'TBN' => 1,
                    'TBS' => 1,
                    'TBSN' => 1,
                    'ESC' => 1,
                    'ESP' => 1,
                    'VPRES' => 1,
                    'ENGAR8' => 2,
                    'ENGCI8' => 3,
                    'ADV8H' => 4,
                    'EST' => 9,
                ];

                if (substr($login, 0, 1) == 'e') {
                    $office = 'EST';
                }

                /*if (!isset($cargos[$office])) {
                    echo $office;
                }*/

                if (!is_null($cost_center)) {

                    $user->cost_center = $cost_center;
                    $user->cost_center2 = $cost_center2;
                    $user->login = $login;
                    $user->email = $email;
                    $user->name = $user_full_name;
                    $user->birthday = $dt_nascimento;
                    $user->occupation = $occupation;
                    $user->office = (isset($cargos[$office])) ? $cargos[$office] : null;

                }

                if (is_null($user->initials) || $user->nickname == '') {

                    $arr = explode(' ', $user->name);
                    $user->first_name = $arr[0];
                    $user->last_name = $arr[count($arr) - 1];
                    $user->nickname = $user->first_name . ' ' . $user->last_name;
                    $user->initials = substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1);
                    $user->color = self::COLORS[random_int(0, count(self::COLORS) - 1)];

                }

                $user->setChanged(true);
                $user->save();

                $this->_user = $user;
                $this->_cost_center = $cs;

                if (!$user->active) {

                    Application::error('Usuário bloqueado!', 601);

                }

                $this->updateGroups($login);

                $this->_authenticated = true;

            } else {

                //Application::error('Erro ao autenticar o usuário!', 602);

                $cs = CostCenter::filter('id', $user->cost_center)->firstOrNew($config, $this);

                $this->_user = $user;
                $this->_cost_center = $cs;

                if (!$user->active) {

                    Application::error('Usuário bloqueado!', 601);

                }

                $this->_authenticated = true;

                if (is_null($user->login)) {

                    $user->login = $login;

                    Application::error('Usuário não encontrado no servidor LDAP!', 602);

                }

            }

        } catch (Exception $e) {

            Application::error($e->getMessage());

        }

    }

    public function teste(string $login)
    {

        try {

            $lc = ldap_connect('ldap://ldapcluster.corecaixa:489');
            ldap_set_option($lc, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($lc, LDAP_OPT_REFERRALS, 0);

            if ($lc) {

                ldap_bind($lc);
                $ls = ldap_search($lc, "ou=People,o=caixa", "(uid=$login)");
                $lge = ldap_get_entries($lc, $ls);

                echo '<pre>';

                print_r($lge);

            } else {

                Application::error('Usuário não encontrado no servidor LDAP!', 602);

            }

        } catch (Exception $e) {

            Application::error($e->getMessage());

        }

    }

    public function updateGroups(string $login)
    {

        $config = $this->_config;

        // Recupera do banco os grupos que o usuário está atualmente

        $this->_groups = array_unique(UserGroup::filter('_user', $login)
            ->filter('_type', UserGroup::AUTOMATICO)
            ->only('_group')
            ->toArray($config, $this));

        // Verifica grupos potenciais, para que não seja necessário o teste em todos

        $groups_0 = Rule::filter('_type', Rule::TODOS)
            ->groupBy('_group')
            ->toArray($config, $this);

        $groups_1 = Rule::filter('_type', Rule::LOTACAO_FISICA)
            ->filter('description__like', '%' . sprintf("%04d", $this->_user->cost_center) . '%')
            ->groupBy('_group')
            ->toArray($config, $this);

        $groups_2 = Rule::filter('_type', Rule::LOTACAO_ADMINISTRATIVA)
            ->filter('description__like', '%' . sprintf("%04d", $this->_user->cost_center2) . '%')
            ->groupBy('_group')
            ->toArray($config, $this);

        $groups_3 = Rule::filter('_type', Rule::LOTACAO_FISICA_OU_ADMINISTRATIVA)
            ->filter(Sql::_or([
                Sql::filter('description__like', '%' . sprintf("%04d", $this->_user->cost_center) . '%'),
                Sql::filter('description__like', '%' . sprintf("%04d", $this->_user->cost_center2) . '%'),
            ]))
            ->groupBy('_group')
            ->toArray($config, $this);

        $groups_4 = Rule::filter('_type', Rule::FUNCAO)
            ->filter('description__like', '%' . sprintf("%04d", $this->_user->occupation) . '%')
            ->groupBy('_group')
            ->toArray($config, $this);

        $groups_5 = Rule::filter('_type', Rule::SUBORDINACAO)
            ->filter('description__like', '%' . sprintf("%04d", $this->_cost_center->subordination) . '%')
            ->groupBy('_group')
            ->toArray($config, $this);

        $groups_6 = Rule::filter('_type', Rule::CARGO)
            ->filter('description__like', '%' . sprintf("%04d", $this->_user->office) . '%')
            ->groupBy('_group')
            ->toArray($config, $this);

        $groups_7 = Rule::filter('_type', Rule::SUPERINTENDENCIA_NACIONAL)
            ->filter('description__like', '%' . sprintf("%04d", $this->_cost_center->subordination2) . '%')
            ->groupBy('_group')
            ->toArray($config, $this);

        $groups_8 = Rule::filter('_type', Rule::VICE_PRESIDENCIA)
            ->filter('description__like', '%' . sprintf("%04d", $this->_cost_center->subordination3) . '%')
            ->groupBy('_group')
            ->toArray($config, $this);

        // Unifica os grupos e testa a permissão do usuário em todos os possíveis

        $groups = array_unique(array_merge(
            $this->_groups, $groups_0, $groups_1, $groups_2, $groups_3,
            $groups_4, $groups_5, $groups_6, $groups_7, $groups_8
        ));

        foreach ($groups as $group) {

            $this->autoGroup($login, $group);

        }

        // Apaga os grupos que eventualmente o usuário não mais faz parte

        if (count($this->_groups) > 0) {

            UserGroup::filter('_user', $login)
                ->filter('_type', UserGroup::AUTOMATICO)
                ->filter('_group', $this->_groups)
                ->delete($config, $this);

        }

        // Apaga os grupos manuais expirados

        UserGroup::filter('_user', $login)
            ->filter('_type', UserGroup::MANUAL)
            ->filter('expires__lt', date('Y-m-d'))
            ->delete($config, $this);

    }

    public function autoGroup(string $login, int $group)
    {

        $config = $this->_config;

        $rules = [];

        foreach (Rule::filter('_group', $group)->orderBy('and_id')->xselect($config, $this) as $rule) {

            if (!isset($rules[$rule->and_id])) {
                $rules[$rule->and_id] = true;
            }

            switch ($rule->_type) {

                case Rule::TODOS:

                    $rules[$rule->and_id] = true;
                    break 2;

                case Rule::LOTACAO_FISICA:

                    if (strpos($rule->description, sprintf("%04d", $this->_user->cost_center)) === false) {
                        $rules[$rule->and_id] = false;
                    } elseif (is_null($rule->and_id)) {
                        $rules[$rule->and_id] = true;
                        break 2;
                    }

                    break;

                case Rule::LOTACAO_ADMINISTRATIVA:

                    if (strpos($rule->description, sprintf("%04d", $this->_user->cost_center2)) === false) {
                        $rules[$rule->and_id] = false;
                    } elseif (is_null($rule->and_id)) {
                        $rules[$rule->and_id] = true;
                        break 2;
                    }

                    break;

                case Rule::LOTACAO_FISICA_OU_ADMINISTRATIVA:

                    if (strpos($rule->description, sprintf("%04d", $this->_user->cost_center)) === false
                        && strpos($rule->description, sprintf("%04d", $this->_user->cost_center2)) === false) {
                        $rules[$rule->and_id] = false;
                    } elseif (is_null($rule->and_id)) {
                        $rules[$rule->and_id] = true;
                        break 2;
                    }

                    break;

                case Rule::FUNCAO:

                    if (is_null($this->_user->occupation)
                        || strpos($rule->description, sprintf("%04d", $this->_user->occupation)) === false) {
                        $rules[$rule->and_id] = false;
                    } elseif (is_null($rule->and_id)) {
                        $rules[$rule->and_id] = true;
                        break 2;
                    }

                    break;

                case Rule::SUBORDINACAO:

                    if (is_null($this->_cost_center->subordination)
                        || strpos($rule->description, sprintf("%04d", $this->_cost_center->subordination)) === false) {
                        $rules[$rule->and_id] = false;
                    } elseif (is_null($rule->and_id)) {
                        $rules[$rule->and_id] = true;
                        break 2;
                    }

                    break;

                case Rule::CARGO:

                    if (is_null($this->_user->office)
                        || strpos($rule->description, sprintf("%04d", $this->_user->office)) === false) {
                        $rules[$rule->and_id] = false;
                    } elseif (is_null($rule->and_id)) {
                        $rules[$rule->and_id] = true;
                        break 2;
                    }

                    break;

                case Rule::SUPERINTENDENCIA_NACIONAL:

                    if (is_null($this->_cost_center->subordination2)
                        || strpos($rule->description, sprintf("%04d", $this->_cost_center->subordination2)) === false) {
                        $rules[$rule->and_id] = false;
                    } elseif (is_null($rule->and_id)) {
                        $rules[$rule->and_id] = true;
                        break 2;
                    }

                    break;

                case Rule::VICE_PRESIDENCIA:

                    if (is_null($this->_cost_center->subordination3)
                        || strpos($rule->description, sprintf("%04d", $this->_cost_center->subordination3)) === false) {
                        $rules[$rule->and_id] = false;
                    } elseif (is_null($rule->and_id)) {
                        $rules[$rule->and_id] = true;
                        break 2;
                    }

                    break;

            }

        }

        foreach ($rules as $pass) {

            if ($pass) {

                if (in_array($group, $this->_groups)) {

                    // Remove da lista de grupos caso o usuário já esteja nele

                    unset($this->_groups[array_search($group, $this->_groups)]);

                } else {

                    // Insere o usuário no grupo

                    $ug = new UserGroup($config, $this);

                    $ug->_type = UserGroup::AUTOMATICO;
                    $ug->_user = $login;
                    $ug->_group = $group;

                    $ug->save();

                }

                return;

            }

        }

    }

    public function saveUserImage($login)
    {

        $base_dir = $_ENV['LOCAL_UPLOAD'] ?? '';

        try {

            $file = strtolower($this->_user_image_dir . $login . '.jpg');

            $file_original = strtolower($this->_user_image_dir . 'original/' . $login . '.jpg');

            // http://tdv.caixa/img/c149439.jpg?timestamp=1746797621162
            Util::downloadFileFromUrl('http://tdv.caixa/img/' . $login . '.jpg?timestamp=' . time(), $base_dir . $file_original, false);

            if (file_exists($base_dir . $file_original)) {

                ImageManipulate::createThumbFromJpeg($base_dir . $file_original, $base_dir . $file);
                return;

            }

            $arr = $this->ldapUserData($login);
            $dados = $arr['thumbnailphoto'] ?? '';

            if (!empty($dados)) {

                ImageManipulate::createThumbFromString($dados, $base_dir . $file, 250, 250);
                return;

            }

            $dados = Util::capturaConteudoUrl('https://novoredevarejo.caixa/api/administrativo-free/funcionarios/avatar/' . $login, false);

            if ($dados && strpos($dados, 'Object Moved') === false && strpos($dados, 'Not Found') === false) {

                ImageManipulate::createThumbFromString($dados, $base_dir . $file);
                return;

            }

        } catch (Exception $e) {}

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

            if (in_array(CostCenter::GESIT, $list)) {
                $list[] = CostCenter::VINAT;
            }

        } elseif (is_array($cost_center)) {

            $list = $cost_center;

        } else {

            $list = [$cost_center];

        }

        $list = array_unique($list);

        $filters = [
            Sql::filter('id', $list),
            Sql::filter('subordination', $list),
            Sql::filter('subordination2', $list),
            Sql::filter('subordination3', $list),
            Sql::filter('subordination4', $list),
            Sql::filter('subordination0', $list),
            Sql::filter('subordination1', $list),
        ];

        /*if (in_array(CostCenter::GETAT, $list)) {

            $filters[] = Sql::filter('subordination2', [
                CostCenter::SURVA,
                CostCenter::SURVB,
                CostCenter::SURVC,
            ]);

        }*/

        $list = CostCenter::filter(Sql::_or($filters))->only('id');

        if ($cost_center == null) {
            $GLOBALS['__getAllCostCenterAccess'] = $list;
        }

        return $list;

    }

    public function getAllCostCenterAhead($cost_center): array
    {

        $config = $this->_config;

        $list = [$cost_center];

        for ($i = 1; $i <= 10; $i++) {

            $c = count($list);

            $ahead = CostCenter::loadById($cost_center, $config, $this, true);

            $cost_center = $ahead->subordination;

            $list = array_unique(array_merge($list, [$cost_center]));

            if ($c == count($list)) {
                break;
            }

        }

        return $list;

    }

}
