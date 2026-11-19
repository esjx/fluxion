<?php
namespace Fluxion\Connector;

class TableTab
{

    public function __construct(public null|string|bool|int $id, public string $label, public int $items)
    {

    }

    public function __toString(): string
    {
        return "$this->label";
    }

}
