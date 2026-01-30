<?php
namespace Esj\Core\Mask;

class Cnpj extends Mask
{

    public $mask = '00.000.000/0000-00';
    public $placeholder = '__.___.___/____-__';
    public $label = 'CNPJ';

}
