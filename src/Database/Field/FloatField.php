<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\Field;
use Fluxion\Database\FormField;

#[Attribute(Attribute::TARGET_PROPERTY)]
class FloatField extends Field
{

    protected string $_type = self::TYPE_FLOAT;
    protected string $_type_target = 'float';

    public function __construct(public ?bool           $required = false,
                                public ?bool           $protected = false,
                                public ?bool           $readonly = false,
                                public null|int|string $min_value = null,
                                public null|int|string $max_value = null,
                                public mixed           $default = null,
                                public bool            $default_literal = false,
                                public ?bool           $fake = false,
                                public ?bool           $enabled = true)
    {
        parent::__construct();
    }

    public function validate(mixed &$value): bool
    {

        if ($this->required && is_null($value)) {
            return false;
        }

        if ($value === '') {
            $value = null;
        }

        return is_null($value) || is_numeric($value);

    }

    public function translate(mixed $value): ?float
    {

        if (is_null($value)) {
            return null;
        }

        return floatval($value);

    }

    public function getFormField(array $extras = [], ?string $route = null): FormField
    {

        $form_field = parent::getFormField($extras);

        $form_field->type = 'float';

        return $form_field;

    }

}
