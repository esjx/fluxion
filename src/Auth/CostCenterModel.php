<?php
namespace Fluxion\Auth;

use Fluxion\{Model};
use Fluxion\Database\Field\{IntegerField};

class CostCenterModel extends Model
{

    #[IntegerField(required: true)]
    public ?int $id;

}
