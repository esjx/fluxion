<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Typeahead
{

    protected string $_name;

    function setName(string $name): void
    {
        $this->_name = $name;
    }

    public function __construct(public string $url)
    {

    }

}
