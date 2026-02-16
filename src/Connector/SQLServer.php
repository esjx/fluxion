<?php
namespace Fluxion\Connector;

use PDO;
use ReflectionException;
use Random\RandomException;
use Fluxion\Exception\{SqlException};
use Fluxion\{Color, Connector, Database, Exception, Model, Query, Time};
use Fluxion\Query\{QueryWhere};

class SQLServer extends Connector
{

    protected string $true_value = '1';
    protected string $false_value = '0';
    protected string $null_value = 'NULL';
    protected string $default_value = 'DEFAULT';
    protected string $utf_prefix = 'N';

    protected array $pdo_options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 15,
        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
    ];

    /**
     * @throws SqlException
     */
    protected function updateStructure(): void
    {

        if (is_array($this->_structure)) return;

        $this->_structure = [];

        # Buscando bancos de dados

        $sql = "SELECT name
                FROM sys.databases
                WHERE name NOT IN ('master', 'tempdb', 'model', 'msdb')
                ORDER BY name";

        foreach ($this->fetch($sql) as $result) {

            $this->_structure[$result['name']] = [];

        }

        # Buscando esquemas

        $sql = "SELECT name, schema_id
                FROM sys.schemas
                WHERE name = 'dbo' OR (name NOT IN ('guest', 'INFORMATION_SCHEMA', 'sys')
                    AND name NOT LIKE 'db_%')
                ORDER BY name;";

        foreach ($this->_structure as $database => $value) {

            $this->exec("USE $database;");

            foreach ($this->fetch($sql) as $result) {

                $this->_structure[$database][$result['name']] = [
                    'id' => $result['schema_id'],
                    'tables' => [],
                ];

            }

        }

    }

    /**
     * @throws SqlException
     */
    protected function updateDatabase(Database\Table $table): void
    {

        $this->updateStructure();

        if (!isset($this->_structure[$table->database])) {

            $this->comment("Criando banco de dados '$table->database'", Color::GREEN, true);
            $this->execute("CREATE DATABASE $table->database;");

            $this->_structure[$table->database] = [
                'dbo' => [
                    'id' => 1,
                    'tables' => [],
                ],
            ];

        }

        if ($this->_database != $table->database) {

            $this->comment("Alterando banco de dados para '$table->database'", Color::BROWN, true);
            $this->execute("USE $table->database;", true);

            $this->_database = $table->database;

        }

        if (!isset($this->_structure[$table->database][$table->schema])) {

            $this->comment("Criando esquema '$table->schema'", Color::GREEN, true);
            $this->execute("CREATE SCHEMA $table->schema;", true);

            $this->_structure[$table->database][$table->schema] = [];

        }

    }

    /**
     * @throws SqlException
     */
    public function getTableInfo(Database\Table $table): TableInfo
    {

        # Dados em branco

        $info = new TableInfo();

        # Alterando banco de dados atual

        $this->exec("USE $table->database;");

        # Buscando campos da tabela

        $sql = "SELECT c.name AS column_name,
                       c.column_id,
                       t.name AS type,
                       c.is_nullable,
                       c.is_identity,
                       CASE
                           WHEN t.name IN ('varchar') AND c.max_length >= 1 THEN c.max_length
                           WHEN t.name IN ('nvarchar') AND c.max_length >= 1 THEN c.max_length / 2
                       END AS max_length,
                       IIF(t.name IN ('numeric'), c.precision, NULL) AS precision,
                       IIF(t.name IN ('numeric'), c.scale, NULL) AS scale,
                       d.definition AS default_value,
                       d.name AS default_constraint
                FROM sys.columns c
                         LEFT JOIN sys.types t ON c.user_type_id = t.user_type_id
                         LEFT JOIN sys.default_constraints d ON c.default_object_id = d.object_id
                WHERE c.object_id = OBJECT_ID('$table->schema.$table->table')
                ORDER BY c.column_id;";

        foreach ($this->fetch($sql) as $result) {

            $info->exists = true;

            $column = new TableColumn();

            $column->name = $result['column_name'];
            $column->id = $result['column_id'];
            $column->type = $result['type'];
            $column->nullable = $result['is_nullable'];
            $column->required = !$result['is_nullable'];
            $column->identity = $result['is_identity'];
            $column->max_length = $result['max_length'];
            $column->precision = $result['precision'];
            $column->scale = $result['scale'];

            if ($column->identity) {
                $info->has_identity = true;
            }

            if (!is_null($result['default_value'])) {

                if (str_starts_with($result['default_value'], '((')) {
                    $result['default_value'] = substr($result['default_value'], 2, -2);
                }

                /*elseif (str_starts_with($result['default_value'], '(\'')) {
                    $result['default_value'] = substr($result['default_value'], 2, -2);
                }*/

                elseif (str_starts_with($result['default_value'], '(')) {
                    $result['default_value'] = substr($result['default_value'], 1, -1);
                }

            }

            $column->default_value = $result['default_value'];
            $column->default_constraint = $result['default_constraint'];

            $info->columns[$result['column_name']] = $column;

        }

        # Retornando dados se não existir tabela

        if (!$info->exists) {
            return $info;
        }

        # Buscando chaves primarias da tabela

        $sql = "SELECT kc.name        AS pk_name,
                       c.name         AS column_name,
                       ic.key_ordinal AS column_order
                FROM sys.key_constraints kc
                         INNER JOIN sys.index_columns ic
                                    ON kc.parent_object_id = ic.object_id
                                        AND kc.unique_index_id = ic.index_id
                         INNER JOIN sys.columns c
                                    ON ic.object_id = c.object_id
                                        AND ic.column_id = c.column_id
                WHERE kc.type = 'PK'
                  AND kc.parent_object_id = OBJECT_ID('$table->schema.$table->table')
                ORDER BY column_order;";

        foreach ($this->fetch($sql) as $result) {

            $info->primary_key_name = $result['pk_name'];
            $info->primary_keys[] = $result['column_name'];

        }

        # Buscando chaves estrangeiras da tabela

        $sql = "SELECT f.name                                                     AS fk_name,
                       COL_NAME(fc.parent_object_id, fc.parent_column_id)         AS parent_column,
                       SCHEMA_NAME(f.schema_id)                                   AS referenced_schema,
                       OBJECT_NAME(f.referenced_object_id)                        AS referenced_table,
                       COL_NAME(fc.referenced_object_id, fc.referenced_column_id) AS referenced_column,
                       f.delete_referential_action_desc                           AS delete_rule,
                       f.update_referential_action_desc                           AS update_rule
                FROM sys.foreign_keys AS f
                         INNER JOIN sys.foreign_key_columns AS fc
                                    ON f.object_id = fc.constraint_object_id
                WHERE f.type = 'F'
                  AND f.parent_object_id = OBJECT_ID('$table->schema.$table->table')
                ORDER BY fk_name;";

        foreach ($this->fetch($sql) as $result) {

            $foreign_key = new TableForeignKey();

            $foreign_key->name = $result['fk_name'];
            $foreign_key->parent_column = $result['parent_column'];
            $foreign_key->referenced_schema = $result['referenced_schema'];
            $foreign_key->referenced_table = $result['referenced_table'];
            $foreign_key->referenced_column = $result['referenced_column'];
            $foreign_key->delete_rule = $result['delete_rule'];
            $foreign_key->update_rule = $result['update_rule'];

            $info->foreign_keys[$result['fk_name']] = $foreign_key;

        }

        # Buscando índices da tabela

        $sql = "SELECT i.name,
                       i.type_desc,
                       i.is_unique,
                       COL_NAME(ic.object_id, ic.column_id) AS column_name,
                       ic.is_included_column,
                       ic.key_ordinal
                FROM sys.indexes AS i
                         INNER JOIN sys.index_columns AS ic
                                    ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                WHERE i.object_id = OBJECT_ID('$table->schema.$table->table')
                  AND i.is_primary_key = 0
                ORDER BY i.name, ic.key_ordinal;";

        foreach ($this->fetch($sql) as $result) {

            if (!isset($info->indexes[$result['name']])) {

                $index = new TableIndex();

                $index->name = $result['name'];
                $index->type = $result['type_desc'];
                $index->unique = $result['is_unique'];

                $info->indexes[$result['name']] = $index;

            }

            if ($result['is_included_column']) {
                $info->indexes[$result['name']]->includes[] = $result['column_name'];
            }

            else {
                $info->indexes[$result['name']]->columns[] = $result['column_name'];
            }

        }

        # Retornando dados

        return $info;

    }

    /** @throws Exception */
    protected function getDatabaseType(Database\Field $value): string
    {

        return match ($value->getType()) {
            'json', 'link', 'color' => 'varchar',
            'text', 'iframe', 'map', 'html', 'upload', 'image', 'string', 'password' => 'nvarchar',
            'integer' => 'bigint',
            'boolean' => 'bit',
            'date' => 'date',
            'datetime' => 'datetime',
            'float' => 'float',
            'decimal', 'numeric' => "numeric",
            'geography' => 'geography',
            default => throw new Exception("Tipo {$value->getType()} não implementado"),
        };

    }

    /** @throws Exception */
    protected function getColumnCommand(Database\Field $value): string
    {

        # Definição do tipo

        $type = $this->getDatabaseType($value);

        # Criação do comando

        $command = "$type";

        # Complementos

        if (in_array($type, ['varchar', 'nvarchar']) && !$value->max_length) {
            $command .= "(max)";
        }

        elseif (in_array($type, ['varchar', 'nvarchar'])) {
            $command .= "($value->max_length)";
        }

        elseif ($type == 'numeric') {
            $command .= "(18,$value->decimal_places)";
        }

        # Valores nulos

        if ($value->required || $value->identity) {
            $command .= ' not null';
        }

        # Auto incremento

        if ($value->identity) {
            $command .= ' identity(1,1)';
        }

        return $command;

    }

    protected function getDefaultCommand(Database\Field $value): ?string
    {

        # Valor padrão

        if (!is_null($value->default)) {

            $default = $value->default;

            if ($default != 'getdate()' && !$value->default_literal) {
                $default = $this->escape($value->default);
            }

            return "$default";

        }

        return null;

    }

    /**
     * @throws Exception
     * @throws RandomException
     * @throws ReflectionException
     */
    protected function executeSync(Model $model): void
    {

        $table = $model->getTable();
        $fields = $model->getFields();

        $this->updateDatabase($table);

        $prefix = strtolower("{$table->schema}_$table->table");

        $info = $this->getTableInfo($table);

        # Buscando as chaves necessárias

        $primary_keys = [];
        $foreign_keys = [];

        foreach ($fields as $key => $f) {

            if ($f->fake || $f->assistant_table) {
                continue;
            }

            # Chave primária

            if ($f->isPrimaryKey()) {
                $primary_keys[] = $f->column_name;
            }

            # Chave estrangeira

            if ($f->isForeignKey()) {
                $foreign_keys[$key] = $f;
            }

        }

        # Quando a tabela já existir

        if ($info->exists) {

            $this->comment("Tabela '$table->schema.$table->table' já existe");

            # Campos utilizados

            foreach ($fields as $key => $value) {

                if ($value->fake || $value->assistant_table) {
                    continue;
                }

                $default_value = $this->getDefaultCommand($value);

                # Campo ainda não existe

                if (!isset($info->columns[$key])) {

                    $this->comment("Incluindo campo '$key'", Color::GREEN, true);

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tADD [$value->column_name] {$this->getColumnCommand($value)}";

                    if (!is_null($default_value)) {
                        $sql .= " default $default_value";
                    }

                    #TODO: Validar inclusão de campo não nulo sem valor padrão quando já existir registros na tabela

                    $sql .= ";";

                    $info->columns[$key] = new TableColumn();
                    $info->columns[$key]->type = $this->getDatabaseType($value);
                    $info->columns[$key]->required = $value->required;
                    $info->columns[$key]->extra = false;
                    $info->columns[$key]->default_value = $default_value;

                    $this->execute($sql, true);

                }

                # Campo existe e precisa ser alterado

                elseif ($info->columns[$key]->type != $this->getDatabaseType($value)
                    || $info->columns[$key]->max_length != $value->max_length
                    || $info->columns[$key]->required != $value->required) {

                    $info->columns[$key]->extra = false;

                    $this->comment("Alterando campo '$key: {$info->columns[$key]}'", Color::BROWN, true);

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tALTER COLUMN [$value->column_name] {$this->getColumnCommand($value)};";

                    $this->execute($sql, true);

                }

                # Campo já existe
                else {

                    $info->columns[$key]->extra = false;

                    $this->comment("Campo '$key: {$info->columns[$key]}' já existe");

                }

                # Valor padrão do campo

                if (isset($info->columns[$key])
                    && !is_null($info->columns[$key]->default_value)
                    && $info->columns[$key]->default_value != $default_value) {

                    $this->comment("Excluindo valor padrão '{$info->columns[$key]->default_value}' no campo '$key'", Color::RED, true);

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tDROP CONSTRAINT {$info->columns[$key]->default_constraint};";

                    $this->execute($sql, true);

                }

                if (!is_null($value->default)
                    && $info->columns[$key]->default_value != $default_value) {

                    $this->comment("Incluindo valor padrão '$default_value' no campo '$key'", Color::BROWN, true);

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tADD DEFAULT $default_value FOR [$value->column_name];";

                    $this->execute($sql, true);

                }

            }

            # Campos não utilizados

            foreach ($info->columns as $key => $value) {

                if (!$value->extra) {
                    continue;
                }

                $this->comment("Campo '$key: $value' não utilizado", Color::DEEP_ORANGE);

            }

            # Chaves primárias

            if (count($primary_keys) > 0) {

                # Verificar se existe chave com os mesmos campos

                if ($info->primary_keys == $primary_keys) {

                    $detail = implode(", ", $primary_keys);

                    $this->comment("Chave primária '$info->primary_key_name ($detail)' já existe");

                }

                else {

                    if (!is_null($info->primary_key_name)) {

                        $this->comment("Apagando chave primária '$info->primary_key_name'", Color::RED, true);

                        $sql = "ALTER TABLE $table->schema.$table->table\n"
                            . "\tDROP CONSTRAINT $info->primary_key_name;";

                        $this->execute($sql, true);

                    }

                    $uid = bin2hex(random_bytes(10));

                    $primary_key_name = "{$prefix}_pk_$uid";
                    $primary_key = implode("], [", $primary_keys);

                    $this->comment("Criando chave primária '$primary_key_name'", Color::GREEN, true);

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tADD CONSTRAINT $primary_key_name PRIMARY KEY ([$primary_key]);";

                    $this->execute($sql, true);

                }

            }
            
            # Chave primária não utilizada

            else if (!is_null($info->primary_key_name)) {

                $this->comment("Chave primária '$info->primary_key_name' não utilizada", Color::DEEP_ORANGE);

            }

            # Chaves estrangeiras

            if (count($foreign_keys) > 0) {

                /** @var Database\Field\ForeignKeyField $foreign_key */
                foreach ($foreign_keys as $key => $foreign_key) {

                    $uid = bin2hex(random_bytes(10));

                    $foreign_key_name = "{$prefix}_fk_$uid";

                    if (!$foreign_key->real) {
                        continue;
                    }

                    $reference = $foreign_key->getReferenceModel()->getTable();

                    $field = $fields[$key];

                    $foreign_key_exists = false;

                    $reference_field = $foreign_key->getField();

                    $foreign_key_type = ($field->required) ? 'NO_ACTION' : 'SET_NULL';

                    if (!is_null($foreign_key->type)) {
                        $foreign_key_type = $foreign_key->type;
                    }

                    foreach ($info->foreign_keys as $fk_key => $fk_value) {

                        if ($fk_value->parent_column != $field->column_name) {
                            continue;
                        }

                        $info->foreign_keys[$fk_key]->extra = false;

                        if ($fk_value->referenced_schema != $reference->schema
                            || $fk_value->referenced_table != $reference->table
                            || $fk_value->referenced_column != $reference_field->column_name
                            || $fk_value->delete_rule != $foreign_key_type
                            || $fk_value->update_rule != $foreign_key_type) {

                            $detail = "($key) → $fk_value->referenced_table ($fk_value->referenced_column)";

                            $this->comment("Apagando chave estrangeira '$fk_key' $detail", Color::RED, true);

                            $sql = "ALTER TABLE $table->schema.$table->table\n"
                                . "\tDROP CONSTRAINT $fk_key;";

                            $this->execute($sql, true);

                        }

                        else {

                            $foreign_key_exists = true;

                            $detail = "($key) → $reference->table ($reference_field->column_name)";

                            $this->comment("Chave estrangeira '$fk_key $detail' já existe");

                        }

                    }

                    if (!$foreign_key_exists) {

                        $this->comment("Criando chave estrangeira '$foreign_key_name'", Color::GREEN, true);

                        $foreign_key_type = str_replace('_', ' ', $foreign_key_type);

                        $sql = "ALTER TABLE $table->schema.$table->table\n"
                            . "\tADD CONSTRAINT $foreign_key_name "
                            . "FOREIGN KEY ([$field->column_name]) "
                            . "REFERENCES $reference->schema.$reference->table ([$reference_field->column_name]) "
                            . "ON UPDATE $foreign_key_type ON DELETE $foreign_key_type";

                        $this->execute($sql, true);

                    }

                }

            }
            
            # Chaves estrangeiras não utilizadas

            foreach ($info->foreign_keys as $key => $foreign_key) {

                if (!$foreign_key->extra) {
                    continue;
                }

                $this->comment("Chave estrangeira '$key' não utilizada", Color::DEEP_ORANGE);

            }

            # Dados iniciais

            $data = $model->getData();

            if (count($data) > 0) {

                $this->comment("Atualizando dados iniciais na tabela '$table->schema.$table->table'", Color::GREEN, true, true);

                $primary_keys = $model->getPrimaryKeys();

                foreach ($data as $row) {

                    $id = [];
                    foreach ($primary_keys as $key => $pk) {
                        $id[$key] = $row[$key] ?? null;
                    }

                    $test = $model::loadById($id);

                    foreach ($row as $key => $value) {
                        $test->$key = $value;
                    }

                    $test->save();

                }

            }

        }

        # Criar a tabela se não existir

        else {

            $create_fields = [];

            # Campos utilizados

            foreach ($fields as $value) {

                if ($value->fake || $value->assistant_table) {
                    continue;
                }

                $command = "\t[$value->column_name] {$this->getColumnCommand($value)}";

                $default_value = $this->getDefaultCommand($value);

                if (!is_null($default_value)) {
                    $command .= " default $default_value";
                }

                $create_fields[] = $command;

            }

            # Campos da tabela

            $command = implode(",\n", $create_fields);

            # Chaves primárias

            if (count($primary_keys) > 0) {

                $primary_key = implode("], [", $primary_keys);

                $uid = bin2hex(random_bytes(10));

                $command .= ",\n\tCONSTRAINT {$prefix}_pk_$uid PRIMARY KEY ([$primary_key])";

            }

            # Chaves estrangeiras

            if (count($foreign_keys) > 0) {

                /** @var Database\Field\ForeignKeyField $foreign_key */
                foreach ($foreign_keys as $key => $foreign_key) {

                    if (!$foreign_key->real) {
                        continue;
                    }

                    $reference = $foreign_key->getReferenceModel()->getTable();

                    if ($reference->database != $table->database) {
                        $this->comment("<b>ERRO</b>: Não é possível a inclusão de chave estrangeira de outro banco de dados (campo '$key').", Color::RED);
                    }

                    else {

                        $field = $fields[$key];

                        $reference_field = $foreign_key->getField();

                        $foreign_key_type = ($field->required) ? 'NO_ACTION' : 'SET_NULL';

                        if (!is_null($foreign_key->type)) {
                            $foreign_key_type = $foreign_key->type;
                        }

                        $foreign_key_type = str_replace('_', ' ', $foreign_key_type);

                        $uid = bin2hex(random_bytes(10));

                        $command .= ",\n\tCONSTRAINT {$prefix}_fk_$uid "
                            . "FOREIGN KEY ([$field->column_name]) "
                            . "REFERENCES $reference->schema.$reference->table ([$reference_field->column_name]) "
                            . "ON UPDATE $foreign_key_type ON DELETE $foreign_key_type";

                    }

                }

            }

            # Comandos

            $sql = "CREATE TABLE $table->schema.$table->table (\n$command\n);";

            $this->comment("Criando tabela '$table->schema.$table->table'", Color::GREEN, true);
            $this->execute($sql, true);

            $comment = $model->getComment();

            $this->comment("Adicionando descrição na tabela '$table->schema.$table->table'", Color::GREEN, true);
            $sql = "EXEC sys.sp_addextendedproperty 'MS_Description', '$comment (" . Time::NOW->value() . ")', 'SCHEMA', '$table->schema', 'TABLE', '$table->table';";

            $this->execute($sql, true);

            # Dados iniciais

            $data = $model->getData();

            if (count($data) > 0) {

                $this->comment("Incluindo dados iniciais na tabela '$table->schema.$table->table'", Color::GREEN, true, true);

                $sql = $this->sql_insert($model, $data);

                $count = $this->execute($sql);

                $this->rowCountLog($count);

            }

        }

        # Índices

        foreach ($model->getIndexes() as $create_index) {

            $index_exists = false;

            foreach ($info->indexes as $key => $index) {

                $a = $index->columns;
                $b = $create_index->columns;

                sort($a);
                sort($b);

                if ($index->unique == $create_index->unique
                    && $a == $b
                    && $index->includes == $create_index->includes) {

                    $index_exists = true;

                    $info->indexes[$key]->extra = false;

                    if ($index->columns == $create_index->columns) {
                        $this->comment("Índice '$key $index' já existe");
                    }

                    else {
                        $this->comment("Índice '$key $index' já existe com outra ordem", Color::PURPLE);
                    }

                }

            }

            if (!$index_exists) {

                $index_name = $prefix . '_index_' . bin2hex(random_bytes(10));

                $this->comment("Criando índice '$index_name'", Color::GREEN, true);

                if ($create_index->unique) {
                    $sql = "CREATE UNIQUE INDEX $index_name ON $table->schema.$table->table";
                }

                else {
                    $sql = "CREATE INDEX $index_name ON $table->schema.$table->table";
                }

                $sql .= ' ("' . implode('", "', $create_index->columns) . '")';

                if (count($create_index->includes) > 0) {
                    $sql .= ' INCLUDE ("' . implode('", "', $create_index->includes) . '")';
                }

                $sql .= ';';

                $this->execute($sql, true);

            }

        }

        # Índices não utilizados

        foreach ($info->indexes as $key => $index) {

            if (!$index->extra) {
                continue;
            }

            $this->comment("Índice '$key $index' não utilizado", Color::DEEP_ORANGE);

        }

        if (!is_null($this->log_stream)) {
            $this->log_stream->write("\n");
        }

    }

    /**
     * @throws Exception
     */
    public function filter(QueryWhere $filter, Query $query, ?string $id): string
    {

        $model = $query->getModel();

        $txt_id = (!is_null($id)) ? "$id." : '';

        $not = ($filter->not) ? ' NOT ' : '';

        if (is_object($filter->field)) {

            $where = [];
            /** @var QueryWhere $w */
            foreach ($filter->field->_filters as $w) {
                if (!is_null($w)) {
                    $where[] = $this->filter($w, $query, $id);
                }
            }

            $where = implode(" {$filter->field->_type} ", $where);

            return "$not($where)";

        }

        $_field = explode('__', $filter->field);

        $field_obj = $model->getField($_field[0]);

        $field = $field_obj->column_name;

        $type = '=';

        for ($i = 1; $i <= count($_field); $i++) {

            if (isset($_field[$i])) {

                if ($_field[$i] == 'json') {

                    $json_name = $_field[$i + 1]
                        ?? throw new Exception("Utilizar padrão 'campo__json__variavel'");

                    $field = "JSON_VALUE({$txt_id}[$field], '$.$json_name')";

                    $filter->value = (string) $filter->value;

                    $i++;

                    continue;

                }

                $type = match ($_field[$i]) {
                    'ne' => '<>',
                    'lt' => '<',
                    'gt' => '>',
                    'lte' => '<=',
                    'gte' => '>=',
                    'like' => 'LIKE',
                    default => '=',
                };

                $field = match ($_field[$i]) {
                    'second', 'minute', 'hour', 'day', 'week', 'month', 'year',
                    'weekday' => "DATEPART($_field[$i], {$txt_id}[$field])",
                    'dow' => "DATEPART(weekday, {$txt_id}[$field])",
                    'date' => "CAST({$txt_id}[$field] AS DATE)",
                    'length' => "LEN({$txt_id}[$field])",
                    'only_number', 'clean' => "REPLACE(REPLACE(REPLACE(REPLACE({$txt_id}[$field], ' ', ''), '.', ''), '/', ''), '-', '')",
                    default => $field,
                };

            }

        }

        if ($field == $field_obj->column_name) {
            $field = "{$txt_id}[$field]";
        }

        if (is_null($filter->value)) {
            return "$field IS {$not}NULL";
        }

        if (is_string($filter->value)
            || is_numeric($filter->value)
            || is_bool($filter->value)) {

            $escaped_value = $filter->value;

            if ($type == 'LIKE') {
                $escaped_value = str_replace(['_', '%'], '', $escaped_value);
            }

            if (in_array($field_obj->getType(), ['integer', 'float', 'decimal'])
                && $escaped_value
                && !is_numeric($escaped_value)
                && !is_bool($escaped_value)) {
                throw new Exception("O valor <b>$filter->value</b> não é numérico!");
            }

            if ($type == 'LIKE') {
                return "$field {$not}LIKE {$this->escape($filter->value)}";
            }

            return "$not$field $type " . $this->escape($filter->value);

        }

        if (is_array($filter->value)) {

            if (count($filter->value) > 0) {

                if (in_array(null, $filter->value)) {
                    return "($field$not IN " . $this->escape($filter->value) . " OR $field IS {$not}NULL)";
                }

                return "$field$not IN " . $this->escape($filter->value);

            }

            else {
                return '1=0';
            }

        }

        if (is_object($filter->value)) {

            if (get_class($filter->value) == Query::class) {
                return "$field$not IN (" . self::sql_select($filter->value, true) . ")";
            }

        }

        return false;

    }

    /**
     * @throws Exception
     */
    public function sql_select(Query $query, bool $inline = false): string
    {

        $id = $this->getTableId();
        $model = $query->getModel();
        $table = $model->getTable();

        $fields = [];

        if ($query->isAllFields()) {
            foreach ($model->getFields() as $field) {
                if (!$field->fake && !$field->assistant_table) {
                    $fields[] = "$id.[$field->column_name]";
                }
            }
        }

        foreach ($query->getFields() as $query_field) {

            $field = $model->getField($query_field->field);

            if (is_null($query_field->aggregator)) {
                $fields[] = "$id.[$field->column_name]";
            }

            elseif ($query_field->aggregator == 'COUNT') {

                if (is_null($field)) {
                    $fields[] = "COUNT(*) AS $query_field->name";
                }

                else {
                    $fields[] = "COUNT($id.[$field->column_name]) AS $query_field->name";
                }

            }

            else {
                $fields[] = "$query_field->aggregator($id.[$field->column_name]) AS $query_field->name";
            }

        }

        $where = [];

        foreach ($query->getWhere() as $w) {
            $where[] = $this->filter($w, $query, $id);
        }

        $order_by = [];

        foreach ($query->getOrderBy() as $o) {
            $field = $model->getField($o->field);
            $order_by[] = "$id.[$field->column_name] $o->order";
        }

        $group_by = [];

        foreach ($query->getGroupBy() as $g) {
            $field = $model->getField($g->field);
            $group_by[] = "$id.[$field->column_name]";
        }

        $top = "\t";
        $limit_offset = null;

        if (!is_null($l = $query->getLimit())) {

            if ($l->offset == 0) {
                $top = " TOP $l->limit ";
            }

            if ($l->offset > 0) {

                $limit_offset = "OFFSET $l->offset ROWS FETCH NEXT $l->limit ROWS ONLY";

                if (count($order_by) == 0) {
                    foreach ($model->getPrimaryKeys() as $pk) {
                        $order_by[] = "$id.[$pk->column_name] ASC";
                        break;
                    }
                }

                if (count($order_by) == 0) {
                    foreach ($model->getFields() as $f) {
                        $order_by[] = "$id.[$f->column_name] ASC";
                        break;
                    }
                }

            }

        }

        $sql = "SELECT$top" . implode(",\n\t", $fields);

        $sql .= "\nFROM $table->database.$table->schema.$table->table AS $id WITH (NOLOCK)";

        if (count($where) > 0) {
            $sql .= "\nWHERE\t" . implode(" AND\n\t", $where);
        }

        if (count($order_by) > 0) {
            $sql .= "\nORDER BY " . implode(",\n\t", $order_by);
        }

        if (count($group_by) > 0) {
            $sql .= "\nGROUP BY " . implode(",\n\t", $group_by);
        }

        if (!is_null($limit_offset)) {
            $sql .= "\n$limit_offset";
        }

        if ($inline) {
            $sql = str_replace(["\n\t", "\n", "\t"], " ", $sql);
        }

        return $sql;

    }

    /**
     * @throws Exception
     */
    public function sql_insert(Model $model, array $data = []): string
    {

        $fields = $model->getFields();
        $primary_keys = $model->getPrimaryKeys();
        $table = $model->getTable();

        $fields_sql = [];

        $inserts_sql = [];
        $outputs_sql = [];

        $identity_insert = false;

        # Inclusão de um único registro

        if (count($data) == 0) {

            # Valores a serem devolvidos

            foreach ($primary_keys as $p) {
                $outputs_sql[] = "INSERTED.[$p->column_name]";
            }

            $values_sql = [];

            foreach ($fields as $key => $f) {

                if ($f->fake || $f->assistant_table) {
                    continue;
                }

                if (!$f->isChanged() && $model->isSaved()) {
                    continue;
                }

                $value = $f->getValue(true);

                if (!is_null($f->default)) {
                    $outputs_sql[] = "INSERTED.[$f->column_name]";
                }

                if ($f->identity && is_null($f->getValue())) {
                    continue;
                }

                if ($f->required && is_null($value) && is_null($f->default)) {
                    throw new Exception("Campo '$key' não pode ser nulo <pre>" . json_encode($f, JSON_PRETTY_PRINT) . '</pre>');
                }

                if ($f->identity) {
                    $identity_insert = true;
                }

                if ($f->required && is_null($value) && !is_null($f->default)) {
                    $values_sql[] = $this->default_value;
                }

                else {
                    $values_sql[] = $this->escape($value);
                }

            }

            $inserts_sql[] = "(" . implode(", ", $values_sql) . ")";

        }

        # Inclusão de vários registros

        else {

            foreach ($data as $d) {

                $values_sql = [];

                $i_model = clone $model;

                foreach ($fields as $key => $f) {

                    if (!array_key_exists($key, $d)) {
                        $f->clear();
                        continue;
                    }

                    $value = $d[$key] ?? null;

                    if ($f->required && is_null($value) && is_null($f->default)) {
                        throw new Exception("Campo '$key' não pode ser nulo");
                    }

                    if ($f->identity && !is_null($f->getValue())) {
                        $identity_insert = true;
                    }

                    $i_model->$key = $value;

                }

                if ($i_model->onSave()) {

                    foreach ($fields as $key => $f) {

                        if ($f->fake || $f->assistant_table) {
                            continue;
                        }

                        if ($f->required && is_null($i_model->$key) && !is_null($f->default)) {
                            $values_sql[] = $this->default_value;
                        }

                        else {
                            $values_sql[] = $this->escape($i_model->$key);
                        }

                    }

                    $inserts_sql[] = "(" . implode(", ", $values_sql) . ")";

                }

                else {
                    $this->comment('Erro ao salvar o registro: ' . json_encode($d), Color::RED);
                }

            }

        }

        # Campos

        foreach ($fields as $f) {

            if ($f->fake || $f->assistant_table) {
                continue;
            }

            if (!$f->isChanged() && $model->isSaved()) {
                continue;
            }

            if ($f->identity && !$identity_insert) {
                continue;
            }

            $fields_sql[] = "[$f->column_name]";

        }

        if (count($fields_sql) == 0) {
            throw new Exception("Nenhum campo alterado para incluir");
        }

        if (count($inserts_sql) == 0) {
            throw new Exception("Nenhum registro para incluir");
        }

        $sql = "INSERT INTO $table->database.$table->schema.$table->table";

        $sql .= " (\n\t" . implode(",\n\t", $fields_sql) . "\n)";

        if (count($outputs_sql) > 0) {
            $sql .= "\nOUTPUT\t" . implode(",\n\t", $outputs_sql);
        }

        $sql .= "\nVALUES\t" . implode(",\n\t", $inserts_sql) . ';';

        if ($identity_insert) {

            $sql = "SET IDENTITY_INSERT $table->database.$table->schema.$table->table ON;\n"
                . $sql
                . "\nSET IDENTITY_INSERT $table->database.$table->schema.$table->table OFF;";

        }

        return $sql;

    }

    /**
     * @throws Exception
     */
    public function sql_update(Model $model, Query $query): ?string
    {

        $table = $model->getTable();

        $where = [];

        foreach ($query->getWhere() as $w) {
            $where[] = $this->filter($w, $query, null);
        }

        $update = [];

        foreach ($model->getFields() as $f) {

            if ($f->fake || $f->assistant_table) {
                continue;
            }

            $f->update();

            if ($f->isChanged()) {
                $update[] = "[$f->column_name] = " . $this->escape($f->getValue());
            }

        }

        if (count($update) == 0) {
            return null;
        }

        $sql = "UPDATE $table->database.$table->schema.$table->table";

        $sql .= "\nSET\t" . implode(",\n\t", $update);

        if (count($where) > 0) {
            $sql .= "\nWHERE\t" . implode(" AND\n\t", $where);
        }

        return "$sql;";

    }

}
