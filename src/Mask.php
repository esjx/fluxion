<?php
namespace Fluxion;

use Fluxion\Exception\MaskException;

abstract class Mask
{

    public string $mask = '';
    public string $placeholder = '';
    public string $pattern = '';
    public string $pattern_validator = '';
    public string $pattern_message = '';
    public string $label = '';
    public int $max_length = 255;

    public static function mask(?string $value): string
    {

        if (is_null($value)) {
            return '';
        }

        $mask = get_called_class();

        /** @var self $obj */
        $obj = new $mask();

        $out = '';
        $pos = 0;

        for ($i = 0; $i <= strlen($obj->mask) - 1; $i++) {

            if (in_array($obj->mask[$i], ['0', 'A', '#'])) {

                if (isset($value[$pos])) {

                    $out .= $value[$pos++];

                }

            } else {

                $out .= $obj->mask[$i];

            }

        }

        return $out;

    }

    public static function validate(string $value): bool
    {

        $mask = get_called_class();

        /** @var self $obj */
        $obj = new $mask();

        if (empty($obj->pattern)) {
            return true;
        }

        return preg_match($obj->pattern, $value);

    }

    /**
     * @throws MaskException
     */
    public static function explode(string $value): array
    {

        $mask = get_called_class();

        /** @var self $obj */
        $obj = new $mask();

        if (!preg_match($obj->pattern, $value, $data)) {

            throw new MaskException(
                label: $obj->label,
                value: $value,
                message: $obj->pattern_message ?? htmlspecialchars($obj->pattern),
                log: false
            );

        }

        return $data;

    }

}
