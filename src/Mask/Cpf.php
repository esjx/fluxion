<?php
namespace Esj\Core\Mask;

class Cpf extends Mask
{

    public $mask = '000.000.000-00';
    public $placeholder = '___.___.___-__';
    public $label = 'CPF';
    public $max_length = 11;

}
