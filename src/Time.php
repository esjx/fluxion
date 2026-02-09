<?php
namespace Fluxion;

use Exception as _Exception;
use DateTime;

enum Time: string
{

    case TODAY = '|Y-m-d';
    case NOW = '';
    case ONE_HOUR_AGO = '-1 hour';

    public function value(): ?string
    {
        return self::getValue($this);
    }

    public static function getValue(self $value): ?string
    {

        try {

            $parts = explode('|', $value->value);

            $date = new DateTime($parts[0]);

            return $date->format($parts[1] ?? 'Y-m-d H:i:s');

        } catch (_Exception) {
            return null;
        }
        
    }

}
