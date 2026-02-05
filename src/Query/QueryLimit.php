<?php
namespace Fluxion\Query;

use Fluxion\CustomException;

class QueryLimit {

    /**
     * @throws CustomException
     */
    public function __construct(public int $limit, public int $offset = 0)
    {

        if ($this->limit < 1) {
            throw new CustomException("Valor de limite '$this->limit' inválido!");
        }

        if ($this->offset < 0) {
            throw new CustomException("Valor de offset '$this->offset' inválido!");
        }

    }

}
