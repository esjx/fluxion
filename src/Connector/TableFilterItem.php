<?php
namespace Fluxion\Connector;

use Fluxion\Color;

class TableFilterItem
{

    public ?string $color = null;

    public function __construct(public null|int|string|bool $id,
                                public string $label,
                                public bool $active,
                                ?Color $color = null)
    {
        $this->color = $color?->value;
    }

    public function __toString(): string
    {
        return "$this->label";
    }

}
