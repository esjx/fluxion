<?php
namespace Fluxion\Connector;

use Fluxion\{Color, ColorLink};

class TableTag
{

    public string $color;

    public function __construct(public string $label,
                                Color|ColorLink $color,
                                public ?string $link = null)
    {
        $this->color = $color->value;
    }

    public function __toString(): string
    {
        return "$this->label";
    }

}
