<?php
namespace Fluxion;

class Exception extends \Exception
{

    private bool $_log;

    public function __construct(string $message = "", array $data = [], bool $log = true)
    {
        $this->_log = $log;
        parent::__construct(Message::create($message, $data));
    }

    public function getLog(): bool
    {
        return $this->_log;
    }

}
