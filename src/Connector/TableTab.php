<?php
namespace Fluxion\Connector;

class TableTab
{

    public function __construct(public ?int $id, public string $label, public int $itens)
    {

    }

    public function __toString(): string
    {
        return "$this->label";
    }

}
