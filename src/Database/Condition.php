<?php
namespace Fluxion\Database;

use Fluxion\Exception;

class Condition
{

    /**
     * @throws Exception
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
            throw new Exception("Tipo '$type' inválido!");
        }

    }

}
