<?php
namespace Fluxion\Connector;

use Generator;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Http\Message\StreamInterface;
use Fluxion\{Color, CustomException, Database\Table, MnModel2, Model2, SqlFormatter};
use Fluxion\Query\{Query2, QueryField, QueryWhere, QueryOrderBy, QueryGroupBy, QueryLimit};

abstract class Connector2
{

    //const DB_DATE_FORMAT = 'Y-m-d';
    //const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected ?PDO $_pdo;

    private static int $table_id = 0;

    protected ?StreamInterface $log_stream = null;

    protected bool $_connected = false;

    protected bool $_extra_break;

    protected ?array $_structure = null;
    protected ?string $_database = null;

    protected string $true_value = 'TRUE';
    protected string $false_value = 'FALSE';
    protected string $null_value = 'NULL';

    protected string $utf_prefix = '';

    public function setLogStream(StreamInterface $stream): void
    {
        $this->log_stream = $stream;
    }

    public function comment(string $text, string $color = Color::GRAY, bool $break_before = false): void
    {

        if (is_null($this->log_stream)) return;

        if ($break_before && $this->_extra_break) {
            $this->log_stream->write("\n");
        }

        $text = preg_replace('/(\'[\w\s,.-_()→]*\')/m', '<b><i>${1}</i></b>', $text);
        $text = preg_replace('/(\"[\w\s,.-_()→]*\")/m', '<b>${1}</b>', $text);

        $this->log_stream->write("<span style='color: $color;'>-- $text </span>\n");

        $this->_extra_break = true;

    }

    protected function logSql($sql): void
    {

        if (!is_null($this->log_stream)) {
            $this->log_stream->write(SqlFormatter::highlight($sql, false));
        }

    }

    protected function execute($comando): void
    {

        $this->logSql($comando);

        try {
            $this->getPDO()->exec($comando);
        }

        catch (PDOException $e) {

            $erro = $e->getMessage();
            $exp = explode('[SQL Server]', $erro);

            if (isset($exp[1])) {
                $erro = $exp[1];
            }

            $this->comment("<b>ERRO</b>: $erro", Color::RED);

        }

        if (!is_null($this->log_stream) && $this->_extra_break) {
            $this->log_stream->write("\n");
        }

        $this->_extra_break = false;

    }

    public function escape($value): string
    {

        if (is_string($value))
            return "$this->utf_prefix'" . str_replace("'", "''", $value) . "'";

        if (is_array($value)) {

            $ret = '';
            foreach ($value as $k)
                $ret .= (($ret != '') ? ", " : "") . $this->escape($k);

            return "($ret)";

        }

        if ($value === true)
            return $this->true_value;

        if ($value === false)
            return $this->false_value;

        if (is_null($value))
            return $this->null_value;

        return $value;

    }

    /** @throws PDOException */
    public function getPDO(): PDO
    {

        if (!$this->_connected) {

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT => true,
            ];

            $this->_pdo = new PDO($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $options);

            $this->_connected = true;

        }

        return $this->_pdo;

    }

    public function disconnect(): void
    {

        $this->_connected = false;
        $this->_pdo = null;

    }

    /** @throws PDOException */
    public function fetch(string $sql): Generator
    {

        $stmt = $this->getPDO()->query($sql);

        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $result;
        }

    }

    public function lastInsertId(PDOStatement $query, string $field_id)
    {

        $ret = $query->fetch(PDO::FETCH_ASSOC);

        return $ret[$field_id];

    }

    /** @throws CustomException */
    public function sync(string $class_name): void
    {

        /** @var Model2 $model */
        $model = new $class_name;

        $model->changeState(Model2::STATE_SYNC);

        $this->comment("<b>$class_name</b>\n", Color::ORANGE);

        # Criar a tabela principal

        $this->executeSync($model);

        # Criar as tabelas MN

        $many_to_many = $model->getManyToMany();

        foreach ($many_to_many as $key => $mn) {

            $this->comment("<b>Tabela MN para o campo '$key'</b>\n");

            $mn_model = new MnModel2($model, $key);

            $mn_model->changeState(Model2::STATE_SYNC);

            $mn_model->setComment(get_class($model) . " MN[$key]");

            # Criar a tabela de relacionamento

            $this->executeSync($mn_model);

        }

    }

    protected function executeSync(Model2 $model): void
    {

    }

    public function filter(QueryWhere $filter, Query2 $query, string $id): string
    {
        return '';
    }

    public function getTableId(): string
    {
        return "T" . $this::$table_id++;
    }

    /**
     * @throws CustomException
     */
    public function select(Query2 $query): ?Generator
    {

        $this::$table_id = 1;

        $class_name = get_class($query->getModel());

        /** @var Model2 $model */

        if ($class_name == MnModel2::class) {
            $model = new MnModel2();
        }

        else {
            $model = new $class_name();
        }

        $fields = $model->getFields();

        $sql = $this->sql_select($query) . ";\n";

        //echo "<pre>$sql</pre>";

        $this->logSql("$sql");

        foreach ($this->fetch($sql) as $result) {

            if (isset($result['total'])) {
                $model->getField('total')->setValue($result['total'], true);
            }

            foreach ($fields as $field) {

                if ($field->fake || $field->column_name == 'total') {
                    continue;
                }

                if (isset($result[$field->column_name])) {
                    $field->setValue($result[$field->column_name], true);
                }

                else {
                    $field->setValue(null, true);
                }

            }

            yield $model;

        }

    }

    public function sql_select(Query2 $query, bool $inline = false): string
    {
        return '';
    }

    public function delete(Query2 $query): string
    {
        return '';
    }

    public function drop(Query2 $query): string
    {

        return "DROP TABLE IF EXISTS {$arg['table']} CASCADE;";

    }

    public function insert($arg, Model2 $model, $force_fields = false): string
    {
        return '';
    }

    public function update($arg, Model2 $model): string
    {
        return '';
    }

}
