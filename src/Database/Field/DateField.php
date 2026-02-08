<?php
namespace Fluxion\Database\Field;

use Attribute;
use DateTime;
use Fluxion\Database\Field;
use Fluxion\Exception;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DateField extends Field
{

    protected string $_type = self::TYPE_DATE;

    protected string $date_format = 'Y-m-d';

    public function __construct(public ?bool           $required = false,
                                public ?bool           $protected = false,
                                public ?bool           $readonly = false,
                                public null|int|string $min_value = null,
                                public null|int|string $max_value = null,
                                public mixed           $default = null,
                                public bool            $default_literal = false)
    {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    public function validate(mixed &$value): bool
    {

        if (!parent::validate($value)) {
            return false;
        }

        if (empty($value)) {
            $value = null;
        }

        else {

            $formats = ['Y-m-d H:i:s.v', 'Y-m-d H:i:s', 'Y-m-d'];

            foreach ($formats as $format) {

                $date = DateTime::createFromFormat($format, $value);

                if ($date !== false) {
                    break;
                }

            }

            if ($date === false) {
                throw new Exception("Invalid date format: $value");
            }

            else {
                $value = $date->format($this->date_format);
            }

        }

        return true;

    }

}
