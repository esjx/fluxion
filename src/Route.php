<?php
namespace Fluxion;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Route
{

    public function __construct(public string $route, public ?array $methods = null, public ?array  $args = null)
    {
        
    }

}
