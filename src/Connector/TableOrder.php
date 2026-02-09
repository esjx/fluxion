<?php
namespace Fluxion\Connector;

class TableOrder
{

    public function __construct(public int $id, public string $label, public array|string $order)
    {

    }

    public function __toString(): string
    {
        return "$this->label";
    }

}
