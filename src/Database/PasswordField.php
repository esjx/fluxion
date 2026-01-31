<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PasswordField extends Field
{

    protected string $_type = self::TYPE_PASSWORD;

    public function __construct(public ?string $label = 'Senha',
                                public ?bool $required = false,
                                public ?bool $protected = false,
                                public ?bool $readonly = false,
                                public ?int $size = 12)
    {

    }

}
