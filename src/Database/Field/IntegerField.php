<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\Field;
use Fluxion\Query\{QuerySql, QueryWhere};

#[Attribute(Attribute::TARGET_PROPERTY)]
class IntegerField extends Field
{

    protected string $_type = self::TYPE_INTEGER;
    protected string $_type_target = 'int';

    public function __construct(public ?bool           $required = false,
                                public ?bool           $primary_key = false,
                                public ?bool           $protected = false,
                                public ?bool           $readonly = false,
                                public null|int|string $min_value = null,
                                public null|int|string $max_value = null,
                                public mixed           $default = null,
                                public bool            $default_literal = false,
                                public ?bool $enabled = true)
    {
        parent::__construct();
    }

    public function validate(mixed &$value): bool
    {

        if ($this->required && is_null($value)) {
            return false;
        }

        if (empty($value)) {
            $value = null;
        }

        return is_null($value) || is_numeric($value);

    }

    public function translate(mixed $value): null|int|array
    {

        if (empty($value)) {
            return null;
        }

        return intval($value);

    }

    public function getSearch(string $value): ?QueryWhere
    {

        if (!is_numeric($value) || strlen($value) > 15) {
            return null;
        }

        return QuerySql::filter($this->_name, (int) $value);

    }

}
