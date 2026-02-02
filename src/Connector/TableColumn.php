<?php
namespace Fluxion\Connector;

class TableColumn
{

    public string $name;
    public int $id;
    public string $type;
    public bool $nullable;
    public bool $required;
    public bool $identity;
    public ?int $max_length = null;
    public ?int $precision = null;
    public ?int $scale = null;
    public bool $extra = true;

}
