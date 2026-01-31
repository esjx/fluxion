<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IntegerField extends Field
{

    protected string $_type = self::TYPE_INTEGER;
    protected string $_type_target = 'int';

    public function __construct(public ?string         $label = null,
                                public ?bool           $required = false,
                                public ?bool           $protected = false,
                                public ?bool           $readonly = false,
                                public ?array          $choices = null,
                                public ?array          $choices_colors = null,
                                public null|int|string $min_value = null,
                                public null|int|string $max_value = null,
                                public ?int            $size = 12)
    {
        parent::__construct();
    }

    public function validate(mixed &$value): bool
    {

        if (!parent::validate($value)) {
            return false;
        }

        if (empty($value)) {
            $value = null;
        };

        return is_null($value) || is_numeric($value);

    }

    public function translate(mixed $value): int
    {
        return intval($value);
    }

}
