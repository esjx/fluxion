<?php
namespace Fluxion;

use DateTime;
use Exception;

class Format
{

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

    public static function datetime(?string $date, string $format = 'd/m/Y H:i:s'): ?string
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
        return self::datetime($date, $format);
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

}
