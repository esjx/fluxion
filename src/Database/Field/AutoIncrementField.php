<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\Field;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AutoIncrementField extends Field
{

    protected string $_type = self::TYPE_INTEGER;

    public ?bool $identity = true;
    public ?bool $enabled = false;

    public function __construct(public ?bool $protected = false,
                                public ?bool $readonly = true)
    {

        $this->required = true;
        $this->primary_key = true;

        parent::__construct();

    }

    public function isIdentity(): bool
    {
        return true;
    }

}
