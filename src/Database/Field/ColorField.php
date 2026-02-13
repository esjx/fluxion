<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Color;
use Fluxion\Database\{Field, FormField};

#[Attribute(Attribute::TARGET_PROPERTY)]
class ColorField extends Field
{

    protected string $_type = self::TYPE_COLOR;

    public ?array $choices = null;
    public ?int $max_length = 15;

    public function __construct(public ?bool $required = false,
                                public ?bool $protected = false,
                                public ?bool $readonly = false,
                                public mixed $default = null,
                                public bool  $default_literal = false,
                                public ?bool $fake = false,
                                public ?bool $enabled = true)
    {

        parent::__construct();

    }

    public function getFormField(array $extras = [], ?string $route = null): FormField
    {

        $form_field = parent::getFormField($extras);

        foreach (Color::getColors() as $color) {

            $form_field->addChoice(
                value: $color->value,
                label: $color->value
            );

        }

        $form_field->type = 'colors';

        return $form_field;

    }

}
