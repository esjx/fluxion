<?php
namespace Fluxion;

use Exception;

class CustomException extends Exception
{

    private $_log;

    public function __construct($message = "", $data = [], $log = true)
    {
        $this->_log = $log;
        parent::__construct(CustomMessage::create($message, $data));
    }

    public function getLog(): bool
    {
        return $this->_log;
    }

}
