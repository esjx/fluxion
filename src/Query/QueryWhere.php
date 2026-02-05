<?php
namespace Fluxion\Query;

class QueryWhere {

    public function __construct(public string|QuerySql $field, public mixed $value = null, public bool $not = false)
    {

    }

}
