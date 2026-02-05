<?php
namespace Fluxion\Query;

class QuerySql
{

    /** @var array<QueryWhere|null> */
    public array $_filters = [];
    public string $_type = 'AND';

    public static function _and($filters): self
    {

    	$qry = new self();
    	$qry->_filters = $filters;
    	$qry->_type = 'AND';

        return $qry;

    }

    public static function _or($filters): self
    {

    	$qry = new self();
    	$qry->_filters = $filters;
    	$qry->_type = 'OR';

        return $qry;

    }

    public static function filter($field, $value = null): QueryWhere
    {
        return new QueryWhere($field, $value);
    }

    public static function filterIf($field, $value = null, $if = true): ?QueryWhere
    {

        if ($if) {
            return new QueryWhere($field, $value);
        }

        return null;

    }

    public static function exclude($field, $value = null): QueryWhere
    {
        return new QueryWhere($field, $value, true);
    }

    public static function excludeIf($field, $value = null, $if = true): ?QueryWhere
    {

        if ($if) {
            return new QueryWhere($field, $value, true);
        }

        return null;

    }

}
