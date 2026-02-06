<?php
namespace Fluxion\Auth\Models;

use Fluxion\{Model, Connector};
use Fluxion\Database\Field\{StringField, BooleanField};

abstract class User extends Model
{

    #[StringField(required: true)]
    public ?string $login;

    #[StringField]
    public ?string $mail;

    #[BooleanField(default: false)]
    public ?bool $super = false;

    public function getIndexes(): array
    {

        $indexes = parent::getIndexes();

        $indexes[] = new Connector\TableIndex(['mail'], unique: true);
        $indexes[] = new Connector\TableIndex(['super']);

        return $indexes;

    }

}
