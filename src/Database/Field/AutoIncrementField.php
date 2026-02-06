<?php
namespace Fluxion\Database\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AutoIncrementField extends Field
{

    protected string $_type = self::TYPE_INTEGER;

    public ?bool $identity = true;

    public function __construct(public ?bool $required = false,
                                public ?bool $protected = false,
                                public ?bool $readonly = true)
    {
        parent::__construct();
    }

}
