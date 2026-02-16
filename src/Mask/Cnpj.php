<?php
namespace Fluxion\Mask;

use Fluxion\{Mask, DigitValidator};

class Cnpj extends Mask
{

    public string $mask = 'AA.AAA.AAA/AAAA-00';
    public string $placeholder = '__.___.___/____-__';
    public string $label = 'CNPJ';
    public int $max_length = 14;

    public static function validate(string $value): bool
    {

        $mask = get_called_class();

        /** @var self $obj */
        $obj = new $mask();

        return (preg_match($obj->pattern, $value) && DigitValidator::cnpj($value));

    }

}
