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

        foreach (self::$_items as $_item) {

            if ($_item->route == '/') {

                $_item->visible = true;
                continue;

            }

            $_item->visible = false;

            foreach ($_item->sub as $_sub) {

                if ($_sub->visible) {

                    $_item->visible = true;
                    break;

                }

            }

        }

        return self::$_items;

    }

}
