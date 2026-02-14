<?php
namespace Fluxion;

use DateTime;
use Exception;
use IntlDateFormatter;

trait Format
{

    public static function money(?float $value, int $decimals = 2): string
    {
        return 'R$ ' . self::number($value, $decimals);
    }

    private static function numberBase(?float $value, int $decimals, int $mod, string $list): string
    {

        if (is_null($value)) {
            return '';
        }

        $trigger = 0.95;

        $units = explode(' ',$list);

        for ($i = 0; $value > ($mod * $trigger); $i++) {
            $value /= $mod;
        }

        $suffix = $units[$i];

        $number = self::number($value, $decimals);

        if ($suffix) {
            $number .= ' ' . $suffix;
        }

        return $number;

    }

    public static function number(?float $value, int $decimals = 2): string
    {

        if (is_null($value)) {
            return '';
        }

        $decimal_separator = $_ENV['DECIMAL_SEPARATOR'] ?? ',';
        $thousands_separator = $_ENV['THOUSANDS_SEPARATOR'] ?? '.';

        return trim(number_format($value, $decimals, $decimal_separator, $thousands_separator));

    }

    public static function number2(?float $value, int $decimals = 2): string
    {
        return self::numberBase($value, $decimals, 1000, '0 mil mi bi tri qua qui');
    }

    public static function fullNumber(float $value = 0, bool $currency = true, bool $feminine = false): string
    {

        if ($currency) {
            $singular = ["centavo", "real", "mil", "milhão", "bilhão", "trilhão", "quatrilhão"];
            $plural = ["centavos", "reais", "mil", "milhões", "bilhões", "trilhões", "quatrilhões"];
        }

        else {
            $singular = ["", "", "mil", "milhão", "bilhão", "trilhão", "quatrilhão"];
            $plural = ["", "", "mil", "milhões", "bilhões", "trilhões", "quatrilhões"];
        }

        $c = ["", "cem", "duzentos", "trezentos", "quatrocentos", "quinhentos", "seiscentos", "setecentos", "oitocentos", "novecentos"];
        $d = ["", "dez", "vinte", "trinta", "quarenta", "cinquenta", "sessenta", "setenta", "oitenta", "noventa"];
        $d10 = ["dez", "onze", "doze", "treze", "quatorze", "quinze", "dezesseis", "dezessete", "dezoito", "dezenove"];
        $u = ["", "um", "dois", "três", "quatro", "cinco", "seis", "sete", "oito", "nove"];


        if ($feminine) {

            if ($value == 1) {
                $u = ["", "uma", "duas", "três", "quatro", "cinco", "seis", "sete", "oito", "nove"];
            }

            else {
                $u = ["", "um", "duas", "três", "quatro", "cinco", "seis", "sete", "oito", "nove"];
            }

            $c = ["", "cem", "duzentas", "trezentas", "quatrocentas", "quinhentas", "seiscentas", "setecentas", "oitocentas", "novecentas"];

        }

        $z = 0;

        $value = number_format($value, 2, ".", ".");
        $inteiro = explode(".", $value);

        for ($i = 0; $i < count($inteiro); $i++) {
            for ($ii = mb_strlen($inteiro[$i]); $ii < 3; $ii++) {
                $inteiro[$i] = "0" . $inteiro[$i];
            }
        }

        // $fim identifica onde que deve se dar junção de centenas por "e" ou por "," ;)
        $rt = null;
        $fim = count( $inteiro ) - ($inteiro[count( $inteiro ) - 1] > 0 ? 1 : 2);

        for ($i = 0; $i < count($inteiro); $i++) {

            $value = $inteiro[$i];
            $rc = (($value > 100) && ($value < 200)) ? "cento" : $c[$value[0]];
            $rd = ($value[1] < 2) ? "" : $d[$value[1]];
            $ru = ($value > 0) ? (($value[1] == 1) ? $d10[$value[2]] : $u[$value[2]]) : "";

            $r = $rc . (($rc && ($rd || $ru)) ? " e " : "") . $rd . (($rd && $ru) ? " e " : "") . $ru;
            $t = count($inteiro) - 1 - $i;
            $r .= $r ? " " . ($value > 1 ? $plural[$t] : $singular[$t]) : "";
            if ($value == "000")
                $z++;
            elseif ($z > 0)
                $z--;

            if (($t == 1) && ($z > 0) && ($inteiro[0] > 0))
                $r .= (($z > 1) ? " de " : "") . $plural[$t];

            if ($r)
                $rt = $rt . ((($i > 0) && ($i <= $fim) && ($inteiro[0] > 0) && ($z < 1)) ? (($i < $fim) ? ", " : " e ") : " ") . $r;

        }

        $rt = mb_substr($rt, 1);

        return mb_strtoupper($rt ? trim($rt) : "zero reais", 'utf8');

    }

    public static function dateTime(?string $date, string $format = 'd/m/Y H:i:s'): ?string
    {

        if (is_null($date)) {
            return null;
        }

        try {
            return (new DateTime($date))->format($format);
        }

        catch (Exception) {
            return null;
        }

    }

    public static function date(?string $date, string $format = 'd/m/Y'): ?string
    {
        return self::dateTime($date, $format);
    }

    public static function fullDate(string $data, $full = false): ?string
    {

        try {

            $data = new DateTime($data);

            $type = ($full) ? IntlDateFormatter::FULL : IntlDateFormatter::LONG;

            $formatter = new IntlDateFormatter('pt_BR',
                $type,
                IntlDateFormatter::NONE,
                'America/Sao_Paulo',
                IntlDateFormatter::GREGORIAN);

            return $formatter->format($data);

        }

        catch (Exception) {
            return null;
        }

    }

    public static function time(?int $minutes): ?string
    {

        if (is_null($minutes)) {
            return '--:--';
        }

        $hours = floor($minutes / 60);
        $minutes %= 60;

        return sprintf("%02d:%02d", $hours, $minutes);

    }

    public static function size(?float $size, int $decimals = 2): string
    {
        return self::numberBase($size, $decimals, 1024, 'B KB MB GB TB PB');
    }

    public static function abreviaNome($nome): string
    {

        $partes = explode(' ', $nome);

        $meio = ' ';

        for ($i = 1; $i < (count($partes) - 1); $i++) {

            if (strlen($partes[$i]) > 3) {

                $meio .= substr($partes[$i], 0, 1) . ' ';

            }

        }

        return $partes[0] . $meio . $partes[count($partes) - 1];

    }

    public static function sanitize($str): string
    {

        $str = preg_replace('/[áàãâä]/u', 'a', $str);
        $str = preg_replace('/[éèêë]/u', 'e', $str);
        $str = preg_replace('/[íìîï]/u', 'i', $str);
        $str = preg_replace('/[óòõôö]/u', 'o', $str);
        $str = preg_replace('/[úùûü]/u', 'u', $str);
        $str = preg_replace('/ç/u', 'c', $str);

        $str = preg_replace('/[ÁÀÃÂÄ]/u', 'A', $str);
        $str = preg_replace('/[ÉÈÊË]/u', 'E', $str);
        $str = preg_replace('/[ÍÌÎÏ]/u', 'I', $str);
        $str = preg_replace('/[ÓÒÕÔÖ]/u', 'O', $str);
        $str = preg_replace('/[ÚÙÛÜ]/u', 'U', $str);
        $str = preg_replace('/Ç/u', 'c', $str);

        $str = preg_replace('/[^a-z0-9]/i', ' ', $str);
        $str = preg_replace('/\s+/', ' ', $str);

        return trim($str);

    }

    public static function onlyNumbers($str): string
    {

        return preg_replace('/[^0-9]/i', '', $str);

    }

    public static function padLeft(?string $string, int $length = 4, string $pad_string = '0'): ?string
    {

        if (is_null($string)) {
            return null;
        }

        return str_pad($string, $length, $pad_string, STR_PAD_LEFT);

    }

}
