<?php
namespace Fluxion\Exception;

use Fluxion\Exception;

class FileNotExistException extends Exception
{

    public function __construct(string $filename, bool $log = true)
    {
        parent::__construct("Arquivo '$filename' não existe!", [], $log);
    }

}
