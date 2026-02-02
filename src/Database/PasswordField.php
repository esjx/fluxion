<?php
namespace Fluxion\Database;

use Attribute;
use Fluxion\CustomException;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PasswordField extends Field
{

    protected string $_type = self::TYPE_PASSWORD;

    public function __construct(public ?string $label = 'Senha',
                                public ?bool   $required = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public ?int    $size = 12)
    {
        parent::__construct();
    }

    public function translate(mixed $value): string
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    /** @throws CustomException */
    public function validadePassword($password): bool
    {

        if (!password_verify($password, $this->_value)) {
            throw new CustomException(message: "Senha inválida!", log: false);
        }

        if (password_needs_rehash($this->_value, PASSWORD_DEFAULT)) {
            $this->_value = password_hash($password, PASSWORD_DEFAULT);
        }

        return true;

    }

}
