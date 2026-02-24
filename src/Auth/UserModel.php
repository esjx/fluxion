<?php
namespace Fluxion\Auth;

use Fluxion\{Connector, Model};
use Fluxion\Database\Field\{BooleanField, IntegerField, PasswordField, StringField};

class UserModel extends Model
{

    #[StringField(required: true)]
    public ?string $login;

    #[IntegerField(required: true)]
    public ?int $cost_center;

    #[PasswordField]
    public ?string $password;

    /** @noinspection PhpUnused */
    #[StringField]
    public ?string $mail;

    /** @noinspection PhpUnused */
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
