<?php
namespace Fluxion\Exception;

use Fluxion\Exception;
use Psr\Log\LogLevel;

class FileNotExistException extends Exception
{

    public function __construct(string $filename, string $log_level = LogLevel::WARNING)
    {
        parent::__construct("Arquivo '$filename' não existe!", [], $log_level);
    }

}
