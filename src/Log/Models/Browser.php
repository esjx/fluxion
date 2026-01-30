<?php
namespace Esj\Core\Log\Models;

use Esj\Core\Model;

class Browser extends Model
{

    protected $_verbose_name = 'Grupo';

    protected $_table = 'log.browser';

    protected $_order = [
        ['name', 'ASC'],
    ];

    public $name = [
        'type' => 'string',
        'label' => 'Nome',
        'required' => true,
    ];

    public $active = [
        'type' => 'boolean',
        'label' => 'Ativo',
    ];

    public function __toString()
    {
        return $this->name;
    }

}
