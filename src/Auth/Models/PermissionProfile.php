<?php
namespace Fluxion\Auth\Models;

use Fluxion\Model;

class PermissionProfile extends Model
{

    protected $_verbose_name_plural = 'Autenticação - Permissões de Grupos';

    protected $_field_id = '';
    protected $_field_id_ai = false;

    protected $_table = 'auth.permission_profile';

    public string|null|array $permission = [
        'type' => 'string',
        'required' => true,
        'foreign_key' => Permission::class,
        'primary_key' => true,
        'label' => 'Permissão',
    ];

    public string|null|array $profile = [
        'type' => 'string',
        'required' => true,
        'foreign_key' => Profile::class,
        'primary_key' => true,
        'label' => 'Perfil',
    ];

    public bool|null|array $insert = [
        'type' => 'boolean',
        'label' => 'Inserir',
    ];

    public bool|null|array $update = [
        'type' => 'boolean',
        'label' => 'Editar',
    ];

    public bool|null|array $delete = [
        'type' => 'boolean',
        'label' => 'Apagar',
    ];

    public bool|null|array $view = [
        'type' => 'boolean',
        'label' => 'Ver',
    ];

    public bool|null|array $under = [
        'type' => 'boolean',
        'label' => 'Vinculadas',
    ];

    public bool|null|array $special = [
        'type' => 'boolean',
        'label' => 'Especial',
    ];

}
