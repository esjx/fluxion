<?php
namespace Fluxion\Database;

use Attribute;
use Fluxion\Model2;
use Fluxion\CustomException;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany extends ForeignKey
{

    /** @throws CustomException */
    public function initialize(): void
    {

    }

}
