<?php
namespace Fluxion\Connector;

use PDO;
use PDOException;
use Generator;
use Fluxion\Model2;
use Fluxion\SqlFormatter;
use Fluxion\Database;
use Fluxion\CustomException;
use Fluxion\Color;
use Random\RandomException;

class SQLServer2 extends SQLServer
{

    const DB_DATE_FORMAT = 'Y-m-d';
    const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';

    private bool $_extra_break = false;

    protected $true_value = '1';
    protected $false_value = '0';
    protected $null_value = 'NULL';
    protected $utf_prefix = '';

    protected PDO $_pdo;

    protected $_connected = false;

    protected ?array $_structure = null;
    protected ?string $_database = null;

    /** @throws PDOException */
    public function getPDO(): PDO
    {

        if (!$this->_connected) {

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 15,
                PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
                //PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_SYSTEM,
                //PDO::ATTR_PERSISTENT => true,
            ];

            $this->_pdo = new PDO($this->_host, $this->_user, $this->_pass, $options);

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

            $this->getPDO()->exec("USE $database;");

            foreach ($this->fetch($sql) as $result) {

                $this->_structure[$database][$result['name']] = [
                    'id' => $result['schema_id'],
                    'tables' => [],
                ];

            }

        }

    }

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
            $this->execute("USE $table->database;");

            $this->_database = $table->database;

        }

        if (!isset($this->_structure[$table->database][$table->schema])) {

            $this->comment("Criando esquema '$table->schema'", Color::GREEN, true);
            $this->execute("CREATE SCHEMA $table->schema;");

            $this->_structure[$table->database][$table->schema] = [];

        }

    }

    public function getTableInfo(Database\Table $table): TableInfo
    {

        # Dados em branco

        $info = new TableInfo();

        # Alterando banco de dados atual

        $this->getPDO()->exec("USE $table->database;");

        # Buscando campos da tabela

        $sql = "SELECT c.name AS column_name,
                       c.column_id,
                       t.name AS type,
                       c.is_nullable,
                       c.is_identity,
                       IIF(t.name IN ('varchar', 'nvarchar') AND c.max_length >= 1, c.max_length / 2, NULL) AS max_length,
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

    /** @throws CustomException */
    protected function getDatabaseType(Database\Field $value): string
    {

        return match ($value->getType()) {
            'text', 'iframe', 'map', 'html', 'upload', 'image' => 'varchar',
            'string', 'password', 'link', 'color' => 'nvarchar',
            'integer' => 'bigint',
            'boolean' => 'bit',
            'date' => 'date',
            'datetime' => 'datetime',
            'float' => 'float',
            'decimal', 'numeric' => "numeric",
            'geography' => 'geography',
            default => throw new CustomException("Tipo {$value->getType()} não implementado"),
        };

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

    /** @throws CustomException */
    protected function getColumnCommand(Database\Field $value): string
    {

        # Definição do tipo

        $type = $this->getDatabaseType($value);

        # Criação do comando

        $command = "$type";

        # Complementos

        if ($type == 'varchar' && $value->max_length) {
            $command .= "($value->max_length)";
        }

        elseif ($type == 'varchar' && !$value->max_length) {
            $command .= "(max)";
        }

        elseif ($type == 'nvarchar') {
            $command .= "($value->max_length)";
        }

        elseif ($type == 'numeric') {
            $command .= "(20,$value->decimal_places)";
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

    /**
     * @throws CustomException
     * @throws RandomException
     */
    public function synchronize(Model2 $model): void
    {

        $this->_extra_break = false;

        $table = $model->getTable();
        $fields = $model->getFields();

        $this->updateDatabase($table);

        $prefix = strtolower("{$table->schema}_$table->table");

        $info = $this->getTableInfo($table);

        # Buscando as chaves necessárias

        $primary_keys = [];
        $foreign_keys = [];

        foreach ($fields as $key => $value) {

            if ($value->fake) {
                continue;
            }

            # Chave primária

            if ($value->primary_key) {
                $primary_keys[] = $value->column_name;
            }

            # Chave estrangeira

            if (isset($value->foreign_key)) {
                $foreign_keys[$key] = $value->foreign_key;
            }

        }

        # Quando a tabela já existir

        if ($info->exists) {

            $this->comment("Tabela '$table->schema.$table->table' já existe");

            foreach ($fields as $key => $value) {

                if ($value->fake) {
                    continue;
                }

                # Campo ainda não existe

                if (!isset($info->columns[$key])) {

                    $this->comment("Incluindo campo '$key'", Color::GREEN, true);

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tADD [$value->column_name] {$this->getColumnCommand($value)};";

                    $this->execute($sql);

                }

                # Campo existe e precisa ser alterado

                elseif ($info->columns[$key]->type != $this->getDatabaseType($value)
                    || $info->columns[$key]->max_length != $value->max_length
                    || $info->columns[$key]->required != $value->required) {

                    $info->columns[$key]->extra = false;

                    $this->comment("Alterando campo '$key'", Color::BROWN, true);

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tALTER COLUMN [$value->column_name] {$this->getColumnCommand($value)};";

                    $this->execute($sql);

                }

                # Campo já existe
                else {

                    $info->columns[$key]->extra = false;

                    $this->comment("Campo '$key: {$info->columns[$key]}' já existe");

                }

                # Valor padrão do campo

                $default_value = $this->getDefaultCommand($value);

                if (!is_null($info->columns[$key]->default_value)
                    && $info->columns[$key]->default_value != $default_value) {

                    $this->comment("Excluindo valor padrão '{$info->columns[$key]->default_value}' no campo '$key'", Color::RED, true);

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tDROP CONSTRAINT {$info->columns[$key]->default_constraint};";

                    $this->execute($sql);

                }

                if (!is_null($value->default)
                    && $info->columns[$key]->default_value != $default_value) {

                    $this->comment("Incluindo valor padrão '$default_value' no campo '$key'", Color::BROWN, true);

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tADD DEFAULT $default_value FOR [$value->column_name];";

                    $this->execute($sql);

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

                    $this->comment("Chave primária '$info->primary_key_name' já existe");

                }

                else {

                    if (!is_null($info->primary_key_name)) {

                        $this->comment("Apagando chave primária '$info->primary_key_name'", Color::RED, true);

                        $sql = "ALTER TABLE $table->schema.$table->table\n"
                            . "\tDROP CONSTRAINT $info->primary_key_name;";

                        $this->execute($sql);

                    }

                    $uid = bin2hex(random_bytes(10));

                    $primary_key_name = "{$prefix}_pk_$uid";
                    $primary_key = implode("\", \"", $primary_keys);

                    $this->comment("Criando chave primária '$primary_key_name'", Color::GREEN, true);

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tADD CONSTRAINT $primary_key_name PRIMARY KEY (\"$primary_key\");";

                    $this->execute($sql);

                }

            }
            
            # Chave primária não utilizada

            else if (!is_null($info->primary_key_name)) {

                $this->comment("Chave primária '$info->primary_key_name' não utilizada", Color::DEEP_ORANGE);

            }

            # Chaves estrangeiras

            if (count($foreign_keys) > 0) {

                /** @var Database\ForeignKey $foreign_key */
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

                            $this->comment("Apagando chave estrangeira '$fk_key'", Color::RED, true);

                            $sql = "ALTER TABLE $table->schema.$table->table\n"
                                . "\tDROP CONSTRAINT $fk_key;";

                            $this->execute($sql);

                        }

                        else {

                            $foreign_key_exists = true;

                            $this->comment("Chave estrangeira '$fk_key' já existe");

                        }

                    }

                    if (!$foreign_key_exists) {

                        $this->comment("Criando chave estrangeira '$foreign_key_name'", Color::GREEN, true);

                        $foreign_key_type = str_replace('_', ' ', $foreign_key_type);

                        $sql = "ALTER TABLE $table->schema.$table->table\n"
                            . "\tADD CONSTRAINT $foreign_key_name "
                            . "FOREIGN KEY (\"$field->column_name\") "
                            . "REFERENCES $reference->schema.$reference->table ($reference_field->column_name) "
                            . "ON UPDATE $foreign_key_type ON DELETE $foreign_key_type";

                        $this->execute($sql);

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

        }

        # Criar a tabela se não existir

        else {

            $create_fields = [];

            foreach ($fields as $value) {

                if ($value->fake) {
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

                $primary_key = implode("\", \"", $primary_keys);

                $uid = bin2hex(random_bytes(10));

                $command .= ",\n\tCONSTRAINT {$prefix}_pk_$uid PRIMARY KEY (\"$primary_key\")";

            }

            # Chaves estrangeiras

            if (count($foreign_keys) > 0) {

                /** @var Database\ForeignKey $foreign_key */
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

                        $foreign_key_type = ($field->required) ? 'NO ACTION' : 'SET NULL';

                        $uid = bin2hex(random_bytes(10));

                        $command .= ",\n\tCONSTRAINT {$prefix}_fk_$uid "
                            . "FOREIGN KEY (\"$field->column_name\") "
                            . "REFERENCES $reference->schema.$reference->table ($reference_field->column_name) "
                            . "ON UPDATE $foreign_key_type ON DELETE $foreign_key_type";

                    }

                }

            }

            # Comandos

            $sql = "CREATE TABLE $table->schema.$table->table (\n$command\n);";

            $this->comment("Criando tabela '$table->schema.$table->table'", Color::GREEN, true);
            $this->execute($sql);

            $class = get_class($model);

            $this->comment("Adicionando descrição na tabela '$table->schema.$table->table'", Color::GREEN, true);
            $sql = "EXEC sp_addextendedproperty 'MS_Description', '$class (" . AGORA . ")', 'SCHEMA', '$table->schema', 'TABLE', '$table->table';";

            $this->execute($sql);

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

                        $this->comment("Índice '$key' já existe");

                    }

                    else {

                        $this->comment("Índice '$key' já existe com outra ordem", Color::PURPLE);

                    }

                }

            }

            if (!$index_exists) {

                $index_name = $prefix . '_index_' . bin2hex(random_bytes(10));

                $this->comment("Criando índice '$index_name'", Color::GREEN, true);

                if ($create_index->unique) {
                    $command = "CREATE UNIQUE INDEX $index_name ON $table->schema.$table->table";
                }

                else {
                    $command = "CREATE INDEX $index_name ON $table->schema.$table->table";
                }

                $command .= ' ("' . implode('", "', $create_index->columns) . '")';

                if (count($create_index->includes) > 0) {
                    $command .= ' INCLUDE ("' . implode('", "', $create_index->includes) . '")';
                }

                $command .= ';';

                $this->execute($command);

            }

        }

        # Chaves estrangeiras não utilizadas

        foreach ($info->indexes as $key => $index) {

            if (!$index->extra) {
                continue;
            }

            $this->comment("Índice '$key' não utilizado", Color::DEEP_ORANGE);

        }


        /*foreach ($arg['indexes'] as $index) {

            $indexName = "{$arg['table_2']}_index";
            $indexName2 = "{$arg['table_2']}_index";
            $btree = '';

            foreach ($index as $fld) {

                $t = substr($fld, 0, 3);

                $indexName .= "_$fld";
                $indexName2 .= "_$t";

                if ($btree != '')
                    $btree .= ", ";

                //$btree .=  "\"{$model->dbField($fld)}\"";

            }

            if (mb_strlen($indexName, 'utf8') > 128) {
                $indexName = $indexName2;
            }

            //$indexes .= "IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = '$indexName')" . PHP_EOL;
            //$indexes .= "CREATE INDEX $indexName ON $table->schema.$table->table ($btree);" . PHP_EOL;

        }*/

    }

    private function comment(string $text, string $color = Color::GRAY, bool $break_before = false): void
    {

        if ($break_before && $this->_extra_break) {
            echo "\n";
        }

        $text = preg_replace('/(\'[\w\s,.-_()]*\')/m', '<b><i>${1}</i></b>', $text);
        $text = preg_replace('/(\"[\w\s,.-_()]*\")/m', '<b>${1}</b>', $text);

        echo "<span style='color: $color;'>-- $text </span>\n";

        $this->_extra_break = true;

    }

    private function execute($comando): void
    {

        echo SqlFormatter::highlight($comando, false);

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

        if ($this->_extra_break) {
            echo "\n";
        }

        $this->_extra_break = false;

    }

}
