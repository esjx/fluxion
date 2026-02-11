<?php
namespace Fluxion;

use Fluxion\Query\QuerySql;

trait ModelMany
{

    public mixed $a;
    public mixed $b;

    protected string $left = 'a';
    protected string $right = 'b';

    public function getLeft(): string
    {
        return $this->left;
    }

    public function getRight(): string
    {
        return $this->right;
    }

    /**
     * @throws Exception
     */
    public function load($id): array
    {

        $query = new Query($this);

        return $query->filter($this->left, $id)->only($this->right)->toArray();

    }

    public function _filter(string|QuerySql $field, $value = null): Query
    {

        $query = new Query($this);

        return $query->filter($field, $value);

    }

    public function _query(): Query
    {

        return new Query($this);

    }

}
