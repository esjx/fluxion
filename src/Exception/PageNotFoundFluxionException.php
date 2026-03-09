<?php
namespace Fluxion\Exception;

use Fluxion\FluxionException;
use Psr\Log\LogLevel;

class PageNotFoundFluxionException extends FluxionException
{

    public function __construct(string $page, string $log_level = LogLevel::WARNING)
    {
        parent::__construct("Página '$page' não encontrada!", [], $log_level);
    }

}
