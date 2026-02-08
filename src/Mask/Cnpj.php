<?php
namespace Fluxion\Mask;

use Fluxion\Mask;

class Cnpj extends Mask
{

    public string $mask = '00.000.000/0000-00';
    public string $placeholder = '__.___.___/____-__';
    public string $label = 'CNPJ';
    public int $max_length = 14;

}
