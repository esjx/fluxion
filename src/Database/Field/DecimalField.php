<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\TextFormatter;

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
                                public ?int            $precision = 18,
                                public ?int            $scale = 2,
                                public mixed           $default = null,
                                public bool            $default_literal = false,
                                public ?bool           $fake = false,
                                public ?bool           $enabled = true)
    {

        parent::__construct(
            required: $required,
            protected: $protected,
            readonly: $readonly,
            min_value: $min_value,
            max_value: $max_value,
            default: $default,
            default_literal: $default_literal,
            fake: $fake,
            enabled: $enabled
        );

    }

}
