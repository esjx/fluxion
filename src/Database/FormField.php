<?php
namespace Fluxion\Database;

use AllowDynamicProperties;
use Fluxion\Color;

#[AllowDynamicProperties]
class FormField
{

    public ?string $minDate = null;
    public ?string $maxDate = null;
    public ?string $typeahead = null;
    public bool $multiple = false;
    public bool $inline = false;

    protected array $_choices = [];

    public function __construct(public bool    $visible = true,
                                public ?string $name = null,
                                public bool    $enabled = true,
                                public ?string $label = null,
                                public string  $type = 'string',
                                public int     $size = 12,
                                public ?int    $min = null,
                                public ?int    $max = null,
                                public bool    $required = false,
                                public string  $placeholder = '',
                                public ?string $mask = null,
                                public ?int    $minlength = null,
                                public ?int    $maxlength = null,
                                public bool    $readonly = false,
                                public ?string $help = null,
                                public mixed   $value = null)
    {

    }

    public function addChoice(mixed $value, string $label, ?Color $color = null): void
    {

        if (!is_null($color)) {
            $label = "<span class='text-$color->value'>$label</span>";
        }

        $this->_choices[] = ['id' => $value, 'label' => $label];

    }

    public function getChoices(): array
    {
        return $this->_choices;
    }

}
