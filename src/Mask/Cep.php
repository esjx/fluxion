<?php
namespace Fluxion\Mask;

use Fluxion\Mask;

class Cep extends Mask
{

    public string $mask = '00.000-000';
    public string $placeholder = '__.___-___';
    public string $label = 'CEP';
    public int $max_length = 10;

}
