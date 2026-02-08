<?php
namespace Fluxion\Mask;

use Fluxion\Mask;

class OrdemServico extends Mask
{

    public $mask = '0000.0000.000000000.0000.00.00';
    public $placeholder = '____.____._________.____.__.__';
    public $label = 'Ordem de Serviço';
    public $max_length = 25;

}
