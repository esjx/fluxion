<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PrimaryKey
{

    protected string $_name;

    function setName(string $name): void
    {
        $this->_name = $name;
    }

}
