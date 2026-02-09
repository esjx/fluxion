<?php
namespace Fluxion\Menu;

use Fluxion\Icon;

class MenuGroup
{

    public string $icon;

    public function __construct(public string $title,
                                public string $route,
                                Icon $icon,
                                public array $sub = [],
                                public string $visibility = 'inactive',
                                public bool $visible = true)
    {

        $this->icon = $icon->value;

    }

    public function addSub(MenuItem $item): void
    {
        $this->sub[] = $item;
    }

}
