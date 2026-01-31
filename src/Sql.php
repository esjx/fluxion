<?php
namespace Fluxion;

class Sql
{

    public $_filters = [];
    public $_type = 'AND';

    public static function _and($filters): Sql
    {

    	$sqlf = new Sql;
    	$sqlf->_filters = $filters;
    	$sqlf->_type = 'AND';

        return $sqlf;

    }

    public static function _or($filters): Sql
    {

    	$sqlf = new Sql;
    	$sqlf->_filters = $filters;
    	$sqlf->_type = 'OR';

        return $sqlf;

    }

    public static function filter($field, $value = null): array
    {

        return ['field' => $field, 'value' => $value, 'not' => false];

    }

    public static function exclude($field, $value = null): array
    {

        return ['field' => $field, 'value' => $value, 'not' => true];

    }

    public static function filterIf($field, $value = null, $if = true): array
    {

        if ($if) {

            return ['field' => $field, 'value' => $value, 'not' => false];

        } else {

            return [];

        }

    }

    public static function excludeIf($field, $value = null, $if = true): array
    {

        if ($if) {

            return ['field' => $field, 'value' => $value, 'not' => true];

        } else {

            return [];

        }

    }

}
