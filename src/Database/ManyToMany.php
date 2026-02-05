<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany extends ForeignKey
{

    public function initialize(): void
    {

    }

}
