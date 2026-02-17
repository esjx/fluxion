<?php
namespace Fluxion\Exception;

use Fluxion\Exception;
use Psr\Log\LogLevel;

class PageNotFoundException extends Exception
{

    public function __construct(string $page, string $log_level = LogLevel::WARNING)
    {
        parent::__construct("Página '$page' não encontrada!", [], $log_level);
    }

}
