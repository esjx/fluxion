<?php
namespace Fluxion\Database\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BooleanField extends Field
{

    protected string $_type = self::TYPE_BOOLEAN;
    protected string $_type_target = 'bool';

    public function __construct(public ?bool   $required = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public mixed   $default = null,
                                public bool    $default_literal = false)
    {
        parent::__construct();
    }

    public function validate(mixed &$value): bool
    {

        if ($this->required && !in_array($value, [true, false], true)) {
            return false;
        }

        return true;

    }

}
