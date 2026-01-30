<?php
namespace Esj\Core;

use Esj\Core\Auth\Auth;

class MnChoicesModel extends Model
{

    protected $_field_id = '';
    protected $_field_id_ai = false;

    protected $_list_B = null;

    protected $_fields = [
        'a' => [
            'type' => 'string',
            'primary_key' => true,
            'default' => null,
        ],
        'b' => [
            'type' => 'string',
            'primary_key' => true,
            'default' => null,
        ],
    ];

    protected $_L = 'a';
    protected $_R = 'b';

    protected $_field = null;

    public function __construct($model, $field, Config $config = null, Auth $auth = null)
    {

        if (is_null($config)) {
            $config = $GLOBALS['CONFIG'];
        }

        if (is_null($auth)) {
            $auth = $GLOBALS['AUTH'];
        }

        if ($field != '') {

            if (is_string($model))
                $model = new $model();

            $this->_field = $field;

            $this->_table = $model->_table . '_has_' . $field;

            $this->_fields['a']['type'] = $model->_fields[$model->_field_id]['type'];
            $this->_fields['b']['type'] = $model->_fields[$field]['type'];

            $this->_database = $model->_database;

        }

        parent::__construct($config, $auth);

    }

    public function load($id): array
    {

        $arr = [];

        foreach ($this->sql($id)->xselect($this->_config, $this->_auth) as $key) {

            $arr[] = (is_numeric($key->{$this->_R}) && $this->_fields[$this->_R]['type'] != 'string')
                ? intval($key->{$this->_R})
                : $key->{$this->_R};

        }

        sort($arr);

        return $arr;

    }

    public function _query(): Query
    {

        return new Query([
            'class' => get_class($this),
            'table' => $this->_table,
            'field_id' => $this->_field_id,
            'database' => $this->_database,
            'mn_field' => $this->_field,
            'mn_model' => $this,
        ], $this);

    }

    public function _filter($field, $value = null): Query
    {

        return $this->_query()->filter($field, $value);

    }

    public function _groupBy($field): Query
    {

        return $this->_query()->groupBy($field);

    }

    public function sql($id): Query
    {

        return $this->_filter($this->_L, $id)->orderBy($this->_R)->groupBy($this->_R);

    }

    public function add($L, $R)
    {

        $connector = $this->_config->getConnectorById($this->_database);

        if (is_null($this->_list_B)) {

            $this->_list_B = $this->load($L);

        }

        if (!in_array($R, $this->_list_B)) {

            $this->_list_B[] = $R;

            ModelManipulate::exec($connector->insert([
                'table' => $this->_table,
                'fields' => [
                    [
                        $this->_L => ['value' => $L],
                        $this->_R => ['value' => $R],
                    ],
                ],
                'field_id' => '',
                'field_id_ai' => false,
            ], $this), $connector->getPDO());

        }

    }

    public function delete($L, $R)
    {

        $connector = $this->_config->getConnectorById($this->_database);

        $this->_list_B = null;

        ModelManipulate::exec($connector->delete([
            'table' => $this->_table,
            'database' => $this->_database,
            'where' => [
                ['field' => $this->_L, 'value' => $L, 'not' => false],
                ['field' => $this->_R, 'value' => $R, 'not' => false],
            ],
        ], $this->_config, $this->_auth, $this), $connector->getPDO());

    }

    public function update($L, array $arrR = [])
    {

        $connector = $this->_config->getConnectorById($this->_database);

        ModelManipulate::exec($connector->delete([
            'table' => $this->_table,
            'database' => $this->_database,
            'where' => [
                ['field' => $this->_L, 'value' => $L, 'not' => false],
            ],
        ], $this->_config, $this->_auth, $this), $connector->getPDO());

        $arr = [];

        foreach (array_unique($arrR) as $R) {

            $arr[] = array(
                $this->_L => array('value' => $L),
                $this->_R => array('value' => $R),
            );

        }

        if (count($arr) > 0) {

            ModelManipulate::exec($connector->insert([
                'table' => $this->_table,
                'fields' => $arr,
                'field_id' => '',
                'field_id_ai' => false,
                'database' => $this->_database,
            ], $this), $connector->getPDO());

        }

    }

}
