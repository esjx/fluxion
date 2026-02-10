<?php
namespace Fluxion;

use Exception as _Exception;
use DateTime;

enum Time: string
{

    case YESTERDAY = '-1 day|Y-m-d';
    case TODAY = '|Y-m-d';
    case TOMORROW = '+1 day|Y-m-d';

    case ONE_HOUR_AGO = '-1 hour';
    case NOW = '';
    case ONE_HOUR_LATER = '+1 hour';

    public function value(): ?string
    {

        try {

            $parts = explode('|', $this->value);

            $date = new DateTime($parts[0]);

            return $date->format($parts[1] ?? 'Y-m-d H:i:s');

        } catch (_Exception) {
            return null;
        }

    }

    public static function convert(?string $value, string $format): ?string
    {

        if (is_null($value)) {
            return null;
        }

        try {

            $date = new DateTime($value);

            return $date->format($format);

        } catch (_Exception) {
            return null;
        }

    }

}
