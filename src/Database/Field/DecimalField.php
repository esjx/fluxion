<?php
namespace Fluxion\Database\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DecimalField extends FloatField
{

    protected string $_type = self::TYPE_DECIMAL;
    protected string $_type_target = 'float';

}
