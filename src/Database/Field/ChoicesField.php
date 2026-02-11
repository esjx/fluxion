<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Color;
use Fluxion\Database\Field;
use Fluxion\Database\FormField;
use Fluxion\Exception;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ChoicesField extends Field
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
                                public ?string $column_name = null,
                                public ?bool $enabled = true)
    {
        parent::__construct();
    }

    /** @throws Exception */
    public function initialize(): void
    {

        if ($this->_type_property != 'int') {
            $this->_type = self::TYPE_INTEGER;
        }

        parent::initialize();

    }

    public function translate(mixed $value): string|int|null
    {

        return match ($this->_type) {
            'integer' => (new IntegerField())->translate($value),
            default => (new StringField())->translate($value),
        };

    }

    public function validate(mixed &$value): bool
    {

        return match ($this->_type) {
            'integer' => (new IntegerField())->validate($value),
            default => (new StringField())->validate($value),
        };

    }

    public function getFormField(): FormField
    {

        $form_field = parent::getFormField();

        foreach ($this->choices as $key => $label) {

            if ($this->_type == self::TYPE_STRING) {
                $key = (string) $key;
            }

            $form_field->addChoice(
                value: $key,
                label: $label,
                color: Color::tryFrom($this->choices_colors[$key] ?? '')
            );

        }

        $form_field->type = 'choices';

        return $form_field;

    }

}
