<?php
namespace Esj\Core\Log\Models;

use Esj\Core\Model;

class UserAgent extends Model
{

    protected $_verbose_name = 'Navegador';

    protected $_table = 'log.user_agent';

    protected $_order = [
        ['name', 'ASC'],
    ];

    protected $_fields = [
        'name' => [
            'type' => 'string',
            'label' => 'Nome',
            'required' => false,
        ],
        'os' => [
            'type' => 'integer',
            'required' => false,
            'foreign_key' => __NAMESPACE__ . '\OS',
            'label' => 'Sistema',
        ],
        'so_version' => [
            'type' => 'integer',
            'required' => false,
            'label' => 'Versão',
        ],
        'browser' => [
            'type' => 'integer',
            'required' => false,
            'foreign_key' => __NAMESPACE__ . '\Browser',
            'label' => 'Navegador',
        ],
        'browser_version' => [
            'type' => 'integer',
            'required' => false,
            'label' => 'Versão',
        ],
    ];

    protected $_indexes = [
        ['os', 'browser'],
    ];

    public function __toString()
    {
        return $this->name;
    }

}
