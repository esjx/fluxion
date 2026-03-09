<?php
namespace Fluxion\Database;

use Fluxion\FluxionException;

class Condition
{

    /**
     * @throws FluxionException
     */
    public function __construct(public string  $field,
                                public mixed   $value,
                                public string  $type,
                                public bool    $important = true,
                                public ?string $label = null,
                                public ?string $help = null,
                                public array   $choices = [])
    {

        if (!in_array($type, ['ne', '!=', '<>', 'e', '=', 'equal', '<', 'lt', '>', 'gt', '<=', 'lte', '>=', 'gte', 'has', 'in'])) {
            throw new FluxionException("Tipo '$type' inválido!");
        }

    }

}
