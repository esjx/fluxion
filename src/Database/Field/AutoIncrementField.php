<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\{Field};
use Fluxion\Query\{QuerySql, QueryWhere};

#[Attribute(Attribute::TARGET_PROPERTY)]
class AutoIncrementField extends Field
{

    protected string $_type = self::TYPE_INTEGER;

    public ?bool $identity = true;
    public ?bool $enabled = false;

    public function __construct(public ?bool $protected = false,
                                public ?bool $readonly = true,
                                bool $needs_audit = true)
    {

        $this->required = true;
        $this->primary_key = true;
        $this->_needs_audit = $needs_audit;

        parent::__construct();

    }

    public function getSearch(string $value): ?QueryWhere
    {

        if (!is_numeric($value) || strlen($value) > 15) {
            return null;
        }

        return QuerySql::filter($this->_name, (int) $value);

    }

    public function isIdentity(): bool
    {
        return true;
    }

}
