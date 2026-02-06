<?php
namespace Fluxion\Database;

use Attribute;
use Fluxion\MnModel2;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany extends ForeignKey
{

    protected MnModel2 $_mn_model;

    public function initialize(): void
    {

    }

}
