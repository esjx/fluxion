<?php
namespace Fluxion\Connector;

use Fluxion\AuthOld;
use Fluxion\Config;
use Fluxion\Connector;
use Fluxion\Model;
use Fluxion\ModelOld;
use PDO;
use PDOStatement;

class Sqlite extends Connector
{

    public function lastInsertId(PDOStatement $query, string $field_id)
    {

        $qry = $this->_pdo->query('SELECT last_insert_rowid() AS id;');

        $ret = $qry->fetch(PDO::FETCH_ASSOC);

        return $ret['id'];

    }

    public function select($arg, Config $config, AuthOld $auth, ModelOld $model): string
    {

        $arg['table_2'] = str_replace('.', '_', $arg['table']);

        $where = '';
        foreach ($arg['where'] as $k)
            $where .= (($where == '') ? " WHERE " : " AND ") . Connector::filter($k, $arg['database'], $config, $auth, $model);

        $orderBy = '';
        foreach ($arg['orderBy'] as $k)
            $orderBy .= (($orderBy == '') ? " ORDER BY" : ",") . " {$k['field']} {$k['order']}";

        $limit = (isset($arg['limit']['start'])) ?
            " LIMIT {$arg['limit']['start']} OFFSET {$arg['limit']['end']}" :
            '';

        return "SELECT {$arg['fields']} FROM {$arg['table_2']}{$where}{$orderBy}{$limit}";

    }

    public function drop($arg): string
    {

        $arg['table_2'] = str_replace('.', '_', $arg['table']);

        return "DROP TABLE IF EXISTS {$arg['table_2']};";

    }

    public function create($arg, ModelOld|Model $model): string
    {

        $fields = '';
        $constraints = '';
        $indexes = '';

        $arg['table_2'] = str_replace('.', '_', $arg['table']);

        if ($arg['field_id'] == 'id')
            if ($arg['field_id_ai'])
                $fields .= "\"{$arg['field_id']}\" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL";
            else
                $fields .= "\"{$arg['field_id']}\" INTEGER PRIMARY KEY NOT NULL";

        $pk = 0;
        foreach ($arg['fields'] as $value)
            if (isset($value['primary_key']))
                if ($value['primary_key'])
                    $pk++;

        foreach ($arg['fields'] as $key=>$value) {

            if (!isset($value['many_to_many']) && !isset($value['i_many_to_many']) && !isset($value['many_choices']) && $key != 'id') {

                if (!isset($value['required'])) $value['required'] = false;
                if (!isset($value['maxlength'])) $value['maxlength'] = 255;

                if ($fields != '') $fields .= ",\n ";

                switch($value['type']) {

                    case 'string':
                    case 'password': $type = "TEXT"; break;
                    case 'integer':
                    case 'boolean': $type = 'INTEGER'; break;
                    case 'date':
                    case 'datetime':
                    case 'text': $type = 'TEXT'; break;
                    case 'float': $type = 'REAL'; break;

                    default: $type = $value['type'];

                }

                $fields .= "\"$key\" $type";

                if (isset($value['primary_key']))
                    if ($value['primary_key'] && $pk <= 1)
                        $fields .= ' PRIMARY KEY';

                if ($value['required']) $fields .= ' NOT NULL';

                if (isset($value['foreign_key'])) {

                    $foreignkey_model = new $value['foreign_key'];
                    $foreignkey_table = str_replace('.', '_', $foreignkey_model->_table);
                    $foreignkey_field_id = $foreignkey_model->_field_id;
                    $foreignkey_type = ($value['required']) ? 'CASCADE' : 'SET NULL';

                    $constraints .= ",\n FOREIGN KEY (\"$key\") "
                        . "REFERENCES $foreignkey_table ($foreignkey_field_id) "
                        . "ON UPDATE $foreignkey_type ON DELETE $foreignkey_type ";

                }

            }

        }

        foreach ($arg['indexes'] as $index) {

            $indexName = "{$arg['table_2']}_index";
            $btree = '';

            foreach ($index as $fld) {

                $indexName .= "_$fld";

                if ($btree != '')
                    $btree .= ", ";

                $btree .=  "\"$fld\"";

            }

            $indexes .= "CREATE INDEX $indexName ON {$arg['table_2']}($btree); ";

        }

        return "CREATE TABLE {$arg['table_2']} (\n {$fields}{$constraints} \n);\n$indexes";

    }

    public function insert($arg, ModelOld $model, $force_fields = false): string
    {

        $arg['table_2'] = str_replace('.', '_', $arg['table']);

        $fields = '';
        $regs = '';

        foreach ($arg['fields'] as $i) {

            $fields = '';
            $values = '';

            foreach ($i as $key => $value)
                if (!isset($value['many_to_many']) && !isset($value['i_many_to_many']) && !isset($value['many_choices'])  && isset($value['value'])) {

                    $fields .= (($fields != '') ? ", " : "") . "\"$key\"";
                    $values .= (($values != '') ? ", " : "") . Connector::escape($value['value']);

                }

            $regs .= (($regs != '') ? ", " : "") . "($values)";

        }

        return "INSERT INTO {$arg['table_2']} ($fields) VALUES $regs ;";

    }

    public function update($arg, Config $config, AuthOld $auth, ModelOld $model): string
    {

        $arg['table_2'] = str_replace('.', '_', $arg['table']);

        $changes = '';

        $where = '';
        foreach ($arg['where'] as $k)
            $where .= (($where == '') ? " WHERE " : " AND ") . Connector::filter($k, $arg['database'], $config, $auth, $model);

        foreach ($arg['fields'] as $key=>$value)
            if (!isset($value['many_to_many']) && !isset($value['i_many_to_many']) && !isset($value['many_choices']) && $key != $arg['field_id'])
                if (isset($value['value']))
                    $changes .= (($changes != '') ? ", " : "") . "\"$key\"=" . Connector::escape($value['value']);

        return "UPDATE {$arg['table_2']} SET $changes {$where};";

    }

    public function delete($arg, Config $config, AuthOld $auth, ModelOld $model): string
    {

        $arg['table_2'] = str_replace('.', '_', $arg['table']);

        $where = '';
        foreach ($arg['where'] as $k)
            $where .= (($where == '') ? " WHERE " : " AND ") . Connector::filter($k, $arg['database'], $config, $auth, $model);

        return "DELETE FROM {$arg['table_2']}{$where};";

    }

}
