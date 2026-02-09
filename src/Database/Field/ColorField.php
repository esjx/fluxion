<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Color;
use Fluxion\Database\Field;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ColorField extends Field
{

    protected string $_type = self::TYPE_COLOR;

    public ?array $choices = null;
    public ?int $max_length = 15;

    public function __construct(public ?bool   $required = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public mixed   $default = null,
                                public bool    $default_literal = false)
    {

        $this->choices = Color::getColors();
        $this->choices_colors = Color::getColors();

        parent::__construct();

    }

}
