<?php
namespace Fluxion\Auth\Models;

use Fluxion\Model;

class UserGroup extends Model
{

    const AUTOMATICO = 1;
    const MANUAL = 2;
    const GESTOR = 3;

    protected $_verbose_name_plural = 'Autenticação - Usuários de Grupos';

    protected $_field_id = '';
    protected $_field_id_ai = false;

    protected $_table = 'auth.user_group';

    protected $_fields = [
        '_user' => [
            'type' => 'string',
            'maxlength' => 7,
            'required' => true,
            'foreign_key' => __NAMESPACE__ . '\User',
            'primary_key' => true,
            'label' => 'Usuário',
        ],
        '_group' => [
            'type' => 'integer',
            'required' => true,
            'foreign_key' => __NAMESPACE__ . '\Group',
            'primary_key' => true,
            'label' => 'Grupo',
        ],
        '_type' => [
            'type' => 'integer',
            'label' => 'Automático',
            'required' => true,
            'primary_key' => true,
            'choices' => [
                1 => 'Automática',
                2 => 'Manual',
                3 => 'Gestor',
            ],
        ],
        'responsible' => [
            'type' => 'string',
            'label' => 'Responsavel',
        ],
        'expires' => [
            'type' => 'date',
            'label' => 'Expira em',
            'required' => false,
        ],
    ];

}
