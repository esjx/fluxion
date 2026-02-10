<?php
namespace Fluxion\Exception;

use Fluxion\Exception;

class PageNotFoundException extends Exception
{

    public function __construct(string $page, bool $log = true)
    {
        parent::__construct("Página '$page' não encontrada!", [], $log);
    }

}
