<?php
namespace Fluxion;

use Generator;
use Fluxion\Query\QuerySql;

trait ModelQuery
{

    public static function query(): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return new Query($obj);

    }

    public static function only($field): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->only($field);

    }

    public static function count($field = '*', $name = 'total'): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->count($field, $name);

    }

    public static function sum($field, $name = 'total'): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->sum($field, $name);

    }

    public static function avg($field, $name = 'total'): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->avg($field, $name);

    }

    public static function min($field, $name = 'total'): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->min($field, $name);

    }

    public static function max($field, $name = 'total'): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->sum($field, $name);

    }

    public static function filter(string|QuerySql $field, $value = null): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->filter($field, $value);

    }

    public static function filterIf(string|QuerySql $field, $value = null, $if = true): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->filterIf($field, $value, $if);

    }

    public static function exclude(string|QuerySql $field, $value = null): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->exclude($field, $value);

    }

    public static function excludeIf(string|QuerySql $field, $value = null, $if = true): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->excludeIf($field, $value, $if);

    }

    public static function orderBy($field, $order = 'ASC'): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->orderBy($field, $order);

    }

    public static function groupBy($field, $only = true): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->groupBy($field, $only);

    }

    /**
     * @throws Exception
     */
    public static function limit($limit, $offset = 0): Query
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->limit($limit, $offset);

    }

    /**
     * @throws Exception
     */
    public static function select(): Generator
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query($obj))->select();

    }

    /**
     * @throws Exception
     */
    public static function loadById(mixed $id): self
    {

        $class = get_called_class();

        #TODO: Cache de itens já carregados do banco de dados

        /** @var self $obj */
        $obj = new $class();

        if (is_null($id)) {
            return $obj;
        }

        $primary_keys = $obj->getPrimaryKeys();

        if (count($primary_keys) == 0) {
            throw new Exception("Model '$class' não possui chave primária definida");
        }

        if (!is_array($id) && count($primary_keys) == 1) {
            $id = [$obj->getFieldId()->getName() => $id];
        }

        $query = $obj->query();

        foreach ($primary_keys as $key => $primary_key) {

            $value = $id[$key]
                ?? throw new Exception("Valor para o campo '$class:$key' não informado");

            $query = $query->filter($key, $value);

        }

        return $query->firstOrNew();

    }

}
