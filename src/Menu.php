<?php
namespace Fluxion;

use Fluxion\Menu\MenuGroup;

class Menu
{

    /** @var MenuGroup[] */
    private static array $_items = [];

    public static function add(MenuGroup $item): void
    {

        foreach (self::$_items as $_item) {

            if ($_item->route == $item->route) {

                $_item->sub = array_merge($_item->sub, $item->sub);

                return;

            }

        }

        self::$_items[] = $item;

    }

    public static function get(): array
    {

        #TODO: Ajustar visibilidade

        return self::$_items;

    }

}
