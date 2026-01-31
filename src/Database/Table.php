<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Table
{

    public function __construct(public string $name, public ?bool $view = false)
    {

    }

}
