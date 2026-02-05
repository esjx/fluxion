<?php
namespace Fluxion\Query;

class QueryOrderBy {

    public function __construct(public string $field, public string $order = 'ASC')
    {

    }

}
