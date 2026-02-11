<?php
namespace Fluxion;

use Generator;
use PDO;
use PDOException;
use PDOStatement;
use ReflectionException;
use Fluxion\Query\{QueryWhere};
use Fluxion\Exception\SqlException;
use Psr\Http\Message\StreamInterface;

abstract class Connector
{

    protected ?PDO $_pdo;

    private static int $table_id = 0;

    protected ?StreamInterface $log_stream = null;

    protected bool $_connected = false;

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

    /** @noinspection PhpUnused */
    public function getLogStream(): ?StreamInterface
    {
        return $this->log_stream;
    }

    protected bool $extra_break = false;

    public function comment(string $text, Color $color = Color::GRAY, bool $break_before = false, bool $break_after = false): void
    {

        if (is_null($this->log_stream)) return;

        if ($break_before || $this->extra_break) {
            $this->log_stream->write("\n");
        }

        $this->extra_break = $break_after;

        $text = preg_replace('/(\'[\w\s,.-_()→]*\')/m', '<b><i>${1}</i></b>', $text);
        $text = preg_replace('/(\"[\w\s,.-_()→]*\")/m', '<b>${1}</b>', $text);

        $this->log_stream->write("<span style='color: {$color->code()};'>-- $text </span>\n");

    }

    public function rowCountLog(int $count): void
    {

        if ($count == 0) {
            $this->comment(text: "Nenhum registro alterado", break_after: true);
        }

        elseif ($count == -1) {
            $this->comment(text: "Identificação de registros alterados não possível", break_after: true);
        }

        elseif ($count == 1) {
            $this->comment(text: "<b>1</b> registro alterado", break_after: true);
        }

        else {
            $this->comment(
                text: Message::create("{{count:number:0:b}} registros alterados", ['count' => $count]),
                break_after: true
            );
        }

    }

    protected function logSql($sql): void
    {

        $this->extra_break = false;

        if (!is_null($this->log_stream)) {
            $this->log_stream->write(SqlFormatter::highlight($sql, false) . "\n\n");
        }

    }

    protected function execute($sql, bool $break_after = false): int
    {

        $this->logSql($sql);

        $this->extra_break = $break_after;

        try {

            $stmt = $this->prepare($sql);
            $stmt->execute();

            return $stmt->rowCount();

        }

        catch (PDOException|SqlException $e) {

            $erro = $e->getMessage();
            $this->comment("<b>ERRO</b>: $erro", Color::RED);

        }

        return -1;

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

        if (is_null($value)) {
            return $this->null_value;
        }

        return $value;

    }

    protected array $pdo_options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_PERSISTENT => true,
    ];

    /** @throws PDOException */
    public function getPDO(): PDO
    {

        if (!$this->_connected) {

            $this->_pdo = new PDO($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $this->pdo_options);

            $this->_connected = true;

        }

        return $this->_pdo;

    }

    /** @throws SqlException */
    public function fetch(string $sql): Generator
    {

        $stmt = $this->query($sql);

        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $result;
        }

    }

    public function lastInsertId(PDOStatement $query, string $field_id)
    {

        $ret = $query->fetch(PDO::FETCH_ASSOC);

        return $ret[$field_id];

    }

    public function getTableId(): string
    {
        return "T" . $this::$table_id++;
    }

    /** @throws Exception
     * @throws ReflectionException
     */
    public function sync(string $class_name): void
    {

        /** @var Model $model */
        $model = new $class_name;

        $model->changeState(State::SYNC);

        $this->comment("<b>$class_name</b>", Color::ORANGE, break_before: true, break_after: true);

        # Criar a tabela principal

        $this->executeSync($model);

        # Criar as tabelas MN

        foreach ($model->getManyToMany() as $key => $mn) {

            if ($mn->inverted) {
                continue;
            }

            $this->comment("<b>Tabela MN para o campo '$key'</b>", break_after: true);

            # Criar a tabela de relacionamento

            $this->executeSync($mn->getManyToManyModel());

        }

        foreach ($model->getManyChoices() as $key => $mn) {

            if ($mn->inverted) {
                continue;
            }

            $this->comment("<b>Tabela MN para o campo '$key'</b>", break_after: true);

            # Criar a tabela de relacionamento

            $this->executeSync($mn->getManyChoicesModel());

        }

    }

    protected function executeSync(Model $model): void
    {

    }

    public function filter(QueryWhere $filter, Query $query, ?string $id): string
    {
        return '';
    }

    /**
     * @throws Exception
     */
    public function select(Query $query): ?Generator
    {

        $this::$table_id = 1;

        $model = $query->getModel();

        $class_name = $model->getComment();

        $model->setSaved(true);

        $fields = $model->getFields();

        $sql = $this->sql_select($query) . ";\n";

        $this->comment("Executando consulta em '$class_name'", Color::PINK, break_before: true);

        $this->logSql("$sql");

        foreach ($this->fetch($sql) as $result) {

            if (isset($result['total'])) {
                $model->getField('total')->setValue($result['total'], true);
            }

            foreach ($fields as $field) {

                if ($field->fake || $field->column_name == 'total') {
                    continue;
                }

                $field->setValue($result[$field->column_name] ?? null, true);

            }

            yield $model;

        }

    }

    public function sql_select(Query $query, bool $inline = false): string
    {
        return '';
    }

    public function sql_insert(Model $model, array $data = []): string
    {
        return '';
    }

    /**
     * @throws SqlException
     */
    public function prepare(string $sql): false|PDOStatement
    {

        try {
            return $this->getPDO()->prepare($sql);
        }

        catch (PDOException $e) {
            throw new SqlException($e->getMessage(), $sql);
        }

    }

    /**
     * @throws SqlException
     */
    public function query(string $sql): false|PDOStatement
    {

        try {
            return $this->getPDO()->query($sql);
        }

        catch (PDOException $e) {
            throw new SqlException($e->getMessage(), $sql);
        }

    }

    /**
     * @throws SqlException
     */
    public function exec(string $sql): false|int
    {

        try {
            return $this->getPDO()->exec($sql);
        }

        catch (PDOException $e) {
            throw new SqlException($e->getMessage(), $sql);
        }

    }

    /**
     * @throws SqlException|Exception
     */
    public function execute_insert(Model $model, array $data = []): void
    {

        $class_name = $model->getComment();

        $sql = $this->sql_insert($model, $data);

        $this->comment("Inserindo registro(s) em '$class_name'", Color::GREEN, true);

        $this->logSql($sql);

        $stmt = $this->query($sql);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        foreach ($model->getFields() as $field) {
            if ($field->isPrimaryKey() || $field->hasDefaultValue()) {
                $field->setValue($result[$field->column_name]);
            }
        }

        $this->rowCountLog($stmt->rowCount());

    }

    public function sql_update(Model $model, Query $query): ?string
    {
        return '';
    }

    /**
     * @throws Exception
     */
    public function execute_update(Model $model): void
    {

        $query = $model::query();
        $class_name = get_class($model);

        $primary_keys = $model->getPrimaryKeys();

        if (count($primary_keys) == 0) {
            throw new Exception("Model '$class_name' não possui chave primária definida");
        }

        foreach ($primary_keys as $key => $pk) {
            $query = $query->filter($key, $pk->getSavedValue());
        }

        $sql = $this->sql_update($model, $query);

        $this->comment("Atualizando registro(s) em '$class_name'", Color::ORANGE);

        if (!is_null($sql)) {

            $count = $this->execute($sql);

            $this->rowCountLog($count);

        }

        else {

            $this->comment("Nenhum campo alterado...");

        }

    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function save(Model $model): bool
    {

        $class_name = get_class($model);

        # Inserir dados na tabela principal

        if (!$model->isSaved()) {

            $primary_keys = $model->getPrimaryKeys();

            if (count($primary_keys) == 0) {
                throw new Exception("Model '$class_name' já salvo e não possui chave primária definida");
            }

            $this->execute_insert($model);

        }

        # Atualizar dados na tabela principal

        else {

            $this->execute_update($model);

        }

        # Atualizar dados nas tabelas MN

        foreach ($model->getManyToMany() as $mn) {

            $field_id = $model->getFieldId();

            if ($mn->isChanged()) {

                $mn_model = $mn->getManyToManyModel();
                $id = $field_id->getValue();
                $left = $mn_model->getLeft();
                $right = $mn_model->getRight();

                # Apagando registros antigos

                $query = new Query($mn_model);

                $query->filter($left, $id)->delete();

                # Inserindo registros novos

                $data = [];

                foreach ($mn->getValue() as $item) {
                    $data[] = [$left => $id, $right => $item];
                }

                $this->comment("Inserindo registro(s) em '{$mn_model->getComment()}'", Color::GREEN, true);
                $sql = $this->sql_insert($mn_model, $data);

                $count = $this->execute($sql);

                $this->rowCountLog($count);

            }

        }

        return true;

    }

    /**
     * @throws Exception
     */
    public function delete(Query $query): bool
    {

        $model = $query->getModel();

        $class_name = $model->getComment();

        $sql = $this->sql_delete($query);

        $this->comment("Apagando registro(s) em '$class_name'", Color::RED, true);

        $count = $this->execute($sql);

        $this->rowCountLog($count);

        return true;

    }

    /**
     * @throws Exception
     */
    public function sql_delete(Query $query): string
    {

        $model = $query->getModel();

        $table = $model->getTable();

        $where = [];

        foreach ($query->getWhere() as $w) {
            $where[] = $this->filter($w, $query, null);
        }

        if (count($where) == 0) {
            throw new Exception("Nenhum filtro para exclusão");
        }

        $sql = "DELETE FROM $table->database.$table->schema.$table->table"
            . "\nWHERE\t" . implode(" AND\n\t", $where);

        return "$sql;";

    }

    /**
     * @noinspection PhpUnused
     */
    public function truncate(Query $query): bool
    {

        $model = $query->getModel();

        $class_name = $model->getComment();

        $sql = $this->sql_truncate($query);

        $this->comment("Truncando dados em '$class_name'", Color::RED, true);

        $this->execute($sql);

        return true;

    }

    public function sql_truncate(Query $query): string
    {

        $table = $query->getModel()->getTable();

        return "TRUNCATE TABLE $table->database.$table->schema.$table->table;";

    }

    public function drop(Query $query): bool
    {

        $model = $query->getModel();

        $class_name = $model->getComment();

        $sql = $this->sql_drop($query);

        $this->comment("Apagando tabela '$class_name'", Color::RED, true);

        $this->execute($sql);

        return true;

    }

    public function sql_drop(Query $query): string
    {

        $table = $query->getModel()->getTable();

        return "DROP TABLE $table->database.$table->schema.$table->table;";

    }

}
