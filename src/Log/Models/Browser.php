<?php
namespace Fluxion\Log\Models;

use Fluxion\ModelOld;

class Browser extends ModelOld
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
