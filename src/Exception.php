<?php
namespace Fluxion;

class Exception extends \Exception
{

    private bool $_log;

    protected string $_message;

    public function __construct(string $message = '', array $data = [], bool $log = true)
    {

        $this->_log = $log;

        $message = Message::create($message, $data);

        $this->_message = $this->highlight($message);

        parent::__construct(strip_tags($message));

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

    public function getAltMessage(): string
    {
        return $this->_message;
    }

}
