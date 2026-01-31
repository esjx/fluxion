<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Filterable
{

    protected string $_name;
    private bool $_active = true;

    function setName(string $name): void
    {
        $this->_name = $name;
    }

    function setActive($active): void
    {
        $this->_active = $active;
    }

    function getActive(): bool
    {
        return $this->_active;
    }

}
