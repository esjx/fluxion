<?php
namespace Fluxion\Connector;

use PDO;
use PDOException;
use Fluxion\Application;
use Fluxion\Model;
use Fluxion\Model2;
use Fluxion\SqlFormatter;
use Fluxion\Database;

class SQLServer2 extends SQLServer
{

    const DB_DATE_FORMAT = 'Y-m-d';
    const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected $true_value = '1';
    protected $false_value = '0';
    protected $null_value = 'NULL';
    protected $utf_prefix = '';

    protected $_pdo;

    protected $_connected = false;

    protected ?array $_databases = null;
    protected ?string $_database = null;

    public function getPDO(): PDO
    {

        if (!$this->_connected) {

            try {

                $this->_pdo = new PDO(
                    $this->_host,
                    $this->_user,
                    $this->_pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
                        //PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_SYSTEM,
                        //PDO::ATTR_PERSISTENT => true,
                        //PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 30,
                    ]
                );

                $this->_connected = true;

            } catch (PDOException $e) {
                Application::error($e->getMessage(), 207);
            }

        }

        return $this->_pdo;

    }

    protected function updateDatabases(): void
    {

        if (is_array($this->_databases)) return;

        $this->_databases = [];

        # Buscando bancos de dados

        $sql = "SELECT name
                FROM sys.databases
                WHERE name NOT IN ('master', 'tempdb', 'model', 'msdb')
                ORDER BY name";

        $stmt = $this->getPDO()->query($sql);

        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $this->_databases[$result['name']] = [];

        }

        # Buscando esquemas

        $sql = "SELECT name, schema_id
                FROM sys.schemas
                WHERE name = 'dbo' OR (name NOT IN ('guest', 'INFORMATION_SCHEMA', 'sys')
                    AND name NOT LIKE 'db_%')
                ORDER BY name;";

        foreach ($this->_databases as $database => $value) {

            $this->getPDO()->exec("USE $database;");

            $stmt = $this->getPDO()->query($sql);

            while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {

                $this->_databases[$database][$result['name']] = [
                    'id' => $result['schema_id'],
                    'tables' => [],
                ];

            }

        }

    }

    protected function updateDatabase(Database\Table $table): void
    {

        $this->updateDatabases();

        if (!isset($this->_databases[$table->database])) {

            $this->exec("CREATE DATABASE $table->database;");

            $this->_databases[$table->database] = [
                'dbo' => [
                    'id' => 1,
                    'tables' => [],
                ],
            ];

        }

        if ($this->_database != $table->database) {
            $this->exec("USE $table->database;");
            $this->_database = $table->database;
        }

        if (!isset($this->_databases[$table->database][$table->schema])) {
            $this->exec("USE $table->database;");
            $this->exec("CREATE SCHEMA $table->schema;");
            $this->_databases[$table->database][$table->schema] = [];
        }

    }

    public function getTableInfo(Database\Table $table): array
    {

        # Dados em branco

        $info = [
            'columns' => [],
            'primary_keys' => [],
            'foreign_keys' => [],
            'indexes' => [],
        ];

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
                       IIF(t.name IN ('numeric'), c.scale, NULL) AS scale
                FROM sys.columns c
                         JOIN sys.types t ON c.user_type_id = t.user_type_id
                WHERE c.object_id = OBJECT_ID('$table->schema.$table->table')
                ORDER BY c.column_id;";

        $stmt = $this->getPDO()->query($sql);

        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $info['columns'][$result['column_name']] = [
                'exists' => true,
                'column_name' => $result['column_name'],
                'column_id' => $result['column_id'],
                'type' => $result['type'],
                'required' => !$result['is_nullable'],
                'is_identity' => $result['is_identity'],
                'max_length' => $result['max_length'],
                'precision' => $result['precision'],
                'scale' => $result['scale'],
            ];

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

        $stmt = $this->getPDO()->query($sql);

        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {

            if (!isset($info['primary_keys'][$result['pk_name']])) {
                $info['primary_keys'][$result['pk_name']] = [
                    'exists' => true,
                    'columns' => [],
                ];
            }

            $info['primary_keys'][$result['pk_name']]['columns'][] = $result['column_name'];

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

        $stmt = $this->getPDO()->query($sql);

        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $info['foreign_keys'][$result['fk_name']] = [
                'exists' => true,
                'parent_column' => $result['parent_column'],
                'referenced_schema' => $result['referenced_schema'],
                'referenced_table' => $result['referenced_table'],
                'referenced_column' => $result['referenced_column'],
                'delete_rule' => $result['delete_rule'],
                'update_rule' => $result['update_rule'],
            ];

        }

        # Retornando dados

        return $info;

    }

    public function synchronize(Model2 $model): void
    {

        $table = $model->getTable();
        $fields = $model->getFields();

        $this->updateDatabase($table);

        $prefix = strtolower("{$table->schema}_$table->table");

        $dados_tabela = $this->getTableInfo($table);

        $columns = $dados_tabela['columns'];
        $primary_keys = $dados_tabela['primary_keys'];
        $foreign_keys = $dados_tabela['foreign_keys'];

        $exists = (count($columns) > 0);

        if ($exists) {

            echo "<span style='color: gray;'>/* Tabela '$table->schema.$table->table' já existe */</span>\n\n";

        }

        $_fields = [];
        $_primary_keys = [];
        $_foreign_keys = [];

        foreach ($fields as $key => $value) {

            if ($value->fake) {
                continue;
            }

            # Definição do tipo

            $type = null;

            switch($value->getType()) {

                case 'text':
                case 'iframe':
                case 'map':
                case 'html':
                case 'upload':
                case 'image':
                    $type = 'varchar';
                    break;

                case 'string':
                case 'password':
                case 'link':
                case 'color':
                    $type = 'nvarchar';
                    break;

                case 'integer':
                    $type = 'bigint';
                    break;

                case 'boolean':
                    $type = 'bit';
                    break;

                case 'date':
                    $type = 'date';
                    break;

                case 'datetime':
                    $type = 'datetime';
                    break;

                case 'float':
                    $type = 'float';
                    break;

                case 'decimal':
                case 'numeric':
                    $type = "numeric";
                    break;

                case 'geography':
                    $type = 'geography';

                    // TODO: Criar índices espaciais

                    break;

            }

            # Criação do comando

            $comando = "[$value->column_name] $type";

            # Complementos

            if ($type == 'varchar' && $value->max_length) {
                $comando .= "($value->max_length)";
            }

            elseif ($type == 'varchar' && !$value->max_length) {
                $comando .= "(max)";
            }

            elseif ($type == 'nvarchar') {
                $comando .= "($value->max_length)";
            }

            elseif ($type == 'decimal') {
                $comando .= "(20,$value->decimal_places)";
            }

            # Valores nulos

            if ($value->required || $value->identity) {
                $comando .= ' not null';
            }

            # Auto incremento

            if ($value->identity) {
                $comando .= ' identity(1,1)';
            }

            # Valor padrão

            if ($value->default) {
                $default = ($value->default == Model::NOW) ? 'GETDATE()' : $this->escape($value->default);
                $comando .= " default $default";
            }

            # Chave primária

            if ($value->primary_key) {
                $_primary_keys[] = $value->column_name;
            }

            # Chave estrangeira

            if (isset($value->foreign_key)) {
                $_foreign_keys[$key] = $value->foreign_key;
            }

            if ($exists) {

                if (!isset($columns[$key])) {

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tADD $comando;";

                    $this->exec($sql);

                }

                elseif ($columns[$key]['type'] != $type
                    || $columns[$key]['max_length'] != $value->max_length
                    || $columns[$key]['required'] != $value->required) {

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tALTER COLUMN $comando;";

                    $this->exec($sql);

                }

            }

            else {
                $_fields[] = "\t$comando";
            }

        }

        if ($exists) {

            # Chaves primárias

            if (count($_primary_keys) > 0) {

                # Verificar se existe chave com os mesmos campos

                $primary_key_exists = false;

                foreach ($primary_keys as $key => $primary_key) {

                    # Já existe uma chave com os mesmos campos

                    if ($primary_key['columns'] == $_primary_keys) {

                        $primary_key_exists = true;

                        echo "<span style='color: gray;'>/* Chave primária '$key' já existe */</span>\n\n";

                    }

                }

                if (!$primary_key_exists) {

                    foreach ($primary_keys as $key => $primary_key) {

                        echo "<span style='color: red;'>/* Apagando chave primária '$key' */</span>\n\n";

                        $sql = "ALTER TABLE $table->schema.$table->table\n"
                            . "\tDROP CONSTRAINT $key;";

                        $this->exec($sql);

                    }

                    $primary_key_name = "{$prefix}_pkey";
                    $primary_key = implode("\", \"", $_primary_keys);

                    echo "<span style='color: green;'>/* Criando chave primária '$primary_key_name' */</span>\n\n";

                    $sql = "ALTER TABLE $table->schema.$table->table\n"
                        . "\tADD CONSTRAINT $primary_key_name PRIMARY KEY (\"$primary_key\");";

                    $this->exec($sql);

                }

            }

            # Chaves estrangeiras

            if (count($_foreign_keys) > 0) {

                /** @var Database\ForeignKey $foreign_key */
                foreach ($_foreign_keys as $key => $foreign_key) {

                    $foreign_key_name = "{$prefix}_fk_$key";

                    if (!$foreign_key->real) {
                        continue;
                    }

                    $reference = $foreign_key->getReferenceModel()->getTable();

                    $field = $fields[$key];

                    $foreign_key_exists = false;

                    $reference_field = $foreign_key->getField();

                    $foreign_key_type = ($field->required) ? 'NO_ACTION' : 'SET_NULL';

                    foreach ($foreign_keys as $fk_key => $fk_value) {

                        if ($fk_value['parent_column'] != $field->column_name) {
                            continue;
                        }

                        if ($fk_value['referenced_schema'] != $reference->schema
                            || $fk_value['referenced_table'] != $reference->table
                            || $fk_value['referenced_column'] != $reference_field->column_name
                            || $fk_value['delete_rule'] != $foreign_key_type
                            || $fk_value['update_rule'] != $foreign_key_type) {

                            echo "<span style='color: red;'>/* Apagando chave estrangeira '$fk_key' */</span>\n\n";

                            $sql = "ALTER TABLE $table->schema.$table->table\n"
                                . "\tDROP CONSTRAINT $fk_key;";

                            $this->exec($sql);

                        }

                        else {

                            $foreign_key_exists = true;

                            echo "<span style='color: gray;'>/* Chave estrangeira '$fk_key' já existe */</span>\n\n";

                        }

                    }

                    if (!$foreign_key_exists) {

                        echo "<span style='color: green;'>/* Criando chave estrangeira '$foreign_key_name' */</span>\n\n";

                        $foreign_key_type = ($field->required) ? 'NO ACTION' : 'SET NULL';

                        $sql = "ALTER TABLE $table->schema.$table->table\n"
                            . "\tADD CONSTRAINT $foreign_key_name "
                            . "FOREIGN KEY (\"$field->column_name\") "
                            . "REFERENCES $reference->schema.$reference->table ($reference_field->column_name) "
                            . "ON UPDATE $foreign_key_type ON DELETE $foreign_key_type";

                        $this->exec($sql);

                    }

                }

            }

        }

        else {

            echo "<span style='color: green;'>/* Criando tabela '$table->schema.$table->table' */</span>\n\n";

            # Campos da tabela

            $comando = implode(",\n", $_fields);

            # Chaves primárias

            if (count($_primary_keys) > 0) {

                $primary_key = implode("\", \"", $_primary_keys);

                $comando .= ",\n\tCONSTRAINT {$prefix}_pkey PRIMARY KEY (\"$primary_key\")";

            }

            # Chaves estrangeiras

            if (count($_foreign_keys) > 0) {

                /** @var Database\ForeignKey $foreign_key */
                foreach ($_foreign_keys as $key => $foreign_key) {

                    if (!$foreign_key->real) {
                        continue;
                    }

                    $reference = $foreign_key->getReferenceModel()->getTable();

                    if ($reference->database != $table->database) {
                        echo "<span style='color: orange;'>IMPORTANTE</span>: Não é possível a inclusão de chave estrangeira de outro banco de dados (campo '$key').</br>";
                    }

                    else {

                        $field = $fields[$key];

                        $reference_field = $foreign_key->getField();

                        $foreign_key_type = ($field->required) ? 'NO ACTION' : 'SET NULL';

                        $comando .= ",\n\tCONSTRAINT {$prefix}_fk_$key "
                            . "FOREIGN KEY (\"$field->column_name\") "
                            . "REFERENCES $reference->schema.$reference->table ($reference_field->column_name) "
                            . "ON UPDATE $foreign_key_type ON DELETE $foreign_key_type";

                    }

                }

            }

            # Comandos

            $sql = "CREATE TABLE $table->schema.$table->table (\n$comando\n);";

            $this->exec($sql);

            $sql = "EXEC sp_addextendedproperty 'MS_Description', '$model (" . AGORA . ")', 'SCHEMA', '$table->schema', 'TABLE', '$table->table';";

            $this->exec($sql);

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

    public function exec($comando): void
    {

        echo SqlFormatter::highlight($comando, false) . "\n";

        //return;

        try {
            $this->getPDO()->exec($comando);
        } catch (PDOException $e) {
            $erro = $e->getMessage();
            $exp = explode('[SQL Server]', $erro);

            if (isset($exp[1])) {
                $erro = $exp[1];
            }

            echo "\n<span style='color: red;'>/* <b>ERRO</b>: $erro */</span>\n\n";
        }

    }

}
