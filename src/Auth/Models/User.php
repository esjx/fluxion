<?php
namespace Fluxion\Auth\Models;

use Fluxion\Model;
use Fluxion\Util;

class User extends Model
{

    protected $_verbose_name = 'Usuário';

    protected $_field_id = 'login';

    protected $_field_id_ai = false;

    protected $_table = 'auth._user';

    protected $_default_cache = true;

    protected $_order = [
        ['name', ASC],
    ];

    public function changeState($state): void
    {

        $var = ($state == 0);

        $this->_fields['nickname']['protected'] = $var;
        $this->_fields['first_name']['protected'] = $var;
        $this->_fields['last_name']['protected'] = $var;
        //$this->_fields['user_update']['protected'] = $var;
        $this->_fields['email']['protected'] = $var;

    }

    public $login = [
        'type' => 'string',
        'search' => true,
        'required' => true,
        'minlength' => 7,
        'maxlength' => 7,
        'size' => 4,
        'label' => 'Matrícula',
        'mask' => 'A999999',
        'placeholder' => 'c______',
    ];

    public $token = [
        'type' => 'string',
        'protected' => true,
        'maxlength' => 80,
    ];

    public $name = [
        'type' => 'string',
        'search' => true,
        'required' => true,
        'label' => 'Nome Completo',
        'readonly' => true,
        'size' => 8,
    ];

    public $nickname = [
        'type' => 'string',
        'search' => true,
        'label' => 'Apelido',
        'size' => 4,
    ];

    public $first_name = [
        'type' => 'string',
        'search' => true,
        'label' => 'Nome',
        'size' => 4,
    ];

    public $last_name = [
        'type' => 'string',
        'search' => true,
        'label' => 'Sobrenome',
        'size' => 4,
    ];

    public $initials = [
        'type' => 'string',
        'label' => 'Iniciais',
        'maxlength' => 2,
        'size' => 4,
    ];

    public $mobile_phone = [
        'type' => 'string',
        'label' => 'Celular',
        'mask' => '+55 99 99999-9999',
        'size' => 4,
    ];

    public $birthday = [
        'type' => 'date',
        'label' => 'Nascimento',
        'readonly' => true,
        'size' => 4,
    ];

    public $email = [
        'type' => 'string',
        'search' => true,
        'label' => 'E-mail',
        'readonly' => true,
        'size' => 12,
    ];

    public $color = [
        'type' => 'colors',
        'label' => 'Cor',
        'maxlength' => 15,
        'choices' => self::COLORS,
        'size' => 12,
    ];

    public $occupation = [
        'type' => 'integer',
        'label' => 'Função',
        'foreign_key' => __NAMESPACE__ . '\Occupation',
        'foreign_key_fake' => true,
        'readonly' => true,
        'size' => 6,
    ];

    public $office = [
        'type' => 'integer',
        'label' => 'Cargo',
        'choices' => [
            1 => 'TÉCNICO',
            2 => 'ENGENHEIRO AGRO',
            3 => 'ENGENHEIRO CIVIL',
            9 => 'ESTAGIÁRIO',
        ],
        'readonly' => true,
        'size' => 6,
    ];

    public $cost_center = [
        'type' => 'integer',
        'search' => true,
        'required' => true,
        'foreign_key' => __NAMESPACE__ . '\CostCenter',
        'foreign_key_fake' => true,
        'size' => 12,
        'label' => 'Lotação Física',
        'readonly' => true,
        'typeahead' => '/_auth/user/typeahead/cost_center',
    ];

    public $cost_center2 = [
        'type' => 'integer',
        'search' => true,
        'foreign_key' => __NAMESPACE__ . '\CostCenter',
        'foreign_key_fake' => true,
        'size' => 12,
        'label' => 'Lotação Administrativa',
        'readonly' => true,
        'typeahead' => '/_auth/user/typeahead/cost_center2',
    ];

    public $cost_center3 = [
        'type' => 'integer',
        'search' => true,
        'foreign_key' => __NAMESPACE__ . '\CostCenter',
        'foreign_key_fake' => true,
        'size' => 12,
        'label' => 'Lotação Manual',
        'typeahead' => '/_auth/user/typeahead/cost_center3',
    ];

    public $user_update = [
        'type' => 'datetime',
        'label' => 'Última Alteração',
        'protected' => true,
        'size' => 6,
    ];

    public $password = [
        'type' => 'password',
        'label' => 'Senha',
        'size' => 6,
    ];

    public $daily_hits = [
        'type' => 'intenger',
        'label' => 'Limite Acessos',
        'help' => 'por dia',
        'size' => 6,
    ];

    public $active = [
        'type' => 'boolean',
        'label' => 'Usuário Ativo',
        'value' => true,
        'size' => 4,
    ];

    public $super_user = [
        'type' => 'boolean',
        'label' => 'Super Usuário',
        'value' => false,
        'filter' => true,
        'size' => 4,
    ];

    protected $_indexes = [
        ['_insert', '_update'],
    ];

    public function __toString()
    {

        $this->name = mb_strtoupper((string)$this->name, 'utf8');

        return "$this->login - $this->name";

    }

    public function getName()
    {

        $arr = explode(' ', (string)$this->name);

        return ($this->nickname != '') ? $this->nickname : $arr[0]. ' ' . $arr[count($arr) - 1];

    }

    public function subtitle(): string
    {

        $config = $this->_config;
        $auth = $this->_auth;

        $arr = [];

        if (!isset($GLOBALS['cost_centers'])) {
            $GLOBALS['cost_centers'] = [];
        }

        if (!is_null($this->cost_center)) {

            if (!isset($GLOBALS['cost_centers'][$this->cost_center])) {
                $GLOBALS['cost_centers'][$this->cost_center] = (string) CostCenter::loadById($this->cost_center, $config, $auth);
            }

            $arr[] = $GLOBALS['cost_centers'][$this->cost_center];

        }

        if (!isset($GLOBALS['occupations'])) {
            $GLOBALS['occupations'] = [];
        }

        if (!is_null($this->occupation)) {

            if (!isset($GLOBALS['occupations'][$this->occupation])) {
                $GLOBALS['occupations'][$this->occupation] = (string) Occupation::loadById($this->occupation, $config, $auth);
            }

            $arr[] = $GLOBALS['occupations'][$this->occupation];

        }

        return implode(' | ', $arr);

    }

    public function tags(): array
    {

        $tags = [];

        if (!$this->active) {

            $tags[] = ['color' => 'red', 'label' => 'Bloqueado'];

        }

        if ($this->super_user) {

            $tags[] = ['color' => 'black', 'label' => 'Super Usuário'];

        }

        return $tags;

    }

    public function updateInfo(): ?string
    {
        return Util::jsDate($this->_insert);
    }

    public function updateTitle(): string
    {
        return 'Incluído em';
    }

    public function costCenters(): array
    {
        return [$this->cost_center, $this->cost_center2, $this->cost_center3];
    }

    public function actions(): array
    {

        //$config = $this->_config;
        $auth = $this->_auth;

        $actions = [];//parent::actions();

        $actions[] = [
            'id' => '/_auth/simular-acesso/' . $this->login,
            'type' => 'link',
            'label' => 'Simular Acesso',
            'disabled' => ($auth->getUser()->login != 'c098422'),
            'confirm' => '',
        ];

        return $actions;

    }

}
