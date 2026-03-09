<?php
namespace Fluxion\Exception;

use Fluxion\FluxionException;
use Psr\Log\LogLevel;

class FileNotExistFluxionException extends FluxionException
{

    public function __construct(string $filename, string $log_level = LogLevel::WARNING)
    {
        parent::__construct("Arquivo '$filename' não existe!", [], $log_level);
    }

}
