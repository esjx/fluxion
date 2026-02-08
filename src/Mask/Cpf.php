<?php
namespace Fluxion\Mask;

use Fluxion\Mask;

class Cpf extends Mask
{

    public string $mask = '000.000.000-00';
    public string $placeholder = '___.___.___-__';
    public string $label = 'CPF';
    public int $max_length = 11;

}
