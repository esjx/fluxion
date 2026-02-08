<?php
namespace Fluxion\Mask;

use Fluxion\Mask;

class Car extends Mask
{

    public string $mask = 'AA-0000000-AAAA.AAAA.AAAA.AAAA.AAAA.AAAA.AAAA.AAAA';
    public string $placeholder = 'DF-0000000-0000.0000.0000.0000.0000.0000.0000.0000';
    public string $pattern = '/^(?P<estado>[a-z]{2})(?P<municipio>[0-9]{7})(?P<imovel>[0-9a-f]{32})$/i';
    public string $label = 'CAR';
    public int $max_length = 41;

}
