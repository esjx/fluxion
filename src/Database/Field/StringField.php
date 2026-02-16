<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\Field;
use Fluxion\{DigitValidator};
use Fluxion\Query\{QuerySql, QueryWhere};

#[Attribute(Attribute::TARGET_PROPERTY)]
class StringField extends Field
{

    public function __construct(public ?bool   $required = false,
                                public ?bool   $primary_key = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public ?int    $max_length = null,
                                public mixed   $default = null,
                                public bool    $default_literal = false,
                                public ?string $column_name = null,
                                ?string        $pattern = null,
                                ?string        $validator_type = null,
                                public ?bool   $fake = false,
                                public ?bool   $enabled = true)
    {

        if (!is_null($pattern)) {
            $this->pattern = $pattern;
        }

        if (!is_null($validator_type)) {
            $this->validator_type = $validator_type;
        }

        parent::__construct();

    }

    public function getSearch(string $value): ?QueryWhere
    {
        #TODO: fulltext search
        return QuerySql::filter("{$this->_name}__like", "$value%");
    }

    public function validate(mixed &$value): bool
    {

        if (!parent::validate($value)) {
            return false;
        }

        if (empty($value)) {
            $value = null;
            return true;
        }

        if (!is_null($this->max_length) && strlen($value) > $this->max_length) {
            return false;
        }

        if (!is_null($this->pattern) && !preg_match($this->pattern, $value)) {
            return false;
        }

        if ($this->validator_type == 'CPF' && !DigitValidator::cpf($value)) {
            return false;
        }

        if ($this->validator_type == 'CNPJ' && !DigitValidator::cnpj($value)) {
            return false;
        }

        return true;

    }

}
