<?php
namespace Esj\Core\Mask;

class EmpreendimentoBacen extends Mask
{

    public $mask = '00000000000000';
    public $placeholder = '00000000000000';
    public $pattern_validator = '[1-2][1-4]\d{9}[0|4|8]\d[0|1|2|3|8]';
    public $label = 'Empreendimento BACEN';
    public $max_length = 14;

}
