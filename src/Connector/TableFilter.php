<?php
namespace Fluxion\Connector;

use Fluxion\Icon;

class TableFilter
{

    public string $icon;

    public function __construct(
        public string $field,
        public string $label,
        Icon          $icon = Icon::COLLECTION_ITEM,
        public array  $items = [],
        public bool   $multiple = true)
    {

        $this->icon = $icon->value;

    }

    public function __toString(): string
    {
        return "$this->label";
    }

}
