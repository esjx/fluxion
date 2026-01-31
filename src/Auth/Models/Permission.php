<?php
namespace Fluxion\Auth\Models;

use Fluxion\Model;

class Permission extends Model
{

    protected int $create_order = -10;

    const VIEW = 'view';
    const UNDER = 'under';
    const SPECIAL = 'special';

    protected $_verbose_name_plural = 'Autenticação - Permissões';

    protected $_field_id = 'name';
    protected $_field_id_ai = false;

    protected $_table = 'auth.permission';

    public $name = [
        'type' => 'string',
        'label' => 'Nome',
        'required' => true,
    ];

    public function __toString()
    {

        $config = $this->_config;
        $auth = $this->_auth;

        if (is_null($this->name)) {

            return '';

        }

        if (!class_exists($this->name)) {

            self::filter('name', $this->name)->delete($config, $auth);

            return '';

        } else {

            $model = new $this->name($config, $auth);

            return $model->_verbose_name_plural;

        }

    }

}
