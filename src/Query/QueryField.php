<?php
namespace Fluxion\Query;

class QueryField {

    public function __construct(public string  $field,
                                public ?string $aggregator = null,
                                public ?string $name = null)
    {

    }

}
