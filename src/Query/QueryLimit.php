<?php
namespace Fluxion\Query;

use Fluxion\FluxionException;

class QueryLimit {

    /**
     * @throws FluxionException
     */
    public function __construct(public int $limit, public int $offset = 0)
    {

        if ($this->limit < 1) {
            throw new FluxionException("Valor de limite '$this->limit' inválido!");
        }

        if ($this->offset < 0) {
            throw new FluxionException("Valor de offset '$this->offset' inválido!");
        }

    }

}
