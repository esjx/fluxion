<?php
namespace Fluxion;

use Fluxion\Auth\Auth;
use Fluxion\Auth\Models\Permission;

class ModelManipulate2
{

    public static function delete($arg, Config $config, Auth $auth) {

        try {

            $modelName = $arg['class'];
            $model = Model::createFromName($modelName, $config, $auth);
            $loaded = false;

            $dbId = $model->getDatabase();

            $connector = $config->getConnectorById($dbId);

            $obj = $connector->getPDO();

            foreach ($model->getFields() as $key=>$value)
                if ($value['type'] == 'upload') {

                    if (!$loaded) {

                        $loaded = true;

                        $arrModel = self::select($arg, $config, $auth);

                        if (count($arrModel) > 0)
                            $model = $arrModel[0];

                    }

                    $arr = explode('\\', $modelName);
                    $size = count($arr);
                    $p1 = '';
                    $p2 = '';

                    for ($i = 0; $i < $size; $i++) {

                        if ($i == ($size - 1)) {

                            $p2 = $arr[$i];

                        } else {

                            if ($i > 0)
                                $p1 .= '\\';

                            $p1 .= $arr[$i];

                        }

                    }

                    $dir = 'uploads/';
                    $dir .= strtolower(str_replace('\\', '-', $p1));
                    $dir .= '/' . Application::classToStr($p2) . '/';

                    Upload::delete($model->$key, $dir);

                }

            $sql = $connector->delete($arg, $config, $auth, $model);

            Application::trackerSql($sql);

            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            $detail = $backtrace[0]['file'] . ':' . $backtrace[0]['line'];

            $detail = str_replace(dirname(__DIR__, 2), '', $detail);

            $login = $auth->getUser()->login ?? '??';

            $detail = "/* $login | $detail */" . PHP_EOL;

            $obj->exec("$detail $sql");

            return true;

        } catch (\PDOException $e) {

            if ($obj->inTransaction())
                $obj->rollBack();

            $connector->error($e);

        }

        return false;

    }

    public static function getClassVars($class)
    {
        return get_class_vars($class);
    }

    /**
     * @param $arg
     * @param Config $config
     * @param Auth $auth
     * @return array|bool
     */
    public static function select($arg, Config $config, Auth $auth) {

        try {

            if ($arg['class'] == 'Fluxion\MnModel')
                $row = new MnModel('', '', false, $config, $auth);
            elseif ($arg['class'] == 'Fluxion\MnChoicesModel')
                $row = new MnChoicesModel('', '', $config, $auth);
            else
                $row = Model::createFromName($arg['class'], $config, $auth);

            $dbId = $row->getDatabase();

            $connector = $config->getConnectorById($dbId);

            $obj = $connector->getPDO();

            if ($arg['fields'] == '*') {

                $arrFiels = [];
                foreach ($row->getFields() as $k=>$v)
                    if (!isset($v['many_to_many']) && !isset($v['i_many_to_many']) && !isset($v['many_choices']))
                        $arrFiels[] = $v['db_field'];

                $arg['fields'] = '[' . implode('], [', $arrFiels) . ']';

            }

            $sql = $connector->select($arg, $config, $auth, $row) . ';';
            Application::trackerSql($sql);

            $out = array();

            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            $detail = $backtrace[0]['file'] . ':' . $backtrace[0]['line'];

            $detail = str_replace(dirname(__DIR__, 2), '', $detail);

            $login = $auth->getUser()->login ?? '??';

            $detail = "/* $login | $detail */" . PHP_EOL;

            $stmt = $obj->query("$detail $sql");

            while ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                if ($arg['class'] == 'Fluxion\MnModel')
                    $row = new MnModel('', '', false, $config, $auth);
                elseif ($arg['class'] == 'Fluxion\MnChoicesModel')
                    $row = new MnChoicesModel('', '', $config, $auth);
                else
                    $row = Model::createFromName($arg['class'], $config, $auth);

                foreach ($result as $key=>$value)
                    if (!is_null($value))
                        $row->{$row->localField($key)} = (isset($row->_fields[$key]) && $row->_fields[$key]['type'] == 'upload') ? json_decode($value) : $value;

                $row->loadToFields();
                $row->setSaved(true);
                $row->setLoaded(true);
                $row->clearChangeds();

                array_push($out, $row);

            }

            $stmt = null;

            return $out;

        } catch (\PDOException $e) {

            if (isset($obj) && $obj->inTransaction())
                $obj->rollBack();

            $connector->error($e);

        }

        return false;

    }

    public static function sql($arg, Config $config, Auth $auth) {

        if ($arg['class'] == 'Fluxion\MnModel')
            $row = new MnModel('', '', false, $config, $auth);
        elseif ($arg['class'] == 'Fluxion\MnChoicesModel')
            $row = new MnChoicesModel('', '', $config, $auth);
        else
            $row = Model::createFromName($arg['class'], $config, $auth);

        $dbId = $row->getDatabase();

        $connector = $config->getConnectorById($dbId);

        if ($arg['fields'] == '*') {

            $arrFiels = [];
            foreach ($row->getFields() as $k=>$v)
                if (!isset($v['many_to_many']) && !isset($v['i_many_to_many']) && !isset($v['many_choices']))
                    $arrFiels[] = $v['db_field'];

            $arg['fields'] = '[' . implode('], [', $arrFiels) . ']';

        }

        return $connector->select($arg, $config, $auth, $row) . ';';

    }

    /**
     * @param $arg
     * @param Config $config
     * @param Auth $auth
     * @return \Generator
     */
    public static function xselect($arg, Config $config, Auth $auth) {

        try {

            if ($arg['class'] == 'Fluxion\MnModel')
                $row = new MnModel('', '', false, $config, $auth);
            elseif ($arg['class'] == 'Fluxion\MnChoicesModel')
                $row = new MnChoicesModel('', '', $config, $auth);
            else
                $row = Model::createFromName($arg['class'], $config, $auth);

            $dbId = $row->getDatabase();

            $connector = $config->getConnectorById($dbId);

            $obj = $connector->getPDO();

            if ($arg['fields'] == '*') {

                $arrFiels = [];
                foreach ($row->getFields() as $k=>$v)
                    if (!isset($v['many_to_many']) && !isset($v['i_many_to_many']) && !isset($v['many_choices']))
                        $arrFiels[] = $v['db_field'];

                $arg['fields'] = '[' . implode('], [', $arrFiels) . ']';

            }

            $sql = $connector->select($arg, $config, $auth, $row) . ';';
            Application::trackerSql($sql);

            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            $detail = $backtrace[0]['file'] . ':' . $backtrace[0]['line'];

            $detail = str_replace(dirname(__DIR__, 2), '', $detail);

            $login = $auth->getUser()->login ?? '??';

            $detail = "/* $login | $detail */" . PHP_EOL;

            $stmt = $obj->query("$detail $sql");

            while ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                if ($arg['class'] == 'Fluxion\MnModel')
                    $row = new MnModel('', '', false, $config, $auth);
                elseif ($arg['class'] == 'Fluxion\MnChoicesModel')
                    $row = new MnChoicesModel('', '', $config, $auth);
                else
                    $row = Model::createFromName($arg['class'], $config, $auth);

                foreach ($result as $key=>$value)
                    if (!is_null($value))
                        $row->{$row->localField($key)} = (isset($row->_fields[$key]) && $row->_fields[$key]['type'] == 'upload') ? json_decode($value) : $value;

                $row->loadToFields();
                $row->clearChangeds();
                $row->setSaved(true);
                $row->setLoaded(true);

                yield  $row;

            }

            $stmt = null;

        } catch (\PDOException $e) {

            if (isset($obj) && $obj->inTransaction())
                $obj->rollBack();

            $connector->error($e);

        }

    }

    /**
     * @param string $model
     * @param bool $drop
     * @param Config $config
     * @param Auth $auth
     * @return bool
     */
    public static function sync($model, Config $config, Auth $auth) {

        $drop = false;

        try {

            /** @var Model2 $class_model */
            $class_model = new $model;

            echo "<b style='color: orange;'>/* $model */</b>\n\n";

            //$dbId = $class_model->getDatabase();

            $connector = $config->getConnectorById(0);

            $obj = $connector->getPDO();

            // Criar as Permissões
            /*if ($model != 'Fluxion\Auth\Models\Permission' && $model != 'Fluxion\Auth\Models\PermissionGroup') {

                $perm = Permission::filter('name', $model)->firstOrNew($config, $auth);
                $perm->name = $model;
                $perm->save();

            }*/

            /*if ($class_model->_view) {

                if ($class_model->_view_script != '') {

                    $obj->beginTransaction();

                    if (is_array($class_model->_view_script)) {

                        foreach ($class_model->_view_script as $sql) {

                            $obj->exec($sql);

                        }

                    } else {

                        $obj->exec($class_model->_view_script);

                    }
                    $obj->commit();

                }

                return false;
            }*/

            $class_model->changeState(Model::STATE_SYNC);

            // Criar a tabela principal
            $connector->create2([
                'model' => get_class($class_model),
                'table' => $class_model->getTable(),
                'fields' => $class_model->getFields(),
                'field_id' => '',//$class_model->getFieldId(),
                'field_id_ai' => false,//$class_model->getFieldIdAi(),
                'database' => 0,//$class_model->getDatabase(),
                'indexes' => [],//$class_model->getIndexes(),
            ]);
            //Application::trackerSql($sql);

            echo "\n\n";

            return true;

            $obj->exec($sql);

            // Criar as tabelas M:N
            foreach ($class_model->getFields() as $key=>$value) {

                if (isset($value['many_to_many'])) {

                    $class_model_MN = new $value['many_to_many'];

                    $fake = $value['many_to_many_fake'] ?? false;

                    if ($drop) {
                        $sql = $connector->drop(array(
                            'table' => $class_model->getTable() . '_has_' . $key,
                        ));
                        Application::trackerSql($sql);
                        $obj->exec($sql);
                    }

                    $sql = $connector->create(array(
                        'model' => get_class($class_model) . " MN[$key]",
                        'table' => $class_model->getTable() . '_has_' . $key,
                        'fields' => array(
                            'a' => array(
                                'type' => /*($class_model->_field_id == 'id')
                                    ? 'integer'
                                    : */ $class_model->_fields[$class_model->_field_id]['type'],
                                'maxlength' =>
                                    //($class_model->_field_id == 'id')
                                    //    ? 255
                                    //    : (
                                    (isset($class_model->_fields[$class_model->_field_id]['maxlength']))
                                        ? $class_model->_fields[$class_model->_field_id]['maxlength']
                                        : 255,
                                    //),
                                'required' => true,
                                'primary_key' => true,
                                'cascade' => true,
                                'foreign_key' => get_class($class_model),
                            ),
                            'b' => array(
                                'type' => /*($class_model_MN->_field_id == 'id')
                                    ? 'integer'
                                    : */ $class_model_MN->_fields[$class_model_MN->_field_id]['type'],
                                'maxlength' =>
                                    ($class_model_MN->_field_id == 'id')
                                        ? 255
                                        : (
                                    (isset($class_model_MN->_fields[$class_model_MN->_field_id]['maxlength']))
                                        ? $class_model_MN->_fields[$class_model_MN->_field_id]['maxlength']
                                        : 255
                                    ),
                                'required' => true,
                                'primary_key' => true,
                                'foreign_key' => ($fake) ? null : $value['many_to_many'],
                            ),
                        ),
                        'field_id' => '',
                        'field_id_ai' => false,
                        'indexes' => array(
                            array('a', 'b'),
                        ),
                    ), $class_model_MN);
                    Application::trackerSql($sql);
                    $obj->exec($sql);

                }

                if (isset($value['many_choices'])) {

                    if ($drop) {
                        $sql = $connector->drop(array(
                            'table' => $class_model->getTable() . '_has_' . $key,
                        ));
                        Application::trackerSql($sql);
                        $obj->exec($sql);
                    }

                    $sql = $connector->create(array(
                        'model' => get_class($class_model) . " MN[$key]",
                        'table' => $class_model->getTable() . '_has_' . $key,
                        'fields' => array(
                            'a' => array(
                                'type' => $class_model->_fields[$class_model->_field_id]['type'],
                                'maxlength' =>
                                    ($class_model->_field_id == 'id')
                                        ? 255
                                        : (
                                    (isset($class_model->_fields[$class_model->_field_id]['maxlength']))
                                        ? $class_model->_fields[$class_model->_field_id]['maxlength']
                                        : 255
                                    ),
                                'required' => true,
                                'primary_key' => true,
                                'foreign_key' => get_class($class_model),
                            ),
                            'b' => array(
                                'type' => $value['type'],
                                'maxlength' => ((isset($class_model->_fields[$key]['maxlength']))
                                    ? $class_model->_fields[$key]['maxlength']
                                    : 255),
                                'required' => true,
                                'primary_key' => true,
                            ),
                        ),
                        'field_id' => '',
                        'field_id_ai' => false,
                        'indexes' => array(
                            array('a', 'b'),
                        ),
                    ), $class_model);
                    Application::trackerSql($sql);
                    $obj->exec($sql);

                }

            }

            return true;

        } catch (\PDOException $e) {

            if ($obj->inTransaction())
                $obj->rollBack();

            $connector->error($e);

        }

        return false;

    }

    /**
     * @param Model $class_model
     * @param $saved
     * @param Config $config
     * @param Auth $auth
     * @return Model
     */
    public static function save(Model $class_model, $saved, Config $config, Auth $auth)
    {

        try {

            foreach ($class_model->getFields() as $key=>$value)
                if (!($key == '_insert' || $key == '_update' || !$value['required'] || ($key == $class_model->_field_id && $class_model->_field_id_ai)))
                    //if (is_null($value['value']))
                    if (!isset($value['value']) && is_null($value['default']))
                        Application::error("Campo <b>" . ($value['label'] ?? $key) . "</b> não pode ser nulo.", 206);

            $dbId = $class_model->getDatabase();

            $connector = $config->getConnectorById($dbId);

            $obj = $connector->getPDO();

            $_field_id = $class_model->getFieldId();

            if ($class_model->getLogFields()) {
                $class_model->_update = date($connector::DB_DATETIME_FORMAT);
            }

            $new = true;

            if ($saved) {

                $new = false;

            } elseif (count($class_model->getPrimaryKeys()) > 0) {

                $ret = $class_model::count('*')->limit(1);

                if ($_field_id != '')
                    $ret = $ret->filter($_field_id, $class_model->$_field_id);
                else
                    foreach ($class_model->getFields() as $key=>$value)
                        if (isset($value['primary_key']))
                            $ret = $ret->filter($key, (isset($value['value'])) ? $value['value'] : null);

                if (!(count($class_model->_primary_keys) == 1 && is_null($class_model->{$class_model->_primary_keys[0]}))) {

                    $ret = $ret->select($config, $auth);
                    $new = ($ret[0]->total == 0);

                }

            }

            if ($new) {

                if ($class_model->getLogFields() && is_null($class_model->_insert)) {
                    $class_model->_insert = $class_model->_update;
                }

                $sql = $connector->insert(array(
                    'table' => $class_model->getTable(),
                    'fields' => array($class_model->getFields()),
                    'field_id' => $class_model->getFieldId(),
                    'field_id_ai' => $class_model->getFieldIdAi(),
                    'database' => $class_model->getDatabase(),
                ), $class_model);
                Application::trackerSql($sql);
                $query = $obj->query($sql);

                if ($_field_id != '' && $class_model->getFieldIdAi())
                    $class_model->$_field_id = $connector->lastInsertId($obj, $query, $class_model->dbField($_field_id));

            } else {

                $where = array();

                if ($_field_id != '')
                    $where[] = array('field' => $class_model->_field_id, 'value' => $class_model->$_field_id, 'not' => false);
                else
                    foreach ($class_model->_fields as $key=>$value)
                        if (isset($value['primary_key']))
                            $where[] = array('field' => $key, 'value' => (isset($value['value'])) ? $value['value'] : null, 'not' => false);

                $sql = $connector->update(array(
                    'table' => $class_model->getTable(),
                    'fields' => $class_model->getFields(),
                    'field_id' => $class_model->getFieldId(),
                    'field_id_ai' => $class_model->getFieldIdAi(),
                    'database' => $class_model->getDatabase(),
                    'where' => $where,
                ), $config, $auth, $class_model);

                if ($sql != false) {

                    Application::trackerSql($sql);

                    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

                    $detail = $backtrace[0]['file'] . ':' . $backtrace[0]['line'];

                    $detail = str_replace(dirname(__DIR__, 2), '', $detail);

                    $login = $auth->getUser()->login ?? '??';

                    $detail = "/* $login | $detail */" . PHP_EOL;

                    $obj->exec("$detail $sql");

                }

            }

            if ($_field_id != '') {

                foreach ($class_model->getFields() as $key => $value) {

                    if (isset($value['value']) && (isset($value['many_to_many']) || isset($value['i_many_to_many']))) {
                        $mn = new MnModel(get_class($class_model), $key, isset($value['i_many_to_many']), $config, $auth);
                        $mn->update($class_model->$_field_id, $value['value']);
                    }

                    if (isset($value['value']) && (isset($value['many_choices']))) {
                        $mn = new MnChoicesModel(get_class($class_model), $key, $config, $auth);
                        $mn->update($class_model->$_field_id, $value['value']);
                    }

                }

            }

            return $class_model;

        } catch (\PDOException $e) {

            if ($obj->inTransaction())
                $obj->rollBack();

            $connector->error($e);

        }

        return $class_model;

    }

    /**
     * @param string $sql
     * @param \PDO $conn
     * @return bool
     */
    public static function exec(string $sql, \PDO $conn): bool
    {

        try {

            Application::trackerSql($sql);
            $conn->exec($sql);

            return true;

        } catch (\PDOException $e) {

            if ($conn->inTransaction())
                $conn->rollBack();

            Application::error($e->getMessage());

        }

        return false;

    }

}
