<?php
namespace Fluxion\Auth\Models;

use Fluxion\Model;

class PermissionGroup extends Model
{

    protected $_verbose_name_plural = 'Autenticação - Permissões de Grupos';

    protected $_field_id = '';
    protected $_field_id_ai = false;

    protected $_table = 'auth.permission_group';

    protected $_fields = [
        'permission' => [
            'type' => 'string',
            'required' => true,
            'foreign_key' => __NAMESPACE__ . '\Permission',
            'primary_key' => true,
            'label' => 'Permissão',
        ],
        '_group' => [
            'type' => 'integer',
            'required' => true,
            'foreign_key' => __NAMESPACE__ . '\Group',
            'primary_key' => true,
            'label' => 'Grupo',
        ],
        'insert' => [
            'type' => 'boolean',
            'label' => 'Inserir',
        ],
        'update' => [
            'type' => 'boolean',
            'label' => 'Editar',
        ],
        'delete' => [
            'type' => 'boolean',
            'label' => 'Apagar',
        ],
        'view' => [
            'type' => 'boolean',
            'label' => 'Ver',
        ],
        'under' => [
            'type' => 'boolean',
            'label' => 'Vinculadas',
        ],
        'special' => [
            'type' => 'boolean',
            'label' => 'Especial',
        ],
    ];

}
