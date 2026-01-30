<?php
namespace Esj\Core\Auth\Models;

use Esj\Core\Model;

class Occupation extends Model
{

    const GERENTE_NACIONAL = 2037;
    const GERENTE_EXECUTIVO = 2038;
    const GERENTE_CLIENTES_NEGOCIOS_I = 2077;
    const COORDENADOR_PROJETOS_MATRIZ = 2030;
    const SUPERINTENDENTE_NACIONAL = 2048;

    protected $_verbose_name = 'Função';
    protected $_verbose_name_plural = 'Funções';
    protected $_html_title = 'Funções';

    protected $_table = 'auth.occupation';

    protected $_field_id_ai = false;

    protected $_order = [
        ['name', 'ASC'],
    ];

    public $id = [
        'type' => 'integer',
        'search' => true,
        'readonly' => true,
    ];

    public $name = [
        'type' => 'string',
        'label' => 'Nome',
        'search' => true,
        'required' => true,
        'field_size' => 12,
    ];

    public $journey = [
        'type' => 'integer',
        'label' => 'Jornada',
        'field_size' => 12,
    ];

    public $manager = [
        'type' => 'boolean',
        'label' => 'Gerente',
        'field_size' => 12,
    ];

    public function __toString()
    {

        if (!$this->id) {
            return "SEM FUNÇÃO";
        }

        return sprintf("%04d", $this->id) . ' - ' . $this->name;

    }

    public function tags(): array
    {

        $tags = [];

        if ($this->manager) {
            $tags[] = ['color' => 'black', 'label' => 'Gerente'];
        }

        return $tags;

    }

}
