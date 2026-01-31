<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ColorField extends Field
{

    protected string $_type = self::TYPE_COLOR;

    const COLORS = [ // TODO: Passar para uma classe
        'yellow' => 'Amarelo',
        'amber' => 'Âmbar',
        'indigo' => 'Anil',
        'blue' => 'Azul',
        'light-blue' => 'Azul Claro',
        //'white' => 'Branco',
        'cyan' => 'Ciano',
        //'grey' => 'Cinza',
        //'blue-grey' => 'Cinza Azulado',
        'orange' => 'Laranja',
        'deep-orange' => 'Laranja Escuro',
        'lime' => 'Lima',
        'brown' => 'Marrom',
        'black' => 'Preto',
        'pink' => 'Rosa',
        'purple' => 'Roxo',
        //'deep-purple' => 'Roxo Escuro',
        'green' => 'Verde',
        'teal' => 'Verde Azulado',
        'light-green' => 'Verde Claro',
        'red' => 'Vermelho',
    ];

    const COLOR_MAP = [
        'red' => 'FF6B68',
        'pink' => 'ff85af',
        'purple' => 'd066e2',
        'deep-purple' => '673AB7',
        'indigo' => '3F51B5',
        'blue' => '2196F3',
        'light-blue' => '03A9F4',
        'cyan' => '00BCD4',
        'teal' => '39bbb0',
        'green' => '32c787',
        'light-green' => '8BC34A',
        'lime' => 'CDDC39',
        'yellow' => 'FFEB3B',
        'amber' => 'ffc721',
        'orange' => 'FF9800',
        'deep-orange' => 'FF5722',
        'brown' => '795548',
        'grey' => '9E9E9E',
        'gray' => '9E9E9E',
        'blue-grey' => '607D8B',
        'black' => '000000',
    ];

    public ?array $choices = self::COLORS;
    public ?int $max_length = 15;

    public function __construct(public ?string $label = 'Cor',
                                public ?string $mask_class = null,
                                public ?bool   $required = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public ?int    $size = 12)
    {
        parent::__construct();
    }

}
