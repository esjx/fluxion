<?php
namespace Fluxion\Connector;

use Fluxion\Action;
use Fluxion\Color;
use Fluxion\Exception;

class TableAction
{

    public ?string $id = null;

    /**
     * @throws Exception
     */
    public function __construct(public string  $label,
                                Action         $action,
                                ?Color         $color = null,
                                public string  $type = 'action',
                                public ?string $confirm = null,
                                public mixed   $extras = null,
                                public bool    $disabled = false)
    {

        $this->id = $action->value;

        if (!is_null($color) && !$this->disabled) {
            $this->label = "<span class=\"text-$color->value\">$label</span>";
        }

        if (!in_array($type, ['action', 'link', 'route', 'form'])) {
            throw new Exception("Tipo de ação '$this->type' inválida!");
        }

    }

    public function __toString(): string
    {
        return "$this->label";
    }

}
