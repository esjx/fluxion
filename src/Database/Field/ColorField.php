<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Color;
use Fluxion\Database\Field;
use Fluxion\Database\FormField;

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

        parent::__construct();

    }

    public function getFormField(): FormField
    {

        $form_field = parent::getFormField();

        foreach (Color::cases() as $color) {

            if (in_array($color, [Color::INDIGO, Color::LIGHT_BLUE, Color::WHITE, Color::GREY, Color::BLUE_GREY])) {
                continue;
            }

            $form_field->addChoice(
                value: $color->value,
                label: $color->value
            );

        }

        $form_field->type = 'colors';

        return $form_field;

    }

}
