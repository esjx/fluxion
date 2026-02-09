<?php
namespace Fluxion\Connector;

class TableTag
{

    public function __construct(public string $label)
    {

    }

    public function __toString(): string
    {
        return "$this->label";
    }

}
