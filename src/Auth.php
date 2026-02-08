<?php
namespace Fluxion;

use Exception;
use Fluxion\Auth\Basic;
use Fluxion\Auth\Development;
use Fluxion\Auth\LdapIisCaixa;
use Fluxion\Auth\Models;
use Fluxion\Auth\Models\CostCenter;
use Fluxion\Auth\Models\PermissionProfile;
use Fluxion\Auth\Models\UserGroup;
use Fluxion\Auth\Models\UserOld;
use Fluxion\Auth\Setup;
use Fluxion\Log\Models as Log;

class Auth
{

    const AUTH_TYPES = ['insert', 'delete', 'update', 'view', 'under', 'special'];

    const AUTH_TYPES_NAMES = [
        'insert' => 'Inserir',
        'delete' => 'Apagar',
        'update' => 'Atualizar',
        'view' => 'Visualizar',
        'under' => 'Vinculadas',
        'special' => 'Especial',
    ];

    const COLORS = [
        'black',
        'red',
        'green',
        'orange',
        'pink',
        'indigo',
        'purple',
        'deep-purple',
        'light-blue',
        'cyan',
        'teal',
        'light-green',
        'lime',
        'yellow',
        'amber',
        'deep-orange',
        'brown',
        'blue-grey',
    ];

    protected $_config;

    protected $_authenticated;

    /** @var UserOld|null */
    protected $_user;
    /** @var CostCenter|null */
    protected $_cost_center;

    public $_user_image_dir = 'user/';

    public static function create(): self
    {

        $config = $GLOBALS['CONFIG'];

        if ($_ENV['AUTH_METHOD'] == 'LDAP_CAIXA') {

            $auth = new LdapIisCaixa($config);

        } elseif ($_ENV['AUTH_METHOD'] == 'DEVELOPMENT') {

            $auth = new Development($config);

        } elseif ($_ENV['AUTH_METHOD'] == 'SETUP') {

            $auth = new Setup($config);

        } elseif ($_ENV['AUTH_METHOD'] == 'BASIC') {

            $auth = new Basic($config);

        } else {

            $auth = new self();

            Application::error('Configurar variável <b>AUTH_METHOD</b>!');

        }

        return $auth;

    }

    public function __construct(Config $config = null)
    {

        if (!is_null($config)) {

            $this->_config = $config;

        }

        $GLOBALS['AUTH'] = $this;

    }

    public function getUser(): ?UserOld
    {
        return $this->_user;
    }

    public function getCostCenter(): ?CostCenter
    {
        return $this->_cost_center;
    }

    public function authenticate(Config $config): bool
    {
        return false;
    }

    public function userImage($login)
    {

        $base_dir = $_ENV['LOCAL_UPLOAD'] ?? '';

        $file = strtolower($this->_user_image_dir . $login . '.jpg');

        if ($login != 'group-empty') {
            $this->saveUserImage($login);
        }

        if (!file_exists($base_dir . $file))
            $file = $this->_user_image_dir . 'user-empty.png';

        header('Content-type: image/jpeg;');
        header('Content-transfer-encoding: binary');
        header('Content-length: ' . filesize($base_dir . $file));

        Application::setCache(60 * 60 * 24 * 7);

        readfile($base_dir . $file);

    }

    public function groups(): array
    {

        $user = $this->_user;
        $config = $this->_config;

        if (!isset($GLOBALS['$groups'])) {

            $GLOBALS['$groups'] = UserGroup::filter('_user', $user->login)
                ->only('_group')
                ->toArray($config, $this);

        }

        return $GLOBALS['$groups'];

    }

    public function profiles(): array
    {

        if (!isset($GLOBALS['$profiles'])) {

            $mn = new MnModel(Models\Profile::class, 'groups');

            $GLOBALS['$profiles'] = $mn->_filter('b', $this->groups())->groupBy('a')->toArray();

        }

        return $GLOBALS['$profiles'];

    }

    public function validateDailyHits($login): void
    {

        $daily_hits = $this->_user->daily_hits ?? 3000;

        if (isset($GLOBALS['LOG'])) {

            $daily_hits /= 10;

            # Dobra o limite na última hora do sistema aberto
            if (intval(date('H')) >= 19) {
                $daily_hits *= 2;
            }

            $q = Log\Log::filter('login', $login)
                ->filter('_insert__gte', MENOS_1_HORA)
                ->filter('method', 'POST')
                ->count('id')
                ->firstOrNew()
                ->total;

            if ($q > $daily_hits) {
                Application::error("Too Many Requests", 429, false, false);
            }

        }

    }

    public function inGroup($group, $super_user = true): bool
    {

        $user = $this->_user;
        //$config = $this->_config;

        if (is_null($user))
            return false;

        if ($super_user && $user->super_user)
            return true;

        $groups = $this->groups();

        if (is_array($group)) {

            return (count(array_intersect($group, $groups)) > 0);

        } else {

            return in_array($group, $groups);

        }

    }

    public function hasProfile($profile, $super_user = false): bool
    {

        $user = $this->_user;

        if (is_null($user))
            return false;

        if ($super_user && $user->super_user)
            return true;

        $profiles = $this->profiles();

        if (is_array($profile)) {

            return (count(array_intersect($profile, $profiles)) > 0);

        } else {

            return in_array($profile, $profiles);

        }

    }

    public function permissions(): array
    {

        if (!isset($GLOBALS['$permissions'])) {

            $GLOBALS['$permissions'] = [];

            /** @var PermissionProfile $perm */
            foreach (PermissionProfile::filter('profile', $this->profiles())->xselect() as $perm) {

                if (!isset($GLOBALS['$permissions'][$perm->permission])) {

                    $GLOBALS['$permissions'][$perm->permission] = [];

                    foreach (self::AUTH_TYPES as $type) {
                        $GLOBALS['$permissions'][$perm->permission][$type] = false;
                    }

                }

                foreach (self::AUTH_TYPES as $type) {

                    if ($perm->$type) {
                        $GLOBALS['$permissions'][$perm->permission][$type] = true;
                    }

                }

            }

        }

        return $GLOBALS['$permissions'];

    }

    public function hasPermission($model, $type): bool
    {

        if (!in_array($type, self::AUTH_TYPES))
            Application::error("Tipo de permissão não encontrado: <b>$type</b>", 610);

        if (is_object($model))
            $model = get_class($model);

        $user = $this->_user;

        if (is_null($user))
            return false;

        if ($user->super_user)
            return true;

        $permissions = $this->permissions();

        if (!isset($permissions[$model])) {
            return false;
        }

        /*$test = PermissionGroup
            ::filter('permission', $model)
            ->filter('_group', $groups)
            ->filter($type, true)
            ->firstOrNew($config, $this);*/

        return $permissions[$model][$type];

    }

    public function testPermission($model, $type): bool
    {

        if (is_object($model))
            $model = get_class($model);

        if ($this->hasPermission($model, $type))
            return true;

        //Application::error("Sistema em manutenção!", 403, false, false);

        Application::error("Acesso negado! <br/>(<b>$model - $type</b>)", 403, false, false);

        return false;

    }

    public function getPermissions($model): array
    {

        $out = [];

        foreach (self::AUTH_TYPES as $type)
            $out[$type] = $this->hasPermission($model, $type);

        return $out;

    }

    public function saveUserImage($login)
    {

    }

    public function userData(string $login): array
    {

        $config = $this->_config;

        $base_dir = $_ENV['LOCAL_UPLOAD'] ?? '';

        if (isset($GLOBALS['userData__' . $login])) {

            return $GLOBALS['userData__' . $login];

        }

        try {

            $user = UserOld::filter('login', $login)->firstOrNew($config, $this);

            $file = $this->_user_image_dir . $login . '.jpg';

            if ($user->initials == '') {

                $arr = explode(' ', $user->name);

                $user->initials = substr($arr[0], 0, 1) . substr($arr[count($arr) - 1], 0, 1);
                $user->color = self::COLORS[random_int(0, count(self::COLORS) - 1)];


                $user->save();

            }

            if (!file_exists($base_dir . $file)) {
                $this->saveUserImage($login);
            }

            $GLOBALS['userData__' . $login] = [
                'id' => $user->login,
                'nome' => $user->name,//$user->nickname,
                'imagem' => ((file_exists($base_dir . $file)) ? $login . '.jpg' : false),
                'letra' => $user->initials,
                'cor' => $user->color
            ];

            return $GLOBALS['userData__' . $login];

        } catch (Exception $e) {

            return [];

        }

    }

    public function costCenterData(string $cost_center): array
    {

        $config = $this->_config;

        if (isset($GLOBALS['costCenter__' . $cost_center])) {

            return $GLOBALS['costCenter__' . $cost_center];

        }

        try {

            $cs = CostCenter::loadById($cost_center, $config, $this);

            $GLOBALS['costCenter__' . $cost_center] = [
                'id' => $cs->id,
                'nome' => $cs->initials ?? $cs->name,
                'imagem' => false,
                'letra' => $cs->type,
                'cor' => 'black',
            ];

            return $GLOBALS['costCenter__' . $cost_center];

        } catch (Exception $e) {

            return [];

        }

    }

    public function getAllCostCenterAccess($cost_center = null)
    {

        return [];

    }

    public function getAllCostCenterAhead($cost_center): array
    {

        return [];

    }

}
