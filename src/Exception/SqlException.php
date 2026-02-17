<?php
namespace Fluxion\Exception;

use Fluxion\Exception;
use Psr\Log\LogLevel;

class SqlException extends Exception
{

    private string $_sql;
    private string $_original_message;

    public function __construct(string $message = '', string $sql = '', string $log_level = LogLevel::ERROR)
    {

        $needle = "[SQL Server]";

        $message = substr(strrchr($message, $needle), strlen($needle));

        $this->_sql = trim($sql);

        $this->_original_message = '[ERRO] ' . str_replace('. ', ". \n", $this->highlight($message));

        parent::__construct('Erro ao executar uma consulta no banco de dados!', [], $log_level);

    }

    public function getSql(): string
    {
        return $this->_sql;
    }

    public function getOriginalMessage(): string
    {
        return $this->_original_message;
    }

}
