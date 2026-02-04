<?php
namespace Fluxion\Connector;

use Exception;
use Fluxion\Color;
use Fluxion\Config2;
use Fluxion\CustomException;
use Fluxion\MnModel2;
use Fluxion\SqlFormatter;
use PDO;
use PDOException;
use PDOStatement;
use Fluxion\Application;
use Fluxion\Auth\Auth;
use Fluxion\Config;
use Fluxion\Model;
use Fluxion\Model2;
use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\Utils;

class Connector
{

    protected ?StreamInterface $log_stream = null;
    /**
     * @var true
     */
    protected bool $_extra_break;

    public function __construct()
    {
        //$this->log_stream = Utils::streamFor('');
    }

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

    protected function execute($comando): void
    {

        if (!is_null($this->log_stream)) {
            $this->log_stream->write(SqlFormatter::highlight($comando, false));
        }

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



    const DB_DATE_FORMAT = 'Y-m-d';
    const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected $true_value = 'TRUE';
    protected $false_value = 'FALSE';
    protected $null_value = 'NULL';
    protected $utf_prefix = '';

    protected $_host;
    protected $_user;
    protected $_pass;

    protected PDO $_pdo;

    protected $_connected = false;

    public function disconnect()
    {

        $this->_connected = false;
        $this->_pdo = null;

    }

    public function getPDO(): PDO
    {

        if (!$this->_connected) {

            try {

                $this->_pdo = new PDO(
                    $this->_host,
                    $this->_user,
                    $this->_pass,
                    [
                        PDO::ATTR_PERSISTENT => true,
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]
                );

                //$this->_pdo->exec("SET TIMEZONE TO 'America/Recife';");

                $this->_connected = true;

            } catch (PDOException $e) {
                Application::error($e->getMessage(), 207);
            }

        }

        return $this->_pdo;

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

    public function lastInsertId($pdo, PDOStatement $query, $field_id)
    {

        $ret = $query->fetch(PDO::FETCH_ASSOC);

        return $ret[$field_id];

    }

    public function filter($filter, $database, Config $config, Auth $auth, Model $model): string
    {

        $not = ($filter['not']) ? ' NOT ' : '';

        if (is_object($filter['field']))
            if (get_class($filter['field']) == 'Fluxion\Sql') {

                $where = '';
                foreach ($filter['field']->_filters as $k)
                    $where .= (($where == '') ? "" : " {$filter['field']->_type} ") . $this->filter($k, $database, $config, $auth, $model);

                return "$not($where)";

            }

        $_field = explode('__', $filter['field']);

        $field = $model->dbField($_field[0]);

        $type = '=';
        $pure = true;

        for ($i = 1; $i <= count($_field); $i++) {

            if (isset($_field[$i])) {

                switch($_field[$i]) {

                    case 'ne': $type = "<>"; break;
                    case 'lt': $type = '<'; break;
                    case 'gt': $type = '>'; break;
                    case 'lte': $type = '<='; break;
                    case 'gte': $type = '>='; break;
                    case 'like': $type = ' ILIKE '; break;

                    default: $type = "=";

                }

                switch($_field[$i]) {

                    case 'second':
                        $field = "EXTRACT(SECOND FROM \"$field\")";
                        $pure = false;
                        break;

                    case 'minute':
                        $field = "EXTRACT(MINUTE FROM \"$field\")";
                        $pure = false;
                        break;

                    case 'hour':
                        $field = "EXTRACT(HOUR FROM \"$field\")";
                        $pure = false;
                        break;

                    case 'day':
                        $field = "EXTRACT(DAY FROM \"$field\")";
                        $pure = false;
                        break;

                    case 'dow':
                        $field = "EXTRACT(DOW FROM \"$field\")";
                        $pure = false;
                        break;

                    case 'week':
                        $field = "EXTRACT(WEEK FROM \"$field\")";
                        $pure = false;
                        break;

                    case 'month':
                        $field = "EXTRACT(MONTH FROM \"$field\")";
                        $pure = false;
                        break;

                    case 'year':
                        $field = "EXTRACT(YEAR FROM \"$field\")";
                        $pure = false;
                        break;

                    case 'length':
                        $field = "CHAR_LENGTH(\"$field\")";
                        $pure = false;
                        break;

                    case 'only_number':
                        $field = "REPLACE(REPLACE(REPLACE(REPLACE(\"$field\", ' ', ''), '.', ''), '/', ''), '-', '')";
                        $pure = false;
                        break;

                }

            }

        }

        if ($pure)
            $field = "\"$field\"";

        if (is_null($filter['value']))
            return "($field IS $not NULL)";

        if (is_string($filter['value']) || is_numeric($filter['value']) || is_bool($filter['value']))
            return "$not($field $type" . $this->escape($filter['value']) . ")";

        if (is_array($filter['value']))
            if (count($filter['value']) > 0) {

                if (in_array(null, $filter['value']))
                    return "($field $not IN " . $this->escape($filter['value']) . " OR $field IS $not NULL)";

                return "($field $not IN " . $this->escape($filter['value']) . ")";

            } else
                return '(NULL)';

        if (is_object($filter['value']))
            if (get_class($filter['value']) == 'Fluxion\Query') {

                if ($database != $filter['value']->_sql['database'])
                    return "($field $not IN " . $this->escape($filter['value']->toArray($config, $auth)) . ")";

                return " ($field $not IN (" . Connector::select($filter['value']->_sql, $config, $auth, $model) . "))";
            }

        return false;

    }

    public function select($arg, Config $config, Auth $auth, Model $model): string
    {

        $where = '';
        foreach ($arg['where'] as $k)
            $where .= (($where == '') ? " WHERE " : " AND ") . $this->filter($k, $arg['database'], $config, $auth, $model);

        $orderBy = '';
        foreach ($arg['orderBy'] as $k)
            $orderBy .= (($orderBy == '') ? " ORDER BY" : ",") . " {$model->dbField($k['field'])} {$k['order']}";

        $groupBy = '';
        foreach ($arg['groupBy'] as $k)
            $groupBy .= (($groupBy == '') ? " GROUP BY" : ",") . " {$model->dbField($k['field'])}";

        $limit = (isset($arg['limit']['limit'])) ?
            " LIMIT {$arg['limit']['limit']} OFFSET {$arg['limit']['offset']}" :
            '';

        return "SELECT {$arg['fields']} FROM {$arg['table']} $where $groupBy $orderBy $limit";

    }

    public function drop($arg): string
    {

        return "DROP TABLE IF EXISTS {$arg['table']} CASCADE;";

    }

    protected function executeSync(Model2 $model): void
    {

    }

    public function create($arg, Model|Model2 $model): string
    {

        $fields = '';
        $constraints = '';
        $primary_key = '';
        $indexes = '';

        $arg['table_2'] = str_replace('.', '_', $arg['table']);

        $schema_name = '';
        //$table_name = '';

        if (preg_match('/^(?P<schema>[a-z0-9_]+).(?P<table>[a-z0-9-_.=]+)$/si', $arg['table'], $data)) {
            $schema_name = $data['schema'];
            //$table_name = $data['table'];
		}

        if ($arg['field_id'] == 'id')
            if ($arg['field_id_ai'])
                $fields .= "\"{$arg['field_id']}\" serial NOT NULL";
            else
                $fields .= "\"{$arg['field_id']}\" bigint NOT NULL";

        foreach ($arg['fields'] as $key=>$value) {

            if (isset($value['primary_key']))
                if ($value['primary_key']) {

                    if ($primary_key != '')
                        $primary_key .= ", ";

                    $primary_key .=  "\"{$model->dbField($key)}\"";
                }

            if (!isset($value['many_to_many']) && !isset($value['i_many_to_many']) && !isset($value['many_choices']) && $key != 'id') {

                if (!isset($value['required'])) $value['required'] = false;
                if (!isset($value['maxlength'])) $value['maxlength'] = 255;

                if ($fields != '') $fields .= ' , ';

                switch($value['type']) {

                    case 'upload':
                    case 'image': $type = "text"; break;
                    case 'string':
                    case 'password':
                    case 'link': $type = "character varying({$value['maxlength']})"; break;
                    case 'integer': $type = 'bigint'; break;
                    case 'boolean': $type = 'boolean'; break;
                    case 'date': $type = 'date'; break;
                    case 'datetime': $type = 'timestamp'; break;
                    case 'text': $type = 'text'; break;
                    case 'float': $type = 'NUMERIC(18,2)'; break;

                    default: $type = $value['type'];

                }

                $fields .= "\"{$model->dbField($key)}\" $type";

                if ($value['required']) $fields .= ' NOT NULL';

                if (isset($value['foreign_key']))
                    if (!isset($value['foreign_key_fake']) || !$value['foreign_key_fake']) {

                        $foreignkey_model = new $value['foreign_key'];
                        $foreignkey_table = $foreignkey_model->_table;
                        $foreignkey_field_id = $foreignkey_model->_field_id;
                        $foreignkey_type = ($value['required']) ? 'CASCADE' : 'SET NULL';

                        $constraints .= " , CONSTRAINT {$arg['table_2']}_fk_$key FOREIGN KEY (\"$key\") "
                            . "REFERENCES $foreignkey_table ($foreignkey_field_id) MATCH SIMPLE "
                            . "ON UPDATE $foreignkey_type ON DELETE $foreignkey_type";

                    }

            }

        }

        if ($primary_key != '')
            $constraints .= " , CONSTRAINT {$arg['table_2']}_pkey PRIMARY KEY ($primary_key)";

        $ts = date('Y-m-d H:i:s');

        foreach ($arg['indexes'] as $index) {

            $indexName = "{$arg['table_2']}_index";
            $btree = '';

            foreach ($index as $fld) {

                $indexName .= "_$fld";

                if ($btree != '')
                    $btree .= ", ";

                $btree .=  "\"{$model->dbField($fld)}\"";

            }

            if ($schema_name != '')
                $indexes .= "DO $$ "
                    . "BEGIN "
                    . "IF NOT EXISTS ( "
					. "SELECT 1 "
					. "FROM   pg_class c "
					. "JOIN   pg_namespace n ON n.oid = c.relnamespace "
					. "WHERE  c.relname = '$indexName' "
					. "AND    n.nspname = '$schema_name' "
					. ") THEN "
                    . "CREATE INDEX $indexName ON {$arg['table']} USING BTREE($btree); "
                    . "END IF; "
                    . "END$$;";
            else
                $indexes .= "DO $$ "
                    . "BEGIN "
                    . "IF NOT EXISTS ( "
					. "SELECT 1 "
					. "FROM   pg_class c "
					. "JOIN   pg_namespace n ON n.oid = c.relnamespace "
					. "WHERE  c.relname = '$indexName' "
					. "AND    n.nspname = 'public' "
					. ") THEN "
                    . "CREATE INDEX $indexName ON {$arg['table']} USING BTREE($btree); "
                    . "END IF; "
                    . "END$$;";

        }

        $schema = '';

        if ($schema_name != '')
            $schema = "DO $$ "
                    . "BEGIN "
                    . "IF NOT EXISTS ( "
					. "SELECT 1 "
					. "FROM   pg_namespace n "
					. "WHERE  n.nspname = '$schema_name' "
					. ") THEN "
                    . "CREATE SCHEMA $schema_name; "
                    . "END IF; "
                    . "END$$;";

        $create = "CREATE TABLE IF NOT EXISTS {$arg['table']} ($fields $constraints) WITH (OIDS=FALSE);";

        $comment = "COMMENT ON TABLE {$arg['table']} IS '{$arg['model']} ($ts)';";

        return $schema . $create . $comment . $indexes;

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

                    $fields .= (($fields != '') ? ", " : "") . "\"{$model->dbField($key)}\"";
                    $values .= (($values != '') ? ", " : "") . $this->escape((isset($value['value'])) ? $value['value'] : null);

                }

            $regs .= (($regs != '') ? ", " : "") . "($values)";

        }

        $extra = ($arg['field_id'] != '') ? "RETURNING (\"{$arg['field_id']}\")" : "";

        return "INSERT INTO {$arg['table']} ($fields) VALUES $regs $extra;";

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
                        $changes .= (($changes != '') ? ", " : "") . "{$model->dbField($key)}=" . $this->escape($value['value']);
                    else if ($key != '_insert')
                        $changes .= (($changes != '') ? ", " : "") . "{$model->dbField($key)}=" . $this->escape(null);

        if ($changes != '')
            return "UPDATE {$arg['table']} SET $changes $where;";

        return false;

    }

    public function delete($arg, Config $config, Auth $auth, Model $model): string
    {

        $where = '';
        foreach ($arg['where'] as $k)
            $where .= (($where == '') ? " WHERE " : " AND ") . $this->filter($k, $arg['database'], $config, $auth, $model);

        return "DELETE FROM {$arg['table']} $where;";

    }

    public function error(Exception $e): never
    {

        Application::error($e->getMessage(), 500);

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

}
