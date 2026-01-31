<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ForeignKey
{

    protected string $_name;

    function setName(string $name): void
    {
        $this->_name = $name;
    }

    public function __construct(public string $class,
                                public ?bool $fake = true,
                                public ?bool $show = false,
                                public ?array $filter = null)
    {

    }

}
