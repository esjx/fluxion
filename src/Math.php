<?php
namespace Fluxion;

class Math
{

    public static function limit($value, $min, $max): float
    {

        return max($min, min($max, $value));

    }

    public static function round($value, $precision = 6): float
    {

        if ($value >= 0) {
            return floor(round($value * 100, $precision)) / 100;
        }

        else {
            return ceil(round($value * 100, $precision)) / 100;
        }

    }

    public static function doubleRuler($value, $min, $max, $min2, $max2, $limit = true, $precision = 6): float
    {

        $out = self::round((round(($value - $min) * ($max2 - $min2), $precision) / ($max - $min)) + $min2);

        if ($limit) {
            $out = self::limit($out, $min2, $max2);
        }

        return $out;

    }

}
