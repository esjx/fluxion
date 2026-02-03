<?php
namespace Fluxion\Auth\Models;

use Fluxion\Model2;
use Fluxion\Database;
use Fluxion\Connector;

class User2 extends Model2
{

    #[Database\PrimaryKey]
    #[Database\StringField(required: true)]
    public string $login;

    #[Database\Searchable]
    #[Database\StringField]
    public ?string $mail;

    #[Database\Filterable]
    #[Database\BooleanField(default: false)]
    public ?bool $super = false;

    public function getIndexes(): array
    {

        $indexes = parent::getIndexes();

        $indexes[] = new Connector\TableIndex(['mail'], unique: true);
        $indexes[] = new Connector\TableIndex(['super']);

        return $indexes;

    }

}
