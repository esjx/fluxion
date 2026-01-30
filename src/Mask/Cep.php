<?php
namespace Esj\Core\Mask;

class Cep extends Mask
{

    public $mask = '00.000-000';
    public $placeholder = '__.___-___';
    public $label = 'CEP';
    public $max_length = 10;

}
