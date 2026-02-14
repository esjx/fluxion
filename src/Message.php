<?php
namespace Fluxion;

use Fluxion\Mask as Mask;

class Message
{

    public static function create($message = "", $data = []): string
    {

        $re = '/{{(?P<a>[\w\d]+)\:?(?P<b>[\w\d]*)\:?(?P<c>[\w\d\/]*)\:?(?P<d>[\w]*)}}/m';

        preg_match_all($re, $message, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {

            $texto = $data[$match['a']] ?? null;

            if ($match['b'] == 'fixed') {
                $texto = TextFormatter::padLeft($texto, $match['c'] ?? 1);
            }

            if ($match['b'] == 'cpf') {
                $texto = Mask\Cpf::mask($texto);
            }

            if ($match['b'] == 'cnpj') {
                $texto = Mask\Cnpj::mask($texto);
            }

            if ($match['b'] == 'number') {
                $texto = TextFormatter::number($texto, intval((!in_array($match['c'], ['', 'b', 'u', 'i'])) ? $match['c'] : 2));
            }

            if ($match['b'] == 'percent') {
                $texto = TextFormatter::number($texto, intval((!in_array($match['c'], ['', 'b', 'u', 'i'])) ? $match['c'] : 2)) . '%';
            }

            if ($match['b'] == 'date') {
                $texto = TextFormatter::date($texto, (strlen($match['c']) >= 2) ? $match['c'] : 'd/m/Y');
            }

            if ($match['b'] == 'b' || $match['c'] == 'b' || $match['d'] == 'b') {
                $texto = "<b>$texto</b>";
            }

            if ($match['b'] == 'u' || $match['c'] == 'u' || $match['d'] == 'u') {
                $texto = "<u>$texto</u>";
            }

            if ($match['b'] == 'i' || $match['c'] == 'i' || $match['d'] == 'i') {
                $texto = "<i>$texto</i>";
            }

            $message = str_replace($match[0], $texto, $message);

        }

        return $message;

    }

}
