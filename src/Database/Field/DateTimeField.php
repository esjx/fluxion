<?php
namespace Fluxion\Database\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DateTimeField extends DateField
{

    protected string $_type = self::TYPE_DATETIME;

    protected string $date_format = 'Y-m-d H:i:s';

}
