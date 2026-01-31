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

class SQLServer extends Connector
{

    const DB_DATE_FORMAT = 'Y-m-d';
    const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected $true_value = '1';
    protected $false_value = '0';
    protected $null_value = 'NULL';
    protected $utf_prefix = '';

    protected $_pdo;

    protected $_connected = false;

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

    public function create($arg, Model|Model2 $model): string
    {

        $fields = '';
        $constraints = '';
        $primary_key = '';
        $indexes = '';

        list($database_name, $schema_name, $table_name) = $this->names($arg);

        $arg['table_2'] = strtolower("{$database_name}_{$schema_name}_{$table_name}");

        foreach ($arg['fields'] as $key=>$value) {

            if (isset($value['primary_key'])) {

                if ($value['primary_key']) {

                    if ($primary_key != '')
                        $primary_key .= ", ";

                    $primary_key .= "\"{$model->dbField($key)}\"";

                }

            }

            if (!isset($value['many_to_many']) && !isset($value['i_many_to_many']) && !isset($value['many_choices'])) {

                if (!isset($value['required'])) $value['required'] = false;
                if (!isset($value['maxlength'])) $value['maxlength'] = 255;

                if ($fields != '') $fields .= ' , ';

                $id = '';

                if ($arg['field_id_ai'] && $key == $arg['field_id']) {
                    $id = 'IDENTITY(1,1)';
                }

                switch($value['type']) {

                    case 'text':
                    case 'iframe':
                    case 'map':
                    case 'html':
                    case 'upload':
                    case 'image':
                        $type = 'VARCHAR(MAX)';
                        break;

                    case 'string':
                    case 'password':
                    case 'link':
                        $type = "NVARCHAR({$value['maxlength']})";
                        break;

                    case 'integer':
                        $type = 'BIGINT';
                        break;

                    case 'boolean':
                        $type = 'BIT';
                        break;

                    case 'date':
                        $type = 'DATE';
                        break;

                    case 'datetime':
                        $type = 'DATETIME';
                        break;

                    case 'float':
                        $type = 'FLOAT';
                        break;

                    case 'decimal':
                    case 'numeric':
                        $decimal_places = $value['decimalplaces'] ?? 2;
                        $type = "NUMERIC(20,$decimal_places)";
                        break;

                    case 'geography':
                        $type = 'GEOGRAPHY';

                        $field = $model->dbField($key);

                        $indexName = "{$arg['table_2']}_spacial_$field";

                        $indexes .= "IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = '$indexName')" . PHP_EOL;
                        $indexes .= "CREATE SPATIAL INDEX $indexName ON $schema_name.$table_name ($field) USING GEOGRAPHY_GRID WITH (GRIDS =(LEVEL_1 = MEDIUM,LEVEL_2 = MEDIUM,LEVEL_3 = MEDIUM,LEVEL_4 = MEDIUM), CELLS_PER_OBJECT = 16, PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, FILLFACTOR = 90) ON [PRIMARY];" . PHP_EOL;

                        break;

                    default:
                        $type = 'NVARCHAR(255)';

                }

                $fields .= "[{$model->dbField($key)}] $type $id";

                if ($value['required']) $fields .= ' NOT NULL';

                if (isset($value['default']) && !is_null($value['default'])) {


                    $default = ($value['default'] == Model::NOW) ? 'GETDATE()' : $this->escape($value['default']);

                    $fields .= ' DEFAULT ';
                    $fields .= $default;

                }

                $fields .= PHP_EOL;

            }

            if (isset($value['foreign_key']))
                if (!isset($value['foreign_key_fake']) || !$value['foreign_key_fake']) {

                    $foreignkey_model = new $value['foreign_key'];
                    $foreignkey_table = $foreignkey_model->_table;
                    $foreignkey_field_id = $foreignkey_model->dbField($foreignkey_model->_field_id);
                    $foreignkey_type = ($value['required']) ? 'NO ACTION' : 'SET NULL';

                    if (($value['cascade']) ?? false) {
                        $foreignkey_type = 'CASCADE';
                    }

                    $constraints .= " , CONSTRAINT {$arg['table_2']}_fk_{$key} FOREIGN KEY (\"$key\") "
                        . "REFERENCES $foreignkey_table ($foreignkey_field_id) "
                        . "ON UPDATE $foreignkey_type ON DELETE $foreignkey_type" . PHP_EOL;

                }

        }

        if ($primary_key != '')
            $constraints .= " , CONSTRAINT {$arg['table_2']}_pkey PRIMARY KEY ($primary_key)";

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

        echo SqlFormatter::format($use);
        echo SqlFormatter::format($schema);
        echo SqlFormatter::format($create);
        echo SqlFormatter::format($indexes);
        echo SqlFormatter::format($comment);

        return $use . $schema . $create . $indexes . $comment;

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
