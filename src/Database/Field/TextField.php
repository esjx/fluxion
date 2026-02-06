<?php
namespace Fluxion\Database\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class TextField extends StringField
{

    protected string $_type = self::TYPE_TEXT;

}
