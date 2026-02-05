<?php
namespace Fluxion\Connector;

use Generator;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Http\Message\StreamInterface;
use Fluxion\{Color, CustomException, MnModel2, Model2, SqlFormatter, State};
use Fluxion\Query\{Query2, QueryWhere};

abstract class Connector2
{

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
    protected string $default_value = 'DEFAULT';

    protected string $utf_prefix = '';

    public function setLogStream(StreamInterface $stream): void
    {
        $this->log_stream = $stream;
    }

    public function getLogStream(): ?StreamInterface
    {
        return $this->log_stream;
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

        if (is_string($value)) {

            if (preg_match('/^[\s\w\-.:]*$/i', $value)) {
                return "'" . str_replace("'", "''", $value) . "'";
            }

            return "$this->utf_prefix'" . str_replace("'", "''", $value) . "'";

        }

        if (is_array($value)) {

            $ret = [];
            foreach ($value as $k) {
                $ret[] = $this->escape($k);
            }

            return "(" . implode(", ", $ret) . ")";

        }

        if ($value === true) {
            return $this->true_value;
        }

        if ($value === false) {
            return $this->false_value;
        }

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

        $model->changeState(State::STATE_SYNC);

        $this->comment("<b>$class_name</b>\n", Color::ORANGE);

        # Criar a tabela principal

        $this->executeSync($model);

        # Criar as tabelas MN

        $many_to_many = $model->getManyToMany();

        foreach ($many_to_many as $key => $mn) {

            $this->comment("<b>Tabela MN para o campo '$key'</b>\n");

            $mn_model = new MnModel2($model, $key);

            $mn_model->changeState(State::STATE_SYNC);

            $mn_model->setComment(get_class($model) . " MN[$key]");

            # Criar a tabela de relacionamento

            $this->executeSync($mn_model);

        }

    }

    protected function executeSync(Model2 $model): void
    {

    }

    public function filter(QueryWhere $filter, Query2 $query, ?string $id): string
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

        $model->setSaved(true);

        $fields = $model->getFields();

        $sql = $this->sql_select($query) . ";\n";

        $this->comment("Executando consulta em '$class_name'", Color::ORANGE);

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

    public function sql_insert(Model2 $model, array $data = []): string
    {
        return '';
    }

    /**
     * @throws CustomException
     */
    public function execute_insert(Model2 $model, array $data = []): void
    {

        $class_name = get_class($model);

        $sql = $this->sql_insert($model, $data) . ';';

        $this->comment("Inserindo registro(s) em '$class_name'", Color::GREEN);

        $this->logSql($sql);

        $stmt = $this->getPDO()->query($sql);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        foreach ($model->getPrimaryKeys() as $key => $pk) {
            $field = $model->getField($key);
            $field->setValue($result[$field->column_name]);
        }

        foreach ($model->getFields() as $field) {
            if (!is_null($field->default)) {
                $field->setValue($result[$field->column_name]);
            }
        }

    }

    public function sql_update(Model2 $model, Query2 $query): ?string
    {
        return '';
    }

    /**
     * @throws CustomException
     */
    public function execute_update(Model2 $model): void
    {

        $query = $model::query();
        $class_name = get_class($model);

        $primary_keys = $model->getPrimaryKeys();

        if (count($primary_keys) == 0) {
            throw new CustomException("Model '$class_name' não possui chave primária definida");
        }

        foreach ($primary_keys as $key => $pk) {
            $field = $model->getField($key);
            $query = $query->filter($key, $field->getSavedValue());
        }

        $sql = $this->sql_update($model, $query);

        $this->comment("Atualizando registro(s) em '$class_name'", Color::ORANGE);

        if (!is_null($sql)) {

            $this->execute($sql . ';');

        }

        else {

            $this->comment("Nenhum campo atualizável");

        }

    }

    public function sql_delete(Query2 $query): string
    {
        return '';
    }

    public function sql_drop(Query2 $query): string
    {
        return '';
    }


    /**
     * @throws CustomException
     */
    public function save(Model2 $model): bool
    {

        $class_name = get_class($model);

        # Inserir dados na tabela principal

        if (!$model->isSaved()) {

            $primary_keys = $model->getPrimaryKeys();

            if (count($primary_keys) == 0) {
                throw new CustomException("Model '$class_name' já salvo e não possui chave primária definida");
            }

            $this->execute_insert($model);

        }

        # Atualizar dados na tabela principal

        else {

            $this->execute_update($model);

        }

        # Atualizar dados nas tabelas MN

        $this->comment("$model->_insert");

        #TODO

        return true;

    }

}
