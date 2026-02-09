<?php
namespace Fluxion;

use Random\RandomException;

enum Color: string
{

    case RED = 'red';
    case PINK = 'pink';
    case PURPLE = 'purple';
    case DEEP_PURPLE = 'deep-purple';
    case INDIGO = 'indigo';
    case BLUE = 'blue';
    case LIGHT_BLUE = 'light-blue';
    case WHITE = 'white';
    case CYAN = 'cyan';
    case TEAL = 'teal';
    case GREEN = 'green';
    case LIGHT_GREEN = 'light-green';
    case LIME = 'lime';
    case YELLOW = 'yellow';
    case AMBER = 'amber';
    case ORANGE = 'orange';
    case DEEP_ORANGE = 'deep-orange';
    case BROWN = 'brown';
    case GREY = 'grey'; //TODO: retirar depois de ajustar no front-end
    case GRAY = 'gray';
    case BLUE_GREY = 'blue-grey';
    case BLACK = 'black';

    public static function getColors(): array
    {
        return array_values(self::cases());
    }

    public function code(): string
    {
        return self::getCode($this);
    }
    
    public static function getCode(self $value): string
    {
     
        return match ($value) {
            self::RED => '#ff6b68',
            self::PINK => '#ff85af',
            self::PURPLE => '#d066e2',
            self::DEEP_PURPLE => '#673ab7',
            self::INDIGO => '#3f51b5',
            self::BLUE => '#2196f3',
            self::LIGHT_BLUE => '#03a9f4',
            self::WHITE => '#ffffff',
            self::CYAN => '#00bcd4',
            self::TEAL => '#39bbb0',
            self::GREEN => '#32c787',
            self::LIGHT_GREEN => '#8bc34a',
            self::LIME => '#cddc39',
            self::YELLOW => '#ffeb3b',
            self::AMBER => '#ffc721',
            self::ORANGE => '#ff9800',
            self::DEEP_ORANGE => '#ff5722',
            self::BROWN => '#795548',
            self::GRAY, self::GREY => '#9e9e9e',
            self::BLUE_GREY => '#607d8b',
            self::BLACK => '#000000',
        };
        
    }

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {

        return match ($value) {
            self::RED => 'Vermelho',
            self::PINK => 'Rosa',
            self::PURPLE => 'Roxo',
            self::DEEP_PURPLE => 'Roxo Escuro',
            self::INDIGO => 'Anil',
            self::BLUE => 'Azul',
            self::LIGHT_BLUE => 'Azul Claro',
            self::WHITE => 'Branco',
            self::CYAN => 'Ciano',
            self::TEAL => 'Verde Azulado',
            self::GREEN => 'Verde',
            self::LIGHT_GREEN => 'Verde Claro',
            self::LIME => 'Lima',
            self::YELLOW => 'Amarelo',
            self::AMBER => 'Âmbar',
            self::ORANGE => 'Laranja',
            self::DEEP_ORANGE => 'Laranja Escuro',
            self::BROWN => 'Marrom',
            self::GRAY, self::GREY => 'Cinza',
            self::BLUE_GREY => 'Cinza Azulado',
            self::BLACK => 'Preto',
        };

    }

    /**
     * @throws RandomException
     */
    public static function random(): self
    {

        $colors = self::getColors();

        return $colors[random_int(0, count($colors) - 1)];

    }

}
