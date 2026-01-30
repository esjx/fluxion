<?php
namespace Esj\Core\Mask;

class Cnm extends Mask
{

    public $mask = '000000.0.0000000-00';
    public $placeholder = '______._._______-__';
    public $pattern = '/^(?P<cartorio>\d{6})(?P<livro>[2-3])(?P<matricula>\d{7})(?P<digito>\d{2})$/i';
    public $pattern_message = 'CCCCCC.L.NNNNNNN-DD, onde CCCCCC é  Código Nacional da Serventia - CNS, L é o livro (2 ou 3), NNNNNNN é o número de ordem e DD é dígito verificador';
    public $label = 'CNM';
    public $max_length = 16;

}
