<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class StringField extends Field
{

    public function __construct(public ?string $label = null,
                                public ?string $mask_class = null,
                                public ?bool   $required = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public ?int    $max_length = null,
                                public ?array  $choices = null,
                                public ?array  $choices_colors = null,
                                public ?int    $size = 12)
    {
        parent::__construct();
    }

}
