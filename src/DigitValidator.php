<?php
namespace Fluxion;

class DigitValidator
{

    public static function equality(string $value): bool
    {

        $char = str_split($value);

        foreach ($char as $digit) {

            if ($char[0] != $digit) {

                return false;

            }

        }

        return true;

    }

    public static function calc(string $digits, int $positions = 10, int $sum = 0): string
    {

        for ($i = 0; $i < strlen($digits); $i++) {

            $sum += (ord($digits[$i]) - 48) * $positions;

            $positions--;

            if ($positions < 2) {

                $positions = 9;

            }

        }

        $sum = $sum % 11;

        if ($sum < 2) {

            $sum = 0;

        } else {

            $sum = 11 - $sum;

        }

        return $digits . $sum;

    }

    public static function cpf(string $value): bool
    {

        $digits = substr($value, 0, 9);

        $new = self::calc($digits);

        $new = self::calc($new, 11);

        return (!self::equality($value) && $new == $value);

    }

    public static function cnpj(string $value): bool
    {

        $digits = substr($value, 0, 12);

        $new = self::calc($digits, 5);

        $new = self::calc($new, 6);

        return (!self::equality($value) && $new == $value);

    }

}
