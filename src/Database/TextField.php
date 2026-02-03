<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class TextField extends StringField
{

    protected string $_type = self::TYPE_TEXT;

}
