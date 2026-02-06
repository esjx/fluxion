<?php
namespace Fluxion\Log\Models;

use Fluxion\ModelOld;

class Log extends ModelOld
{

    protected $_verbose_name = 'Log de Acessos';

    protected $_table = 'log.log';

    protected $_order = [
        ['id', 'DESC'],
    ];

    protected $_fields = [
        'cost_center' => [
            'type' => 'integer',
            'required' => false,
            'foreign_key' => 'Fluxion\Auth\Models\CostCenter',
            'foreign_key_fake' => true,
            'label' => 'Unidade',
        ],
        'login' => [
            'type' => 'string',
            'label' => 'Matrícula',
            'required' => false,
            'foreign_key' => 'Fluxion\Auth\Models\UserOld',
            'foreign_key_fake' => true,
        ],
        'user_agent' => [
            'type' => 'integer',
            'required' => true,
            'foreign_key' => __NAMESPACE__ . '\UserAgent',
            'label' => 'Browser',
            'protected' => true,
        ],
        'ip' => [
            'type' => 'string',
            'label' => 'IP',
            'required' => true,
        ],
        'method' => [
            'type' => 'string',
            'maxlength' => 6,
            'label' => 'Método',
            'required' => true,
        ],
        'uri' => [
            'type' => 'string',
            'label' => 'URI',
            'required' => true,
        ],
        'query_string' => [
            'type' => 'text',
            'label' => 'Parâmetros',
            'required' => true,
            'protected' => true,
        ],
        'referer' => [
            'type' => 'string',
            'label' => 'Referência',
            'required' => false,
            'protected' => true,
        ],
        'control' => [
            'type' => 'string',
            'label' => 'Controlador',
            'required' => false,
        ],
        'action' => [
            'type' => 'string',
            'label' => 'Ação',
            'required' => false,
        ],
        'time' => [
            'type' => 'float',
            'label' => 'Tempo',
            'required' => false,
        ],
        'memory' => [
            'type' => 'float',
            'label' => 'Memória',
            'required' => false,
        ],
        'ok' => [
            'type' => 'boolean',
            'label' => 'Sucesso',
            'required' => true,
            'protected' => true,
        ],
        'error' => [
            'type' => 'text',
            'label' => 'Erro',
            'protected' => true,
        ],
        'csrf' => [
            'type' => 'string',
            'maxlength' => 80,
            'protected' => true,
        ],
    ];

    protected $_indexes = [
        ['method', '_update'],
        ['user_agent', '_update'],
        ['control', 'action', '_update'],
        ['cost_center', 'login', '_update'],
    ];

}
