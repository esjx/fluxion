<?php
namespace Fluxion\Database;

use Fluxion\Color;

class FormField
{

    public ?string $min_date = null;
    public ?string $max_date = null;
    public ?string $typeahead = null;
    public bool $multiple = false;
    public bool $inline = false;

    public array $choices = [];

    public function __construct(public ?string $label = null,
                                public ?array  $label_conditions = null,
                                public bool    $visible = true,
                                public ?array  $visible_conditions = null,
                                public ?string $name = null,
                                public bool    $enabled = true,
                                public ?array  $enabled_conditions = null,
                                public string  $type = 'string',
                                public int     $size = 12,
                                public ?int    $min = null,
                                public ?int    $max = null,
                                public bool    $required = false,
                                public ?array  $required_conditions = null,
                                public string  $placeholder = '',
                                public ?string $pattern = null,
                                public ?string $validator_type = null,
                                public ?string $mask = null,
                                public ?int    $minlength = null,
                                public ?int    $maxlength = null,
                                public bool    $readonly = false,
                                public ?string $help = null,
                                public ?array  $help_conditions = null,
                                public ?array  $choices_conditions = null,
                                public mixed   $value = null)
    {

        if (!is_null($this->pattern)) {

            $this->pattern = preg_replace('/^\//', '', $this->pattern);
            $this->pattern = preg_replace('/\/\w*$/', '', $this->pattern);

        }

    }

    public function addChoice(mixed $value, string $label, ?Color $color = null): void
    {

        if (!is_null($color)) {
            $label = "<span class='text-$color->value'>$label</span>";
        }

        $this->choices[] = ['id' => $value, 'label' => $label];

    }

}
