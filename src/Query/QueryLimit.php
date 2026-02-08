<?php
namespace Fluxion\Query;

use Fluxion\Exception;

class QueryLimit {

    /**
     * @throws Exception
     */
    public function __construct(public int $limit, public int $offset = 0)
    {

        if ($this->limit < 1) {
            throw new Exception("Valor de limite '$this->limit' inválido!");
        }

        if ($this->offset < 0) {
            throw new Exception("Valor de offset '$this->offset' inválido!");
        }

    }

}
