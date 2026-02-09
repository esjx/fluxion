<?php
namespace Fluxion\Menu;

class MenuItem
{

    public function __construct(public string $title,
                                public string $route,
                                public bool $visible)
    {
    }

}
