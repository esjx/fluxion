<?php
namespace Fluxion\Exception;

use Fluxion\Exception;

class SqlException extends Exception
{

    private string $_sql;
    private string $_original_message;

    public function __construct(string $message = '', string $sql = '', bool $log = true)
    {

        $needle = "[SQL Server]";

        $message = substr(strrchr($message, $needle), strlen($needle));

        $this->_sql = trim($sql);

        $this->_original_message = '[ERRO] ' . $this->highlight($message);

        parent::__construct('Erro ao executar uma consulta no banco de dados!', [], $log);

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
