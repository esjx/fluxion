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

        $formats = ['Y-m-d H:i:s.v', 'Y-m-d H:i:s', 'Y-m-d'];

        foreach ($formats as $format_in) {

            $date = DateTime::createFromFormat($format_in, $value);

            if ($date !== false) {
                break;
            }

        }

        if ($date === false) {
            return null;
        }

        return $date->format($format);

    }

}
