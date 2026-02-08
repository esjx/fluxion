<?php
namespace Fluxion\Mask;

use Fluxion\Mask;

class EmpreendimentoBacen extends Mask
{

    public string $mask = '00000000000000';
    public string $placeholder = '00000000000000';
    public string $pattern_validator = '[1-2][1-4]\d{9}[0|4|8]\d[0|1|2|3|8]';
    public string $label = 'Empreendimento BACEN';
    public int $max_length = 14;

}
