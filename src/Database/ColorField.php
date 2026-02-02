<?php
namespace Fluxion\Database;

use Attribute;
use Fluxion\Color;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ColorField extends Field
{

    protected string $_type = self::TYPE_COLOR;

    public ?array $choices = Color::COLORS;
    public ?int $max_length = 15;

    public function __construct(public ?string $label = 'Cor',
                                public ?bool   $required = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public mixed   $default = null,
                                public bool    $default_literal = false,
                                public ?int    $size = 12)
    {
        parent::__construct();
    }

}
