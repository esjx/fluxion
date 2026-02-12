<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\Field;
use Fluxion\Query\QuerySql;
use Fluxion\Query\QueryWhere;

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
                                public ?bool   $fake = false,
                                public ?bool   $enabled = true)
    {
        parent::__construct();
    }

    public function getSearch(string $value): ?QueryWhere
    {
        #TODO: fulltext search
        return QuerySql::filter("{$this->_name}__like", "$value%");
    }

}
