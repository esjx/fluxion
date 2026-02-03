<?php
namespace Fluxion\Connector;

class TableIndex
{

    public ?string $name = null;
    public bool $extra = true;
    public string $type;
    public bool $unique = false;

    /** @var array<string> */
    public array $columns = [];

    /** @var array<string> */
    public array $includes = [];

    public function __construct(array $columns = [], bool $unique = false, array $includes = []) {

        $this->columns = $columns;
        $this->unique = $unique;
        $this->includes = $includes;

    }

}
