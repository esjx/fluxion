<?php
namespace Fluxion\Database\Field;

use Attribute;
use BackedEnum;
use Fluxion\Color;
use Fluxion\Database\Field;
use Fluxion\Database\FormField;
use Fluxion\Exception;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ChoicesField extends Field
{

    use Choices;

    /**
     * @throws Exception
     */
    public function __construct(public ?bool   $required = false,
                                public ?bool   $primary_key = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public ?int    $max_length = null,
                                public ?array  $choices = null,
                                public ?array  $choices_colors = null,
                                public ?string $class_name = null,
                                public bool    $radio = false,
                                public bool    $inline = false,
                                public mixed   $default = null,
                                public bool    $default_literal = false,
                                public ?string $column_name = null,
                                public ?bool   $fake = false,
                                public ?bool   $enabled = true)
    {

        $this->createChoices();

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

}
