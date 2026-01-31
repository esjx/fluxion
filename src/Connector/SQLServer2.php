<?php
namespace Fluxion\Connector;

use Fluxion\Model2;
use PDO;
use Exception;
use PDOException;
use Fluxion\Application;
use Fluxion\Auth\Auth;
use Fluxion\Config;
use Fluxion\Model;
use Fluxion\MnModel;
use Fluxion\MnChoicesModel;
use Fluxion\SqlFormatter;
use Fluxion\Util;
use Fluxion\Database;

class SQLServer2 extends Connector
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

    protected function getDatabases(): void
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

    protected function updateDatabase(string $database, $schema): void
    {

        $this->getDatabases();

        if (!isset($this->_databases[$database])) {

            $this->exec("CREATE DATABASE $database;");

            $this->_databases[$database] = [
                'dbo' => [
                    'id' => 1,
                    'tables' => [],
                ],
            ];

        }

        if ($this->_database != $database) {
            $this->exec("USE $database;");
            $this->_database = $database;
        }

        if (!isset($this->_databases[$database][$schema])) {
            $this->exec("USE $database;");
            $this->exec("CREATE SCHEMA $schema;");
            $this->_databases[$database][$schema] = [];
        }

    }

    public function tableFields(Database\Table $table): array
    {

        # Dados em branco

        $fields = [
            'columns' => [],
            'primary_keys' => [],
            'foreign_keys' => [],
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

            $fields['columns'][$result['column_name']] = [
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

            if (!isset($fields['primary_keys'][$result['pk_name']])) {
                $fields['primary_keys'][$result['pk_name']] = [
                    'exists' => true,
                    'columns' => [],
                ];
            }

            $fields['primary_keys'][$result['pk_name']]['columns'][] = $result['column_name'];

        }

        # Buscando chaves estraangeiras da tabela

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

            $fields['foreign_keys'][$result['fk_name']] = [
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

        return $fields;

    }

    public function create2($arg, Model2 $model): string
    {

        $fields = '';
        $constraints = '';
        $primary_key = '';
        $indexes = '';

        /** @var Database\Table $table */
        $table = $arg['table'];

        $table_name = $table->table;
        $schema_name = $table->schema;
        $database_name = $table->database;

        $this->updateDatabase($database_name, $schema_name);

        $constraint_prefix = strtolower("{$schema_name}_{$table_name}");

        $dados_tabela = $this->tableFields($table);

        $columns = $dados_tabela['columns'];
        $primary_keys = $dados_tabela['primary_keys'];
        $foreign_keys = $dados_tabela['foreign_keys'];

        $exists = (count($columns) > 0);

        if ($exists) {

            echo "<span style='color: gray;'>/* Tabela '$schema_name.$table_name' já existe */</span>\n\n";

        }

        $_fields = [];
        $_primary_keys = [];
        $_foreign_keys = [];

        /** @var Database\Field $value */
        foreach ($arg['fields'] as $key => $value) {

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

                    /*$indexName = "{$arg['table_2']}_spacial_{$value->column_name}";

                    $indexes .= "IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = '$indexName')" . PHP_EOL;
                    $indexes .= "CREATE SPATIAL INDEX $indexName ON $schema_name.$table_name ($value->column_name) USING GEOGRAPHY_GRID WITH (GRIDS =(LEVEL_1 = MEDIUM,LEVEL_2 = MEDIUM,LEVEL_3 = MEDIUM,LEVEL_4 = MEDIUM), CELLS_PER_OBJECT = 16, PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, FILLFACTOR = 90) ON [PRIMARY];" . PHP_EOL;*/

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

            # Autoincremento

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

                    $sql = "ALTER TABLE $schema_name.$table_name\n"
                        . "\tADD $comando;";

                    $this->exec($sql);

                }

                elseif ($columns[$key]['type'] != $type
                    || $columns[$key]['max_length'] != $value->max_length
                    || $columns[$key]['required'] != $value->required) {

                    $sql = "ALTER TABLE $schema_name.$table_name\n"
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

                        $sql = "ALTER TABLE $schema_name.$table_name\n"
                            . "\tDROP CONSTRAINT $key;";

                        $this->exec($sql);

                    }

                    $primary_key_name = "{$constraint_prefix}_pkey";
                    $primary_key = implode("\", \"", $_primary_keys);

                    echo "<span style='color: green;'>/* Criando chave primária '$primary_key_name' */</span>\n\n";

                    $sql = "ALTER TABLE $schema_name.$table_name\n"
                        . "\tADD CONSTRAINT $primary_key_name PRIMARY KEY (\"$primary_key\");";

                    $this->exec($sql);

                }

            }

            # Chaves estrangeiras

            if (count($_foreign_keys) > 0) {

                /** Database\ForeignKey $foreign_key */
                foreach ($_foreign_keys as $key => $foreign_key) {

                    $foreign_key_name = "{$constraint_prefix}_fk_{$key}";

                    if (!$foreign_key->real) {
                        continue;
                    }

                    $reference = $foreign_key->getReferenceModel()->getTable();

                    /** Database\Field $field */
                    $field = $arg['fields'][$key];

                    $foreign_key_exists = false;

                    /** Database\Field $reference_field */
                    $reference_field = $foreign_key->getField();

                    $foreignkey_type = ($field->required) ? 'NO_ACTION' : 'SET_NULL';

                    foreach ($foreign_keys as $fk_key => $fk_value) {

                        if ($fk_value['parent_column'] != $field->column_name) {
                            continue;
                        }

                        if ($fk_value['referenced_schema'] != $reference->schema
                            || $fk_value['referenced_table'] != $reference->table
                            || $fk_value['referenced_column'] != $reference_field->column_name
                            || $fk_value['delete_rule'] != $foreignkey_type
                            || $fk_value['update_rule'] != $foreignkey_type) {

                            echo "<span style='color: red;'>/* Apagando chave estrangeira '$fk_key' */</span>\n\n";

                            $sql = "ALTER TABLE $schema_name.$table_name\n"
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

                        $foreignkey_type = ($field->required) ? 'NO ACTION' : 'SET NULL';

                        $sql = "ALTER TABLE $schema_name.$table_name\n"
                            . "\tADD CONSTRAINT $foreign_key_name "
                            . "FOREIGN KEY (\"$field->column_name\") "
                            . "REFERENCES $reference->schema.$reference->table ($reference_field->column_name) "
                            . "ON UPDATE $foreignkey_type ON DELETE $foreignkey_type";

                        $this->exec($sql);

                    }

                }

            }

        }

        else {

            echo "<span style='color: green;'>/* Criando tabela '$schema_name.$table_name' */</span>\n\n";

            # Campos da tabela

            $fields = implode(",\n", $_fields);

            # Chaves primárias

            if (count($_primary_keys) > 0) {

                $primary_key = implode("\", \"", $_primary_keys);

                $fields .= ",\n\tCONSTRAINT {$constraint_prefix}_pkey PRIMARY KEY (\"$primary_key\")";

            }

            # Chaves estrangeiras

            if (count($_foreign_keys) > 0) {

                /** Database\ForeignKey $foreign_key */
                foreach ($_foreign_keys as $key => $foreign_key) {

                    if (!$foreign_key->real) {
                        continue;
                    }

                    $reference = $foreign_key->getReferenceModel()->getTable();

                    if ($reference->database != $table->database) {
                        echo "<span style='color: orange;'>IMPORTANTE</span>: Não é possível a inclusão de chave estrangeira de outro banco de dados (campo '$key').</br>";
                    }

                    else {

                        /** Database\Field $field */
                        $field = $arg['fields'][$key];

                        /** Database\Field $reference_field */
                        $reference_field = $foreign_key->getField();

                        $foreignkey_type = ($field->required) ? 'NO ACTION' : 'SET NULL';

                        $fields .= ",\n\tCONSTRAINT {$constraint_prefix}_fk_{$key} "
                            . "FOREIGN KEY (\"$field->column_name\") "
                            . "REFERENCES $reference->schema.$reference->table ($reference_field->column_name) "
                            . "ON UPDATE $foreignkey_type ON DELETE $foreignkey_type";

                    }

                }

            }

            # Comandos

            $sql = "CREATE TABLE $schema_name.$table_name (\n$fields\n);";

            $this->exec($sql);

            $sql = "EXEC sp_addextendedproperty 'MS_Description', '{$arg['model']} (" . AGORA . ")', 'SCHEMA', '$schema_name', 'TABLE', '$table_name';";

            $this->exec($sql);

        }


        foreach ($arg['indexes'] as $index) {

            $indexName = "{$arg['table_2']}_index";
            $indexName2 = "{$arg['table_2']}_index";
            $btree = '';

            foreach ($index as $fld) {

                $t = substr($fld, 0, 3);

                $indexName .= "_$fld";
                $indexName2 .= "_$t";

                if ($btree != '')
                    $btree .= ", ";

                $btree .=  "\"{$model->dbField($fld)}\"";

            }

            if (mb_strlen($indexName, 'utf8') > 128) {
                $indexName = $indexName2;
            }

            $indexes .= "IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = '$indexName')" . PHP_EOL;
            $indexes .= "CREATE INDEX $indexName ON $schema_name.$table_name ($btree);" . PHP_EOL;

        }

        $use = ($database_name == '') ? '' : "USE $database_name;" . PHP_EOL;

        $schema = '';

        if ($schema_name != 'dbo')
            $schema = "IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = '$schema_name')" . PHP_EOL
                . "BEGIN" . PHP_EOL
                . "EXEC('CREATE SCHEMA $schema_name')" . PHP_EOL
                . "END;" . PHP_EOL;

        $create = "IF NOT EXISTS(SELECT * FROM INFORMATION_SCHEMA.TABLES"
            ." WHERE TABLE_SCHEMA = '$schema_name' AND TABLE_NAME = '$table_name')" . PHP_EOL;

        $create .= "CREATE TABLE $schema_name.$table_name ($fields $constraints);" . PHP_EOL;

        $comment = "EXEC sp_addextendedproperty 'MS_Description', '{$arg['model']} (" . AGORA . ")', 'SCHEMA', '$schema_name', 'TABLE', '$table_name';";

        //echo SqlFormatter::format($use);
        //echo SqlFormatter::format($schema);
        //echo SqlFormatter::format($create);
        //echo SqlFormatter::format($indexes);
        //echo SqlFormatter::format($comment);

        return $use . $schema . $create . $indexes . $comment;

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

    public function select($arg, Config $config, Auth $auth, Model $model): string
    {

        $where = '';
        foreach ($arg['where'] as $k)
            $where .= (($where == '') ? " WHERE " : " AND ") . $this->filter($k, $arg['database'], $config, $auth, $model);

        $orderBy = '';
        foreach ($arg['orderBy'] as $k)
            $orderBy .= (($orderBy == '') ? " ORDER BY" : ",") . " [{$model->dbField($k['field'])}] {$k['order']}";

        $groupBy = '';
        foreach ($arg['groupBy'] as $k)
            $groupBy .= (($groupBy == '') ? " GROUP BY" : ",") . " [{$model->dbField($k['field'])}]";

        $top = (isset($arg['limit']['limit']) && $arg['limit']['offset'] == 0) ?
            " TOP {$arg['limit']['limit']} " :
            '';

        $limit = (isset($arg['limit']['limit']) && $arg['limit']['offset'] > 0) ?
            " OFFSET {$arg['limit']['offset']} ROWS FETCH NEXT {$arg['limit']['limit']} ROWS ONLY " :
            '';

        if ((isset($arg['limit']['limit']) && $arg['limit']['offset'] > 0) && $orderBy == '') {
            $orderBy = " ORDER BY [{$model->dbField($model->getFieldId())}]";
        }

        list($database_name, $schema_name, $table_name) = $this->names($arg);

        $lock = /*($this->getPDO()->inTransaction()) ? ' WITH (tablock, holdlock) ' :*/ ' WITH (nolock) ';

        return "SELECT $top {$arg['fields']} FROM [$database_name].[$schema_name].[$table_name] $lock $where $groupBy $orderBy $limit";

    }

    public function insert($arg, Model $model, $force_fields = false): string
    {

        $fields = '';
        $regs = '';

        foreach ($arg['fields'] as $i) {

            $fields = '';
            $values = '';

            foreach ($i as $key => $value)
                if (!isset($value['many_to_many']) && !isset($value['i_many_to_many']) && !isset($value['many_choices']) && (isset($value['value']) || $force_fields)) {

                    $fields .= (($fields != '') ? ", " : "") . "[{$model->dbField($key)}]";
                    $values .= (($values != '') ? ", " : "") . $this->escape((isset($value['value'])) ? $value['value'] : null);

                }

            $regs .= (($regs != '') ? ", " : "") . "($values)";

        }

        $extra = ($arg['field_id'] != '') ? "OUTPUT INSERTED.[{$model->dbField($arg['field_id'])}] " : "";

        list($database_name, $schema_name, $table_name) = $this->names($arg);

        return "INSERT INTO [$database_name].[$schema_name].[$table_name] ($fields) $extra VALUES $regs;";

    }

    public function filter($filter, $database, Config $config, Auth $auth, Model $model): string
    {

        $not = ($filter['not']) ? ' NOT ' : '';

        if (is_object($filter['field']))
            if (get_class($filter['field']) == 'Fluxion\Sql') {

                $where = '';
                foreach ($filter['field']->_filters as $k) {
                    if ($k != []) {
                        $where .= (($where == '') ? "" : " {$filter['field']->_type} ") . $this->filter($k, $database, $config, $auth, $model);
                    }
                }

                return "$not($where)";

            }

        $_field = explode('__', $filter['field']);

        $field = $model->dbField($_field[0]);

        $type = '=';
        $pure = true;
        $like = false;

        for ($i = 1; $i <= count($_field); $i++) {

            if (isset($_field[$i])) {

                if ($_field[$i] == 'json') {

                    $json_name = $_field[$i + 1] ?? Application::error('Utilizar padrão campo__json__variavel');

                    $field = "JSON_VALUE(\"$field\", '$.$json_name')";
                    $pure = false;

                    $i++;

                    $filter['value'] = (string) $filter['value'];

                    continue;

                }

                switch ($_field[$i]) {

                    case 'ne':
                        $type = "<>";
                        break;
                    case 'lt':
                        $type = '<';
                        break;
                    case 'gt':
                        $type = '>';
                        break;
                    case 'lte':
                        $type = '<=';
                        break;
                    case 'gte':
                        $type = '>=';
                        break;
                    case 'like':
                        $type = ' LIKE ';
                        $like = true;
                        break;
                    case 'fulltext':
                        $type = 'fulltext';
                        break 2;

                    default:
                        $type = "=";

                }

                switch ($_field[$i]) {

                    case 'second':
                    case 'minute':
                    case 'hour':
                    case 'day':
                    case 'week':
                    case 'month':
                    case 'year':
                        $field = "DATEPART($_field[$i], \"$field\")";
                        $pure = false;
                        break;

                    case 'dow':
                        $field = "DATEPART(weekday, \"$field\")";
                        $pure = false;
                        break;

                    case 'date':
                        $field = "CAST(\"$field\" AS DATE)";
                        $pure = false;
                        break;

                    case 'length':
                        $field = "LEN(\"$field\")";
                        $pure = false;
                        break;

                    case 'only_number':
                    case 'clean':
                        $field = "REPLACE(REPLACE(REPLACE(REPLACE(\"$field\", ' ', ''), '.', ''), '/', ''), '-', '')";
                        $pure = false;
                        break;

                }

            }

        }

        if ($pure)
            $field = "[$field]";

        if ($type == 'fulltext') {

            $words = str_replace(',', ' ', $filter['value']);
            $arr_words = explode(' ', $words);

            $arr_words2 = [];

            foreach ($arr_words as $word) {

                if (trim($word) == '') continue;

                $arr_words2[] = $word;

            }

            if (count($arr_words2) == 1) {

                $ft_search = $arr_words2[0];

                return "FREETEXT($field,'$ft_search')";
                //return "CONTAINS($field,'\"$ft_search*\"')";

            } else {

                $ft_search = str_replace(' ', '', str_replace(', ,', ',', Util::retiraEspeciais(implode(',', $arr_words2))));

                return "CONTAINS($field,'NEAR($ft_search)')";

            }

        }

        if (is_null($filter['value']))
            return " {$field} IS{$not} NULL";

        if (is_string($filter['value']) || is_numeric($filter['value']) || is_bool($filter['value'])) {

            $name = $_field[0];
            $escaped_value = $filter['value'];

            if ($like) {
                $escaped_value = str_replace(['_', '%'], '', $escaped_value);
            }

            if (in_array($model->getFields()[$name]['type'], ['integer', 'float'])
                && $escaped_value
                && !is_numeric($escaped_value)
                && !is_bool($escaped_value)) {
                Application::error("O valor <b>{$filter['value']}</b> não é numérico!");
            }

            return " {$not} {$field}{$type}" . $this->escape($filter['value']);

        }

        if (is_array($filter['value']))
            if (count($filter['value']) > 0) {

                if (in_array(null, $filter['value']))
                    return "({$field}{$not} IN " . $this->escape($filter['value']) . " OR {$field} IS {$not} NULL)";

                return "{$field}{$not} IN " . $this->escape($filter['value']);

            } else
                return '1=0';

        if (is_object($filter['value']))
            if (get_class($filter['value']) == 'Fluxion\Query') {

                if ($database != $filter['value']->_sql['database'])
                    return "{$field}{$not} IN " . $this->escape($filter['value']->toArray($config, $auth));

                if ($filter['value']->_sql['class'] == MnModel::class) {
                    return " {$field}{$not} IN (" . self::select($filter['value']->_sql, $config, $auth, $filter['value']->_sql['mn_model']) . ")";
                }

                if ($filter['value']->_sql['class'] == MnChoicesModel::class) {
                    return " {$field}{$not} IN (" . self::select($filter['value']->_sql, $config, $auth, $filter['value']->_sql['mn_model']) . ")";
                }

                return " {$field}{$not} IN (" . self::select($filter['value']->_sql, $config, $auth, new $filter['value']->_sql['class']($config, $auth)) . ")";
            }

        return false;

    }

    public function update($arg, Config $config, Auth $auth, Model $model): string
    {

        $changes = '';

        $where = '';
        foreach ($arg['where'] as $k)
            $where .= (($where == '') ? " WHERE " : " AND ") . $this->filter($k, $arg['database'], $config, $auth, $model);

        foreach ($arg['fields'] as $key=>$value)
            if (!isset($value['many_to_many']) && !isset($value['i_many_to_many']) && !isset($value['many_choices']) && $key != $arg['field_id'])

                if ($value['changed'])
                    if (isset($value['value']))
                        $changes .= (($changes != '') ? ", " : "") . "[{$model->dbField($key)}]=" . $this->escape($value['value']);
                    else if ($key != '_insert')
                        $changes .= (($changes != '') ? ", " : "") . "[{$model->dbField($key)}]=" . $this->escape(null);

        list($database_name, $schema_name, $table_name) = $this->names($arg);

        if ($changes != '')
            return "UPDATE [$database_name].[$schema_name].[$table_name] SET $changes {$where};";

        return false;

    }

    private function names(array $arg): array
    {

        $database_name = '';
        $schema_name = 'dbo';

        if (preg_match('/database=(?P<database>[A-Za-z0-9_]+)/si', $this->_host, $data)) {

            $database_name = $data['database'];

        }

        if (preg_match('/^(?P<database>[A-Za-z0-9_]+)\.(?P<schema>[A-Za-z0-9_]+)\.(?P<table>[A-Za-z0-9_]+)$/si', $arg['table'], $data)) {

            $database_name = $data['database'];
            $schema_name = $data['schema'];
            $table_name = $data['table'];

        } elseif (preg_match('/^(?P<schema>[A-Za-z0-9_]+)\.(?P<table>[A-Za-z0-9_]+)$/si', $arg['table'], $data)) {

            $schema_name = $data['schema'];
            $table_name = $data['table'];

        } else {

            $table_name = $arg['table'];

        }

        return [$database_name, $schema_name, $table_name];

    }

    public function error(Exception $e)
    {

        if ($e->getCode() == 40001) {

            Application::error('O registro que você tentou alterar está sendo utilizado! <br><br><b class="text-black">Tente novamente em instantes.</b>', 500, false, false);

        } elseif ($e->getCode() == 22003) {

            Application::error($e->getMessage(), 500);

            Application::error('Valor numérico acima do permitido! <br><br><b class="text-black">Verifique os valores numéricos inseridos.</b>', 500, false, false);

        } else {

            Application::error($e->getMessage(), 500);

        }

    }

}
