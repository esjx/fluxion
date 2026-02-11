<?php
namespace Fluxion;

class Exception extends \Exception
{

    private bool $_log;

    public function __construct(string $message = '', array $data = [], bool $log = true)
    {

        $this->_log = $log;

        parent::__construct(Message::create($this->highlight($message), $data));

    }

    public function highlight($message): string
    {

        $message = preg_replace('/(\'[\w\s,.-_()→]*\')/m', '<b><i>${1}</i></b>', $message);
        $message = preg_replace('/(\"[\w\s,.-_()→]*\")/m', '<b><i>${1}</i></b>', $message);

        return "$message";

    }

    public function getLog(): bool
    {
        return $this->_log;
    }

}
