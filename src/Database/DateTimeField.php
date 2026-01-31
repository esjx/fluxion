<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DateTimeField extends DateField
{

    protected string $_type = self::TYPE_DATETIME;

}
