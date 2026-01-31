<?php
namespace Fluxion\Auth\Models;

use Fluxion as Esj;

class Profile extends Esj\Model
{

    protected $_verbose_name = 'Perfil';
    protected $_verbose_name_plural = 'Perfis';

    protected $_table = 'auth._profile';

    protected $_field_id_ai = false;
    protected $_field_id = 'id';

    protected $_order = [
        ['name', ASC],
    ];

    public string|null|array $id = [
        'type' => 'string',
        'label' => '#',
        'required' => true,
        'protected' => true,
    ];

    public string|null|array $name = [
        'type' => 'string',
        'label' => 'Nome',
        'required' => true,
        //'read' => true,
        'search' => true,
    ];

    public string|null|array $description = [
        'type' => 'string',
        'label' => 'Descricão',
        'search' => true,
    ];

    public ?array $groups = [
        'type' => 'integer',
        'label' => 'Grupos',
        'many_to_many' => Group::class,
        'typeahead' => '/_auth/profile/typeahead/groups',
        'filter' => true,
    ];

    public function onSave(): bool
    {

        if (is_null($this->id)) {
            $this->id = Esj\Util::slug($this->name);
        }

        return parent::onSave();

    }

    public function __toString()
    {
        return $this->name;
    }

    public function actions(): array
    {

        $actions = [];

        $actions[] = [
            'id' => ['/_auth/profile', $this->id, 'manage'],
            'type' => 'route',
            'label' => 'Acessos',
            'disabled' => false,
            'confirm' => '',
        ];

        return $actions;

    }

}
