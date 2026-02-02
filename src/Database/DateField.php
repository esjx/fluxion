<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DateField extends Field
{

    protected string $_type = self::TYPE_DATE;

    public function __construct(public ?string         $label = null,
                                public ?bool           $required = false,
                                public ?bool           $protected = false,
                                public ?bool           $readonly = false,
                                public null|int|string $min_value = null,
                                public null|int|string $max_value = null,
                                public mixed           $default = null,
                                public bool            $default_literal = false,
                                public ?int            $size = 12)
    {
        parent::__construct();
    }

}
