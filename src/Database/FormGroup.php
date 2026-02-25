<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class FormGroup
{

    public function __construct(public string $label)
    {

    }

}
