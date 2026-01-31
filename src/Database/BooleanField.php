<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BooleanField extends Field
{

    protected string $_type = self::TYPE_BOOLEAN;
    protected string $_type_target = 'bool';

    public function __construct(public ?string $label = null,
                                public ?bool $required = false,
                                public ?bool $protected = false,
                                public ?bool $readonly = false,
                                public ?int $size = 12)
    {
        parent::__construct();
    }

}
