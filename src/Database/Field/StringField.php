<?php
namespace Fluxion\Database\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class StringField extends Field
{

    public function __construct(public ?bool   $required = false,
                                public ?bool   $primary_key = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public ?int    $max_length = null,
                                public ?array  $choices = null,
                                public ?array  $choices_colors = null,
                                public mixed   $default = null,
                                public bool    $default_literal = false,
                                public ?string $column_name = null)
    {
        parent::__construct();
    }

}
