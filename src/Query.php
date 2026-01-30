<?php
namespace Esj\Core;

use Esj\Core\Auth\Auth;

/**
 * Query.php
 * 
 * Resumo
 * 
 * @category   
 * @package    
 * @author     Edivan de Souza Junior
 * @copyright  2018 Edivan de Souza Junior
 * @version    1.3 firstOrNew
 * 
 */
class Query {

    public $_sql;
    private $_model;

    function __construct(array $arg, Model $model)
    {

        $this->_model = $model;

        if (!isset($arg['class'])) $arg['class'] = 'Model';
        if (!isset($arg['table'])) $arg['table'] = 'table';
        if (!isset($arg['fields'])) $arg['fields'] = '*';
        if (!isset($arg['field_id'])) $arg['field_id'] = 'id';
        if (!isset($arg['where'])) $arg['where'] = array();
        if (!isset($arg['orderBy'])) $arg['orderBy'] = array();
        if (!isset($arg['groupBy'])) $arg['groupBy'] = array();
        if (!isset($arg['limit'])) $arg['limit'] = array();
        if (!isset($arg['database'])) $arg['database'] = 0;

        $this->_sql = $arg;

    }

    /**
     * @return Query
     */
    public function query() {

        return $this;

    }

    /**
     * @param string|Sql $field
     * @param mixed $value
     * @return Query
     */
    public function filter($field, $value = null) {

        $this->_sql['where'][] = array('field' => $field, 'value' => $value, 'not' => false);

        return $this;

    }

    /**
     * @param string|Sql $field
     * @param mixed $value
     * @param bool $if
     * @return Query
     */
    public function filterIf($field, $value = null, $if = true) {

        if ($if)
            $this->_sql['where'][] = array('field' => $field, 'value' => $value, 'not' => false);

        return $this;

    }

    public function exclude(string $field, $value): self
    {

        $this->_sql['where'][] = array('field' => $field, 'value' => $value, 'not' => true);

        return $this;

    }

    /**
     * @param string $field
     * @param mixed $value
     * @param bool $if
     * @return Query
     */
    public function excludeIf($field, $value = null, $if = true) {

        if ($if)
            $this->_sql['where'][] = array('field' => $field, 'value' => $value, 'not' => true);

        return $this;

    }

    /**
     * @param string|array $field
     * @param string $order
     * @return Query
     */
    public function orderBy($field, $order = 'ASC') {

        if (is_array($field)) {

            $this->_sql['orderBy'][] = array('field' => $field[0], 'order' => $field[1]);

        } else {

            $this->_sql['orderBy'][] = array('field' => $field, 'order' => $order);

        }

        return $this;

    }

    /**
     * @param string $field
     * @return Query
     */
    public function groupBy($field, $only = true) {

        if ($only) {
            $this->only($field);
        }

        $this->_sql['groupBy'][] = array('field' => $field,);

        return $this;

    }

    /**
     * @param string $field
     * @param bool $if
     * @return Query
     */
    public function groupByIf($field, $if = true) {

        if ($if)
            $this->_sql['groupBy'][] = array('field' => $field,);

        return $this;

    }

    /**
     * @param int $limit
     * @param int $offset
     * @return Query
     */
    public function limit($limit, $offset = 0) {

        $this->_sql['limit'] = array('limit' => $limit, 'offset' => max(0, $offset));

        return $this;

    }

    public function paginate(&$page, &$pages, $quant, $config, $auth): Query
    {

        $query_total = clone $this;

        $total = $query_total->clearOrderBy()->count('*')->firstOrNew($config, $auth)->total;

        $pages = ceil($total / $quant);

        $page = min($page, $pages);

        $offset = ($page - 1) * $quant;

        return $this->limit($quant, $offset);

    }

    public function xpaginate(int &$page, int &$pages, int $quant, Config $config, Auth $auth): \Generator
    {

        $query_total = clone $this;

        $total = $query_total->clearOrderBy()->count('*')->firstOrNew($config, $auth)->total;

        $pages = ceil($total / $quant);

        $page = max(1, min($page, $pages));

        $offset = ($page - 1) * $quant;

        return $this->limit($quant, $offset)->xselect($config, $auth);

    }

    /**
     * @param string $field
     * @return Query
     */
    public function only($field) {

        if ($this->_sql['fields'] == '*')
            $this->_sql['fields'] = '';
        else
            $this->_sql['fields'] .= ', ';

        $this->_sql['fields'] .= '[' . $this->_model->dbField($field) . ']';

        return $this;

    }

    public function clear() {

        $this->_sql['fields'] = '*';

        return $this;

    }

    public function clearOrderBy() {

        $this->_sql['orderBy'] = [];

        return $this;

    }

    /**
     * @param string $field
     * @param string $name
     * @return Query
     */
    public function count($field = '*', $name = 'total') {

        if ($this->_sql['fields'] == '*')
            $this->_sql['fields'] = '';
        else
            $this->_sql['fields'] .= ', ';

        $this->_sql['fields'] .= "COUNT({$this->_model->dbField($field)}) AS [$name]";

        return $this;

    }

    /**
     * @param string $field
     * @param string $name
     * @return Query
     */
    public function sum($field, $name = 'total') {

        if ($this->_sql['fields'] == '*')
            $this->_sql['fields'] = '';
        else
            $this->_sql['fields'] .= ', ';

        $this->_sql['fields'] .= "SUM([{$this->_model->dbField($field)}]) AS [$name]";

        return $this;

    }

    /**
     * @param string $field
     * @param string $name
     * @return Query
     */
    public function avg($field, $name = 'total') {

        if ($this->_sql['fields'] == '*')
            $this->_sql['fields'] = '';
        else
            $this->_sql['fields'] .= ', ';

        $this->_sql['fields'] .= "AVG([{$this->_model->dbField($field)}]) AS [{$this->_model->dbField($name)}]";

        return $this;

    }

    public function min($field, $name = ''): self
    {

        if ($name == '')
            $name = $field;

        if ($this->_sql['fields'] == '*')
            $this->_sql['fields'] = '';
        else
            $this->_sql['fields'] .= ', ';

        $this->_sql['fields'] .= "MIN([{$this->_model->dbField($field)}]) AS [{$this->_model->dbField($name)}]";

        return $this;

    }

    public function max($field, $name = ''): self
    {

        if ($name == '')
            $name = $field;

        if ($this->_sql['fields'] == '*')
            $this->_sql['fields'] = '';
        else
            $this->_sql['fields'] .= ', ';

        $this->_sql['fields'] .= "MAX([{$this->_model->dbField($field)}]) AS [{$this->_model->dbField($name)}]";

        return $this;

    }

    /**
     * @param Config $config
     * @param Auth $auth
     * @param string $ret
     * @param int|string|bool $default
     * @return false|array
     */
    public function select(Config $config, Auth $auth, $ret = null, $default = false) {

        if (!is_null($ret)) {

            $this->limit(1);

            if ($this->_sql['fields'] == '*')
                $arr = ModelManipulate::select($this->only($ret)->_sql, $config, $auth);
            else
                $arr = ModelManipulate::select($this->_sql, $config, $auth);

            if (isset($arr[0]))
                return $arr[0]->$ret;

            return $default;

        }

        return ModelManipulate::select($this->_sql, $config, $auth);

    }

    /**
     * @param Config $config
     * @param Auth $auth
     * @return \Generator
     */
    public function xselect(Config $config = null, Auth $auth = null) {

        if (is_null($config)) {
            $config = $GLOBALS['CONFIG'];
        }

        if (is_null($auth)) {
            $auth = $GLOBALS['AUTH'];
        }

        return ModelManipulate::xselect($this->_sql, $config, $auth);

    }

    /**
     * @param Config $config
     * @param Auth $auth
     * @return mixed
     */
    public function first(Config $config = null, Auth $auth = null) {

        if (is_null($config)) {
            $config = $GLOBALS['CONFIG'];
        }

        if (is_null($auth)) {
            $auth = $GLOBALS['AUTH'];
        }

        if (!isset($this->_sql['limit']['limit']))
            $this->limit(1);

        $arr = ModelManipulate::select($this->_sql, $config, $auth);

        if (isset($arr[0]))
            return $arr[0];

        return null;

    }

    /**
     * @param Config $config
     * @param Auth $auth
     * @return Model
     */
    public function firstOrNew(Config $config = null, Auth $auth = null) {

        if (is_null($config)) {
            $config = $GLOBALS['CONFIG'];
        }

        if (is_null($auth)) {
            $auth = $GLOBALS['AUTH'];
        }

        $model = $this->first($config, $auth);

        if (is_null($model))
            $model = new $this->_sql['class']($config, $auth);

        return $model;

    }

    public function sql(Config $config = null, Auth $auth = null) {

        if (is_null($config)) {
            $config = $GLOBALS['CONFIG'];
        }

        if (is_null($auth)) {
            $auth = $GLOBALS['AUTH'];
        }

        return ModelManipulate::sql($this->_sql, $config, $auth);

    }

    /**
     * @param Config $config
     * @param Auth $auth
     * @return array
     */
    public function toArray(Config $config = null, Auth $auth = null) {

        if (is_null($config)) {
            $config = $GLOBALS['CONFIG'];
        }

        if (is_null($auth)) {
            $auth = $GLOBALS['AUTH'];
        }

        $out = array();

        foreach (ModelManipulate::xselect($this->_sql, $config, $auth) as $key)
            array_push($out, $key->{$this->_model->localField(preg_replace('/^\[/', '', preg_replace('/\]$/', '', $this->_sql['fields'])))});

        return $out;

    }

    /**
     * @param Config $config
     * @param Auth $auth
     * @return bool
     */
    public function delete(Config $config = null, Auth $auth = null)
    {

        if (is_null($config)) {
            $config = $GLOBALS['CONFIG'];
        }

        if (is_null($auth)) {
            $auth = $GLOBALS['AUTH'];
        }

        return ModelManipulate::delete($this->_sql, $config, $auth);
    }

}
