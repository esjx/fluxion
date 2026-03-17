<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\Field;
use Fluxion\FluxionException;
use Fluxion\Query\QuerySql;
use Fluxion\Query\QueryWhere;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ChoicesField extends Field
{

    use StaticChoices;

    /**
     * @throws FluxionException
     */
    public function __construct(public ?string $class_name = null,
                                public ?bool   $required = false,
                                public ?bool   $primary_key = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public ?int    $max_length = null,
                                public ?array  $choices = null,
                                public ?array  $choices_colors = null,
                                public array   $filters = [],
                                public bool    $radio = false,
                                public bool    $inline = false,
                                public mixed   $default = null,
                                public bool    $default_literal = false,
                                public ?string $column_name = null,
                                public ?bool   $fake = false,
                                public ?bool   $null_if_invalid = false,
                                public ?bool   $enabled = true,
                                bool $needs_audit = true)
    {

        $this->_needs_audit = $needs_audit;

        parent::__construct();

    }

    /** @throws FluxionException */
    public function initialize(): void
    {

        if (str_contains($this->_type_property, 'int')) {
            $this->_type = self::TYPE_INTEGER;
        }

        parent::initialize();

    }

    public function translate(mixed $value): string|int|null
    {

        return match ($this->_type) {
            'integer' => (new IntegerField(null_if_invalid: $this->null_if_invalid))->translate($value),
            default => (new StringField())->translate($value),
        };

    }

    /**
     * @throws FluxionException
     */
    public function validate(mixed &$value): bool
    {

        return match ($this->_type) {
            'integer' => (new IntegerField(null_if_invalid: $this->null_if_invalid))->validate($value),
            default => (new StringField())->validate($value),
        };

    }

    public function getSearch(string $value): ?QueryWhere
    {

        return match ($this->_type) {
            'integer' => (new IntegerField())->getSearch($value),
            'string' => (new StringField())->getSearch($value),
            default => parent::getSearch($value),
        };

    }

}
