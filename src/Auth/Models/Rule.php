<?php
namespace Fluxion\Auth\Models;

use Fluxion\ModelOld;

class Rule extends ModelOld
{

    const TODOS = 0;
    const LOTACAO_FISICA = 1;
    const LOTACAO_ADMINISTRATIVA = 2;
    const LOTACAO_FISICA_OU_ADMINISTRATIVA = 3;
    const FUNCAO = 4;
    const CARGO = 6;
    const SUBORDINACAO = 5;
    const SUPERINTENDENCIA_NACIONAL = 7;
    const VICE_PRESIDENCIA = 8;

    protected $_verbose_name_plural = 'Autenticação - Regras';

    protected $_table = 'auth._rule';

    protected $_fields = [
        '_group' => [
            'type' => 'integer',
            'required' => true,
            'foreign_key' => __NAMESPACE__ . '\Group',
            'primary_key' => true,
            'label' => 'Grupo',
        ],
        '_type' => [
            'type' => 'integer',
            'label' => 'Tipo',
            'choices' => [
                0 => 'Todos',
                1 => 'Lotação Física',
                2 => 'Lotação Administrativa',
                3 => 'Lotação (Física ou Administrativa)',
                4 => 'Função',
                5 => 'Subordinação',
                6 => 'Cargo',
                7 => 'Superintendência Nacional',
                8 => 'Vice-Presidência',
            ],
        ],
        'and_id' => [
            'type' => 'integer',
            'label' => 'E#',
        ],
        'description' => [
            'type' => 'string',
            'label' => 'Descrição',
        ],
    ];

}
