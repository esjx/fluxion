<?php
namespace Fluxion\Query;

use Fluxion\Time;

class QueryWhere {

    public function __construct(public string|QuerySql $field, public mixed $value = null, public bool $not = false)
    {

        if ($value instanceof Time) {
            $this->value = $value->value();
        }

    }

}
