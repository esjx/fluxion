<?php
namespace Esj\Core\Mask;

class Car extends Mask
{

    public $mask = 'AA-0000000-AAAA.AAAA.AAAA.AAAA.AAAA.AAAA.AAAA.AAAA';
    public $placeholder = 'DF-0000000-0000.0000.0000.0000.0000.0000.0000.0000';
    public $pattern = '/^(?P<estado>[a-z]{2})(?P<municipio>[0-9]{7})(?P<imovel>[0-9a-f]{32})$/i';
    public $label = 'CAR';

}
