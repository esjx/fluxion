<?php
namespace Fluxion;

use Psr\Log\{LogLevel};

class Exception extends \Exception
{

    private string $_log_level;

    protected string $_message;

    public function __construct(string $message = '', array $data = [], string $log_level = LogLevel::ERROR)
    {

        $this->_log_level = $log_level;

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

    public function getLogLevel(): string
    {
        return $this->_log_level;
    }

    public function getAltMessage(): string
    {
        return $this->_message;
    }

}
