<?php
namespace Fluxion\Connector;

use Fluxion\Action;
use Fluxion\Color;

class TableAction
{

    public ?string $id = null;

    public function __construct(public string $label,
                                Action $action,
                                ?Color $color = null,
                                public string $type = 'type',
                                public ?string $confirm = null,
                                public bool $disabled = false)
    {

        $this->id = $action->value;

        if (!is_null($color)) {
            $this->label = "<span class=\"text-$color->value\">$label</span>";
        }

    }

    public function __toString(): string
    {
        return "$this->label";
    }

}
