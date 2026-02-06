<?php
namespace Fluxion\Database\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DecimalField extends FloatField
{

    protected string $_type = self::TYPE_DECIMAL;
    protected string $_type_target = 'float';

    public function __construct(public ?bool           $required = false,
                                public ?bool           $protected = false,
                                public ?bool           $readonly = false,
                                public null|int|string $min_value = null,
                                public null|int|string $max_value = null,
                                public mixed           $default = null,
                                public bool            $default_literal = false)
    {
        parent::__construct();
    }

}
