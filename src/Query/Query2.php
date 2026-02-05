<?php
namespace Fluxion\Query;

use Generator;
use Fluxion\{Config2, Model2, CustomException};

class Query2
{

    protected string $_class_name;

    protected bool $all_fields = true;

    /**
     * @param QueryField[] $fields
     * @param QueryWhere[] $where
     * @param QueryOrderBy[] $order_by
     * @param QueryGroupBy[] $group_by
     */
    function __construct(protected Model2      $model,
                         protected array       $fields = [],
                         protected array       $where = [],
                         protected array       $order_by = [],
                         protected array       $group_by = [],
                         protected ?QueryLimit $limit = null)
    {

        $this->_class_name = get_class($model);

        // TODO: Validação dos parâmetros

    }

    public function getModel(): Model2
    {
        return $this->model;
    }

    public function getClassName(): string
    {
        return $this->_class_name;
    }

    public function isAllFields(): bool
    {
        return $this->all_fields;
    }

    /**
     * @return QueryField[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return QueryWhere[]
     */
    public function getWhere(): array
    {
        return $this->where;
    }

    /**
     * @return QueryOrderBy[]
     */
    public function getOrderBy(): array
    {
        return $this->order_by;
    }

    /**
     * @return QueryGroupBy[]
     */
    public function getGroupBy(): array
    {
        return $this->group_by;
    }

    /**
     * @return QueryLimit|null
     */
    public function getLimit(): ?QueryLimit
    {
        return $this->limit;
    }

    public function only($field): self
    {

        $this->clearFields();
        $this->addField($field);

        return $this;

    }

    public function addField($field): self
    {

        $this->all_fields = false;

        $this->fields[] = new QueryField($field);

        return $this;

    }

    public function clearFields(): self
    {

        $this->all_fields = true;

        $this->fields = [];

        return $this;

    }

    public function count($field = '*', $name = 'total'): self
    {

        $this->all_fields = false;

        $this->fields[] = new QueryField($field, 'COUNT', $name);

        return $this;

    }

    public function sum($field, $name = 'total'): self
    {

        $this->all_fields = false;

        $this->fields[] = new QueryField($field, 'SUM', $name);

        return $this;

    }

    public function avg($field, $name = 'total'): self
    {

        $this->all_fields = false;

        $this->fields[] = new QueryField($field, 'AVG', $name);

        return $this;

    }

    public function min($field, $name = ''): self
    {

        $this->all_fields = false;

        $this->fields[] = new QueryField($field, 'MIN', $name);

        return $this;

    }

    public function max($field, $name = ''): self
    {

        $this->all_fields = false;

        $this->fields[] = new QueryField($field, 'MAX', $name);

        return $this;

    }

    public function filter(string|QuerySql $field, $value = null): self
    {

        $this->where[] = new QueryWhere($field, $value);

        return $this;

    }

    public function filterIf(string|QuerySql $field, $value = null, $if = true): self
    {

        if ($if) {
            $this->filter($field, $value);
        }

        return $this;

    }

    public function exclude(string|QuerySql $field, $value): self
    {

        $this->where[] = new QueryWhere($field, $value, true);

        return $this;

    }

    public function excludeIf(string|QuerySql $field, $value = null, $if = true): self
    {

        if ($if) {
            $this->exclude($field, $value);
        }

        return $this;

    }

    public function clearFilters(): self
    {

        $this->where = [];

        return $this;

    }

    public function orderBy($field, $order = null): self
    {

        if (is_string($field)) {
            $field = explode(',', $field);
        }

        foreach ($field as $item) {

            $item = trim($item);

            $order2 = null;

            if (str_starts_with($item, '+')) {
                $order2 = 'ASC';
                $item = substr($item, 1);
            }

            elseif (str_starts_with($item, '-')) {
                $order2 = 'DESC';
                $item = substr($item, 1);
            }

            elseif (str_ends_with($item, ' ASC')) {
                $order2 = 'ASC';
                $item = substr($item, 0, -4);
            }

            elseif (str_ends_with($item, ' DESC')) {
                $order2 = 'DESC';
                $item = substr($item, 0, -5);
            }

            $this->order_by[] = new QueryOrderBy($item, $order2 ?? $order);

        }

        return $this;

    }

    public function clearOrderBy(): self
    {

        $this->order_by = [];

        return $this;

    }

    public function groupBy($field, $only = true): self
    {

        if (is_string($field)) {
            $field = explode(',', $field);
        }

        foreach ($field as $item) {

            $item = trim($item);

            if ($only) {
                $this->addField($item);
            }

            $this->group_by[] = new QueryGroupBy($item);

        }

        return $this;

    }

    public function clearGroupBy(): self
    {

        $this->group_by = [];

        return $this;

    }

    /**
     * @throws CustomException
     */
    public function limit($limit, $offset = 0): self
    {

        $this->limit = new QueryLimit($limit, $offset);

        return $this;

    }

    public function clearLimit(): self
    {

        $this->limit = null;

        return $this;

    }

    /**
     * @throws CustomException
     */
    public function select(): Generator
    {
        return Config2::getConnector()->select($this);
    }

    /**
     * @throws CustomException
     */
    public function paginate(&$page, &$pages, $quant): self
    {

        $query_total = clone $this;

        $total = $query_total->clearOrderBy()->count()->firstOrNew()->total;

        $pages = ceil($total / $quant);

        $page = min($page, $pages);

        $offset = ($page - 1) * $quant;

        return $this->limit($quant, $offset);

    }

    /**
     * @throws CustomException
     */
    public function first(): ?Model2
    {

        foreach ($this->limit(1)->select() as $model) {
            return $model;
        }

        return null;

    }

    /**
     * @throws CustomException
     */
    public function firstOrNew(): Model2
    {
        return $this->first() ?? new $this->_class_name();
    }

    /**
     * @throws CustomException
     */
    public function sql(): string
    {
        return Config2::getConnector()->sql_select($this);
    }

    /**
     * @throws CustomException
     */
    public function delete(): bool
    {
        return Config2::getConnector()->delete($this);
    }

    /**
     * @throws CustomException
     */
    public function toArray(): array
    {

        $out = [];

        if ($this->count($this->fields) == 0) {
            throw new CustomException('Nenhum campo informado encontrado.');
        }

        if ($this->count($this->fields) > 1) {
            throw new CustomException('Mais de um campo informado encontrado.');
        }

        $field = $this->fields[0]->field;

        /** @var Model2 $key */
        foreach ($this->select() as $key) {
            $out[] = $key->$field;
        }

        return $out;

    }

}
