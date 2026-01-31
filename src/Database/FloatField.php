<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class FloatField extends Field
{

    protected string $_type = self::TYPE_FLOAT;

    public function __construct(public ?string $label = null,
                                public ?bool $required = false,
                                public ?bool $protected = false,
                                public ?bool $readonly = false,
                                public null|int|string $min_value = null,
                                public null|int|string $max_value = null,
                                public ?int $size = 12)
    {

    }

}
