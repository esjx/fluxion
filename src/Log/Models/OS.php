<?php
namespace Esj\Core\Log\Models;

use Esj\Core\Model;

class OS extends Model
{

    protected $_verbose_name = 'Grupo';

    protected $_table = 'log.os';

    protected $_order = [
        ['name', 'ASC'],
    ];

    protected $_fields = [
        'name' => [
            'type' => 'string',
            'label' => 'Nome',
            'required' => true,
        ],
    ];

    public function __toString()
    {
        return $this->name;
    }

}
