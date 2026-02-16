<?php
namespace Fluxion\Mask;

use Fluxion\{Mask, DigitValidator};

class Cpf extends Mask
{

    public string $mask = '000.000.000-00';
    public string $placeholder = '___.___.___-__';
    public string $label = 'CPF';
    public int $max_length = 11;

    public static function validate(string $value): bool
    {

        $mask = get_called_class();

        /** @var self $obj */
        $obj = new $mask();

        return (preg_match($obj->pattern, $value) && DigitValidator::cpf($value));

    }

}
