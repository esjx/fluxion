<?php
namespace Esj\Core;

use stdClass;
use Exception;
use DateTime;
use Esj\Core\Auth\Auth;
use Esj\Core\Mask\Mask;
use Esj\Core\Connector\Connector;

abstract class Model {

    protected int $create_order = -1;

    function getCreateOrder(): int
    {
        return $this->create_order;

    }

    function setCreateOrder(int $create_order)
    {
        $this->create_order = $create_order;
    }

    const STATE_VIEW = 0;
    const STATE_EDIT = 1;
    const STATE_EXCEL = 2;
    const STATE_FIELDS = 3;
    const STATE_SYNC = 4;
    const STATE_BEFORE_SAVE = 15;
    const STATE_SAVE = 5;
    const STATE_FILTER = 6;
    const STATE_FILTER_PARAMS = 7;
    const STATE_INLINE = 8;
    const STATE_INLINE_SAVE = 10;
    const STATE_TYPEAHEAD = 9;

    const NOW = '#NOW#';

    const COLORS = [
        'yellow' => 'Amarelo',
        'amber' => 'Âmbar',
        'indigo' => 'Anil',
        'blue' => 'Azul',
        'light-blue' => 'Azul Claro',
        //'white' => 'Branco',
        'cyan' => 'Ciano',
        //'grey' => 'Cinza',
        //'blue-grey' => 'Cinza Azulado',
        'orange' => 'Laranja',
        'deep-orange' => 'Laranja Escuro',
        'lime' => 'Lima',
        'brown' => 'Marrom',
        'black' => 'Preto',
        'pink' => 'Rosa',
        'purple' => 'Roxo',
        //'deep-purple' => 'Roxo Escuro',
        'green' => 'Verde',
        'teal' => 'Verde Azulado',
        'light-green' => 'Verde Claro',
        'red' => 'Vermelho',
    ];

    const COLOR_MAP = [
        'red' => 'FF6B68',
        'pink' => 'ff85af',
        'purple' => 'd066e2',
        'deep-purple' => '673AB7',
        'indigo' => '3F51B5',
        'blue' => '2196F3',
        'light-blue' => '03A9F4',
        'cyan' => '00BCD4',
        'teal' => '39bbb0',
        'green' => '32c787',
        'light-green' => '8BC34A',
        'lime' => 'CDDC39',
        'yellow' => 'FFEB3B',
        'amber' => 'ffc721',
        'orange' => 'FF9800',
        'deep-orange' => 'FF5722',
        'brown' => '795548',
        'grey' => '9E9E9E',
        'gray' => '9E9E9E',
        'blue-grey' => '607D8B',
        'black' => '000000',
    ];

    const YES_NO = [
        1 => 'Sim',
        2 => 'Não',
    ];

    const YES_NO_NA = [
        1 => 'Sim',
        2 => 'Não',
        9 => 'Não se aplica',
    ];

	protected $_table = '';
	protected $_primary_keys = [];

	protected $_log_fields = true;

	protected $_view = false;
	protected $_view_script = '';
	protected $_view_dependencies = [];

    protected $_fields = [];
    protected $_local_fields = [];

    protected $_pre_load = false;

    public function preLoad(): bool
    {
        return $this->_pre_load;
    }

    protected $_auto_create = [];
    protected $_auto_insert = [];

    protected $_saved = false;
    protected $_loaded = false;
    protected $_changed = false;

    protected $_filters = [];

    protected $_linked_filters = false;

    protected $_field_id = 'id';
    protected $_field_id_ai = true;

    protected $_order = [];

    protected $_indexes = [];

    protected $_database = 0;

    protected $_html_title = '';
    protected $_verbose_name = '';
    protected $_verbose_name_plural = '';

    protected $_create_permission = true;
    protected $_auto_sync = true;
    protected $_save_time = true;

    protected $_edit_template = 'apps/_home/template/edit-popup.html';

    //protected $total = null;
    protected $_total = null;

    protected $_form_size = 'modal-md';

    protected $_config;
    protected $_auth;

    protected $_internal_vars = array(
        '_table', '_view', '_fields', '_field_id', '_field_id_ai',
        '_indexes', '_verbose_name', '_verbose_name_plural',
        '_primary_keys', '_order', '_filters', 'total', '_total',
        '_database', '_saved', '_loaded', '_changed',
        '_create_permission', '_auto_sync', '_save_time', '_form_size',
        '_edit_template', '_config', '_auth', '_view_script', '_view_dependencies',
        '_inlines'
    );

    protected $_refreh_time = 20 * MINUTOS;

    public function getRefreshTime(): int
    {
        return $this->_refreh_time;
    }

    protected $_default_order = 0;

    protected $_ipp = 20;

    public function isView(): bool
    {
        return $this->_view;
    }

    public function getIpp(): int
    {
        return $this->_ipp;
    }

    public function getDefaultOrder(): int
    {
        return $this->_default_order;
    }

    public function setChanged(bool $changed): void
    {
        $this->_changed = $changed;
    }

    public function getAuth(): ?Auth
    {
        return $this->_auth;
    }

    public function getConfig(): ?Config
    {
        return $this->_config;
    }

    public function setMask(string $key, Mask $mask)
    {

        if (!is_subclass_of($mask, Mask::class)) {
            $nome = get_class($mask);
            Application::error("Classe <b>$nome</b> não é subclasse de <b>Mask</b>!");
        }

        $this->_fields[$key]['mask'] = $mask->mask;
        $this->_fields[$key]['placeholder'] = $mask->placeholder;
        $this->_fields[$key]['pattern'] = $mask->pattern_validator;
        $this->_fields[$key]['label'] = $this->_fields[$key]['label'] ?? $mask->label;

    }

    public function __construct(Config $config = null, Auth $auth = null)
    {

        if (is_null($config)) {
            $config = $GLOBALS['CONFIG'];
        }

        if (is_null($auth)) {
            $auth = $GLOBALS['AUTH'];
        }

        $this->_config = $config;
        $this->_auth = $auth;

        $hora = intval(date('H'));

        if ($hora < 8 || $hora >= 20) {
            $this->_refreh_time = 8 * HORAS;
        }

        foreach (ModelManipulate::getClassVars(get_class($this)) as $field=>$params) {
            $this->_fields[$field] = $params;
            unset($this->$field);
        }

        foreach ($this->_auto_create as $type => $fields) {

            foreach ($fields as $key => $value) {

                $field = $key;

                if (is_numeric($key)) {
                    $field = $value;
                }

                $this->_fields[$field] = [
                    'type' => $type,
                    'db_field' => $value,
                ];

            }

        }

        if ($this->_verbose_name == '')
            $this->_verbose_name = preg_replace('/Model$/i', '', get_class($this));

        if ($this->_verbose_name_plural == '')
            $this->_verbose_name_plural = $this->_verbose_name . 's';

        if ($this->_html_title == '')
            $this->_html_title = $this->_verbose_name . 's';

        if ($this->_field_id == 'id' && !isset($this->_fields['id']))
            $this->_fields = array_merge(array(
                $this->_field_id => array(
                    'type' => 'integer',
                    'protected' => true,
                    'search' => false,
                    'not_log' => true,
                    'label' => '#',
                ),
            ), $this->_fields);

        if ($this->_field_id != '')
            $this->_fields[$this->_field_id]['primary_key'] = true;

        if ($this->_log_fields) {

            $this->_fields = array_merge($this->_fields, array(
                '_insert' => array(
                    'type' => 'datetime',
                    'required' => true,
                    'protected' => true,
                    'readonly' => true,
                    'not_log' => true,
                    'default' => self::NOW,
                ),
                '_update' => array(
                    'type' => 'datetime',
                    'required' => true,
                    'protected' => true,
                    'readonly' => true,
                    'not_log' => true,
                    'default' => self::NOW,
                ),
            ));

        }

        foreach ($this->_fields as $key=>$value) {

            if (isset($this->_fields[$key]['mask_class'])) {

                $nome = $this->_fields[$key]['mask_class'];

                if (!class_exists($nome)) {
                    Application::error("Classe <b>$nome</b> não existe!");
                }

                /** @var Mask $erro */
                $mask = new $nome;

                $this->setMask($key, $mask);

            }

            if ($this->_fields[$key]['type'] == 'date') {
                $this->_fields[$key]['mask'] = '99/99/9999';
                $this->_fields[$key]['placeholder'] = '__/__/____';
            }

            if (!isset($this->_fields[$key]['db_field']))
                $this->_fields[$key]['db_field'] = $key;

            $this->_local_fields[$this->_fields[$key]['db_field']] = $key;

            if (!isset($this->_fields[$key]['value']))
                $this->_fields[$key]['value'] = null;

            if (!isset($this->_fields[$key]['label']))
                $this->_fields[$key]['label'] = ucfirst($key);

            if (!isset($this->_fields[$key]['required']))
                $this->_fields[$key]['required'] = false;

            if (!isset($this->_fields[$key]['protected']))
                $this->_fields[$key]['protected'] = false;

            if (!isset($this->_fields[$key]['readonly']))
                $this->_fields[$key]['readonly'] = false;

            if (!isset($this->_fields[$key]['minlength']))
                $this->_fields[$key]['minlength'] = 0;

            if (!isset($this->_fields[$key]['maxlength']))
                $this->_fields[$key]['maxlength'] = 255;

            if (!isset($this->_fields[$key]['size']))
                $this->_fields[$key]['size'] = 12;

            if (!isset($this->_fields[$key]['mask']))
                $this->_fields[$key]['mask'] = null;

            if (!isset($this->_fields[$key]['min']))
                $this->_fields[$key]['min'] = null;

            if (!isset($this->_fields[$key]['max']))
                $this->_fields[$key]['max'] = null;

            if (!isset($this->_fields[$key]['placeholder']))
                $this->_fields[$key]['placeholder'] = '';

            if (!isset($this->_fields[$key]['default']))
                $this->_fields[$key]['default'] = null;

            if (isset($this->_fields[$key]['primary_key']) && $this->_fields[$key]['primary_key']) {
                $this->_fields[$key]['required'] = true;
                $this->_fields[$key]['readonly'] = true;
                array_push($this->_primary_keys, $key);
            }

            if (!isset($this->_fields[$key]['search']))
                $this->_fields[$key]['search'] = false;

            if (!isset($this->_fields[$key]['filter']))
                $this->_fields[$key]['filter'] = false;

            if (!isset($this->_fields[$key]['changed']))
                $this->_fields[$key]['changed'] = false;

        }

        foreach ($this->_primary_keys as $key) {
            $this->_fields[$key]['primary_key'] = true;
        }

        foreach (ModelManipulate::getClassVars(get_class($this)) as $field=>$params)
            $this->$field = $this->__get($field);

    }

    public function getActionFields($action): array
    {
        return [];
    }

    public function actionSave($action, $data): bool
    {
        return true;
    }

    public function getVerboseName()
    {
        return $this->_verbose_name;
    }

    public function getVerboseNamePlural(): string
    {
        return $this->_verbose_name_plural;
    }

    public function getViewDependencies(): array
    {
        return $this->_view_dependencies;
    }

    public function getFormSize(): string
    {
        return $this->_form_size;
    }

    public function getFormHeader($action): ?string
    {
        return null;
    }

    public function getFormFooter($action): ?string
    {
        return null;
    }

    public function getOrder(): array
    {
        return $this->_order;
    }

    public function getLogFields()
    {
        return $this->_log_fields;
    }

    public function getDatabase(): int
    {
        return $this->_database;
    }

    public function getFields(): array
    {
        return $this->_fields;
    }

    public function getTable(): string
    {
        return $this->_table;
    }

    public function getFieldId(): string
    {
        return $this->_field_id;
    }

    public function getFieldIdAi(): bool
    {
        return $this->_field_id_ai;
    }

    public function getIndexes(): array
    {
        return $this->_indexes;
    }

    public function getPrimaryKeys(): array
    {
        return $this->_primary_keys;
    }

    public function setSaved(bool $saved)
    {
        $this->_saved = $saved;
    }

    public function setLoaded(bool $loaded)
    {
        $this->_loaded = $loaded;
    }

    public function __toString()
    {

        return get_called_class() . ' Object';

    }

    public function chartLabel(): string
    {
        return (string) $this;
    }

    static function getClassName(): string
    {
        return __NAMESPACE__ . '_' . __CLASS__;
    }

    public function __isset($name)
    {

        if (in_array($name, $this->_internal_vars))
            return true;

        if (isset($this->_fields[$name]))
            return true;

        return false;

    }

    public function __get($name)
    {

        if ($name == 'total')
            $name = '_total';

        if (in_array($name, $this->_internal_vars))
            return $this->$name;

        if (isset($this->_fields[$name]))
            if (isset($this->_fields[$name]['value']))
                switch ($this->_fields[$name]['type']) {
                    case 'integer':
                    case 'float':
                    case 'decimal':
                    case 'numeric':
                        return (!isset($this->_fields[$name]['many_to_many']) && !isset($this->_fields[$name]['i_many_to_many']) && !isset($this->_fields[$name]['many_choices'])) ? $this->_fields[$name]['value'] * 1 : $this->_fields[$name]['value'];
                    case 'upload':
                        return json_decode($this->_fields[$name]['value']);
                    case 'boolean':
                        return !!$this->_fields[$name]['value'];
                    default:
                        return $this->_fields[$name]['value'];
                }
            else
                return null;

        if (method_exists($this, $name))
            return $this->$name();

        Application::error("Propriedade, campo ou método <b>$name</b> não encontrado!", 101);

        return null;

    }

    /**
     * @param $name
     * @return bool|string
     */
    public function dbField($name) {

        if ($name == '*' || $name == 'a' || $name == 'b')
            return $name;

        if (strpos($name, 'DISTINCT') !== false) {

            $name = trim(str_ireplace('DISTINCT', '', $name));

            if (isset($this->_fields[$name]))
                return 'DISTINCT ' . $this->_fields[$name]['db_field'];

        }

        if (isset($this->_fields[$name]))
            return $this->_fields[$name]['db_field'];

        Application::error("Propriedade, campo ou método <b>$name</b> não encontrado!", 9999);

    }

    /**
     * @param $name
     * @return bool|string
     */
    public function localField($name) {

        if (isset($this->_local_fields[$name]))
            return $this->_local_fields[$name];

        if ($name == 'total')
            return 'total';

        if (in_array($name, $this->_internal_vars))
            return $this->$name;

        if (isset($this->_fields[$name]))
            return $name;

        Application::error("Propriedade, campo ou método <b>$name</b> não encontrado!", 9999);

    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value) {

        if ($name == 'total')
            $name = '_total';

        if (in_array($name, $this->_internal_vars))
            $this->$name = $value;

        elseif (isset($this->_fields[$name]))
            if (isset($this->_fields[$name]['type'])
                && !isset($this->_fields[$name]['many_to_many'])
                && !isset($this->_fields[$name]['i_many_to_many'])
                && !isset($this->_fields[$name]['many_choices'])) {

                switch ($this->_fields[$name]['type']) {
                    case 'integer':
                        $newValue = (is_null($value) || $value === '') ? null : intval($value);
                        break;
                    case 'float':
                    case 'decimal':
                    case 'numeric':

                        if (preg_match('/^\d+,\d+$/', $value ?? '')) {
                            $newValue = floatval(str_replace(',', '.', $value));
                        } else {
                            $newValue = (is_null($value) || $value === '') ? null : floatval($value);
                        }

                        break;
                    case 'upload':
                        $newValue = json_encode($value);
                        break;
                    case 'boolean':
                        $newValue = is_null($value) ? null : $value == true;
                        break;
                    case 'date':

                        try {

                            $newValue = is_null($value) ? null : ((new DateTime($value))->format('Y-m-d'));

                        } catch (Exception $e) {

                            $newValue = '#ERROR#';

                        }

                        break;
                    case 'datetime':
                        $newValue = is_null($value) ? null : $value;
                        break;
                    default:
                        $newValue = is_null($value) ? null : trim(strval($value));
                }

                if ($this->_fields[$name]['value'] !== $newValue) {
                    $this->_changed = true;
                    $this->_fields[$name]['changed'] = true;
                }

                $this->_fields[$name]['value'] = $newValue;

            } else {

                $this->_changed = true;
                $this->_fields[$name]['value'] = $value;

            }

        elseif (method_exists($this, $name))
            $this->$name($value);

        /*else
            Application::error("Campo ou método <b>$name</b> não encontrado!", 102);*/

    }

    /**
     * @param $name
     * @param Config $config
     * @param Auth $auth
     * @return Model
     */
    public static function createFromName($name, Config $config, Auth $auth)
    {
        return new $name($config, $auth);
    }

    /**
     * @param $field
     * @param bool $list
     * @return array
     */
    public function filterType($field, $list = true) {

        $class = get_called_class();

        $config = $this->_config;
        $auth = $this->_auth;

        $choices = [];
        $mask = '';

        switch ($this->_fields[$field]['type']) {

            case 'boolean':

                $type = 'choices';

                array_push($choices, array('id' => false, 'label' => 'Não'));
                array_push($choices, array('id' => true, 'label' => 'Sim'));

                break;

            case 'integer':
            case 'float':
            case 'decimal':
            case 'numeric':
            case 'date':
            case 'string':

                $mask = $this->_fields[$field]['mask'];

                $type = $this->_fields[$field]['type'];

                if (isset($this->_fields[$field]['choices'])) {

                    $type = 'choices';

                    if ($list) {

                        if (!(isset($this->_fields[$field]['choices'][null])) && $this->filter($field, null)->count('*')->select($config, $auth, 'total') > 0)
                            array_push($choices, array('id' => null, 'label' => '(Em Branco)'));

                        foreach ($this->_fields[$field]['choices'] as $k => $v)
                            array_push($choices, array('id' => ($k === '') ? null : $k, 'label' => $v));

                    }

                }

                if (isset($this->_fields[$field]['foreign_key'])) {

                    $type = 'choices';

                    if ($list) {

                        $m_name = $this->_fields[$field]['foreign_key'];

                        $m = $this::createFromName($m_name, $config, $auth);
                        $m_id = $m->_field_id;

                        foreach ($m->_order as $k)
                            $m = $m->orderBy($k[0], $k[1]);

                        if (($m_name == '\Esj\Core\Auth\Models\CostCenter' || $m_name == 'Esj\Core\Auth\Models\CostCenter') && !$auth->hasPermission($class, 'special'))
                            $m = $m->filter('id', $auth->getCostCentersAccess($class));

                        if ($this->filter($field, null)->count('*')->select($config, $auth, 'total') > 0)
                            array_push($choices, array('id' => null, 'label' => '(Em Branco)'));

                        $m = $m->filter($m_id, $this::only($field)->groupBy($field));

                        $m = $m->xselect($config, $auth);

                        foreach ($m as $k)
                            array_push($choices, array('id' => $k->$m_id, 'label' => strval($k)));

                    }

                }

                break;

            case 'upload':
                $type = 'upload';
                break;

            default:
                $mask = $this->_fields[$field]['mask'];
                $type = 'string';

        }

        return array($type, $choices, $mask);

    }

    public static function query(): Query
    {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->query();

    }

    /**
     * @param string|Sql $field
     * @param mixed $value
     * @return Query
     */
    public static function filter($field, $value = null) {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query([
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ], $obj);

        return $_data->filter($field, $value);

    }

    protected $_default_cache = false;

    public function defaultCache(): bool
    {
        return $this->_default_cache;
    }

    public static function getConnector(): Connector
    {

        /** @var Config $config */
        $config = $GLOBALS['CONFIG'];

        /** @var Auth $auth */
        $auth = $GLOBALS['AUTH'];

        $class = get_called_class();

        /** @var self $obj */
        $obj = new $class($config, $auth);

        return $config->getConnectorById($obj->getDatabase());

    }

    public static function loadById($id, Config $config = null, Auth $auth = null, $cache = null): self
    {

        if (is_null($config)) {
            $config = $GLOBALS['CONFIG'];
        }

        if (is_null($auth)) {
            $auth = $GLOBALS['AUTH'];
        }

        $class = get_called_class();

        /** @var self $obj */
        $obj = new $class($config, $auth);

        if (is_null($cache)) {
            $cache = $obj->defaultCache();
        }

        if (empty($id))
            return $obj;

        $query = new Query([
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ], $obj);

        /*$i = 0;

        $arr = explode(',', $id);

        foreach ($obj->_fields as $key => $value) {

            if ($obj->_field_id == $key || (isset($value['primary_key']) && $value['primary_key'])) {

                $query = $query->filter($key, $arr[$i++]);

            }

        }*/

        $query = $query->filter($obj->_field_id, $id);

        if ($cache) {

            if (!isset($GLOBALS['CACHE_LOAD_BY_ID'])) {
                $GLOBALS['CACHE_LOAD_BY_ID'] = [];
            }

            if (!isset($GLOBALS['CACHE_LOAD_BY_ID'][$class])) {
                $GLOBALS['CACHE_LOAD_BY_ID'][$class] = [];
            }

        }

        if (!$cache || !isset($GLOBALS['CACHE_LOAD_BY_ID'][$class][$id])) {
            $GLOBALS['CACHE_LOAD_BY_ID'][$class][$id] = $query->firstOrNew($config, $auth);
        }

        $GLOBALS['CACHE_LOAD_BY_ID'][$class][$id]->setOriginals();

        return $GLOBALS['CACHE_LOAD_BY_ID'][$class][$id];

    }

    public function setOriginals()
    {

        $this->_originals = [];

        foreach ($this->_fields as $field => $params) {

            if (isset($params['many_choices']) || isset($params['many_to_many'])) {

                $this->_originals[$field] = $this->loadMnItens($field);

            } else {

                $this->_originals[$field] = $this->$field;

            }

        }

    }

    /**
     * @throws Exception
     */
    public function validate()
    {

        $this->_originals = [];

        foreach ($this->_fields as $field => $params) {

            if ($params['required']
                && is_null($this->$field)
                && !in_array($field, ['_insert', '_update'])
                && ($field != $this->_field_id || !$this->_field_id_ai)) {
                throw new Exception("Campo <b>{$params['label']}</b> não preenchido!");
            }

            $this->_originals[$field] = $this->$field;

        }

    }

    /**
     * @param string|Sql $field
     * @param mixed $value
     * @param bool $if
     * @return Query
     */
    public static function filterIf($field, $value = null, $if = true) {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query([
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ], $obj);

        return ($if) ? $_data->filter($field, $value) : $_data->query();

    }

    /**
     * @param string $field
     * @param mixed $value
     * @return Query
     */
    public static function exclude($field, $value = null) {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->exclude($field, $value);

    }

    /**
     * @param string $field
     * @param mixed $value
     * @param bool $if
     * @return Query
     */
    public static function excludeIf($field, $value = null, $if = true) {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query([
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ], $obj);

        return ($if) ? $_data->exclude($field, $value) : $_data->query();

    }

    /**
     * @param string|array $field
     * @param string $order
     * @return Query
     */
    public static function orderBy($field, $order = 'ASC') {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->orderBy($field, $order);

    }

    /**
     * @param string $field
     * @return Query
     */
    public static function groupBy($field) {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->groupBy($field);

    }

    /**
     * @param int $limit
     * @param int $offset
     * @return Query
     */
    public static function limit($limit, $offset = 0) {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->limit($limit, $offset);

    }

    /**
     * @param string $field
     * @return Query
     */
    public static function only($field) {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->only($field);

    }

    /**
     * @param string $field
     * @param string $name
     * @return Query
     */
    public static function count($field = '*', $name = 'total') {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->count($field, $name);

    }

    public static function min($field, $name) {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->min($field, $name);

    }

    public static function sum($field, $name = 'total') {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->sum($field, $name);

    }

    /**
     * @param string $field
     * @param string $name
     * @return Query
     */
    public static function max($field, $name = '') {

        if ($name == '')
            $name = $field;

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->max($field, $name);

    }

    /**
     * @param Config $config
     * @param Auth $auth
     * @return array|bool
     */
    public static function select(Config $config, Auth $auth) {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->select($config, $auth);

    }

    public static function xselect(Config $config, Auth $auth) {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->xselect($config, $auth);

    }

    public static function first(Config $config, Auth $auth): self
    {

        $class = get_called_class();
        $obj = new $class;

        $_data = new Query(array(
            'class' => $class,
            'table' => $obj->_table,
            'field_id' => $obj->_field_id,
            'database' => $obj->_database,
        ), $obj);

        return $_data->first($config, $auth);

    }

    public function changeState($state): void {}

    protected $_originals = [];

    public function loadToFields()
    {

        //$this->_originals = [];

        foreach ($this->_fields as $field => $params) {

            //$this->_originals[$field] = $this->$field;

            $this->__set($field, $this->$field);

        }

    }

    /**
     * @throws Exception
     */
    public function save(): ?self
    {

        $config = $this->_config;
        $auth = $this->_auth;

        try {

            $this->loadToFields();

            $this->changeState(Model::STATE_SAVE);

            if ($this->onSave()) {

                if ($this->_changed) {

                    $ret = ModelManipulate::save($this, $this->_saved, $config, $auth);
                    $this->onSaved();
                    $this->_saved = true;
                    $this->_loaded = true;

                    $this->clearChangeds();

                    return $ret;

                } else {

                    $this->onSaved();

                    return $this;

                }

            } else {

                return null;

            }

        } catch (CustomException $e) {
            throw new CustomException($e->getMessage(), log: $e->getLog());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

    }

    /**
     *
     */
    public function clearChangeds() {

        $this->_changed = false;

        foreach ($this->_fields as $key=>$value)
            $this->_fields[$key]['changed'] = false;

    }

    /**
     * @param Config $config
     * @param Auth $
     * @param $auth
     * @param int $state
     * @return array
     */
    public function toArray(Config $config, Auth $auth, $state = Model::STATE_VIEW) {

        $this->changeState($state);

        $arr = [];
        $arrId = [];

        foreach ($this->_fields as $key=>$value) {

            if (!in_array($key, array('_insert2', '_update2')))
                if (!isset($value['many_to_many']) && !isset($value['i_many_to_many']) && !isset($value['many_choices']) && !$value['protected']) {

                    if (isset($value['choices']) && isset($value['choices'][$value['value']]))
                        $arr[$key] = $value['choices'][$value['value']];
                    elseif (isset($value['foreign_key']) && (isset($value['foreign_key_show']) && $value['foreign_key_show'])) {

                        $nome = get_called_class() . '_FK_' . $value['foreign_key'] . '_' . $key;

                        if (!isset($GLOBALS[$nome])) {

                            $m_name = $value['foreign_key'];
                            $m = new $m_name;
                            $m_arr = [];

                            $field_id = $m->_field_id;

                            foreach ($m->filter($field_id, $this->groupBy($key))->xselect($config, $auth) as $m_ret)
                                $m_arr[$m_ret->$field_id] = strval($m_ret);

                            $GLOBALS[$nome] = $m_arr;

                        }

                        if (is_null($value['value']) || $value['value'] === '')
                            $arr[$key] = null;
                        else
                            $arr[$key] = (isset($GLOBALS[$nome][$value['value']])) ? $GLOBALS[$nome][$value['value']] : $value['value'] . ' - #ERROR#';

                    } elseif ($value['type'] == 'date' || $value['type'] == 'datetime') {
                        $arr[$key] = Util::jsDate($value['value']);
                    } else {
                        $arr[$key] = (isset($value['value'])) ? (($value['type'] == 'upload') ? json_decode($value['value']) : $value['value']) : null;
                    }

                }

            if (isset($value['primary_key']) && $value['primary_key'])
                array_push($arrId, $value['value']);

        }

        $arr['__id__'] = implode(',', $arrId);

        return $arr;

    }

    public function prepare($data): bool {

        foreach ($this->_fields as $key => $value) {

            if (!array_key_exists($key, $data)) {
                continue;
            }

            if ($value['type'] == 'date') {

                try {

                    $this->$key = DateTime::createFromFormat('d/m/Y', $data->$key)->format('Y-m-d');

                } catch (Exception $e) {

                    $this->$key = null;

                    Application::error("Data inválida no campo <b>$key</b>!");

                }

            } elseif ($value['type'] == 'datetime') {

                try {

                    $this->$key = DateTime::createFromFormat('d/m/Y H:i:s', $data->$key)->format('Y-m-d H:i:s');

                } catch (Exception $e) {

                    $this->$key = null;

                    Application::error("Data/Hora inválida no campo <b>$key</b>!");

                }

            } else {

                $this->$key = $data->$key;

            }

        }

        return true;

    }

    /**
     * @throws CustomException
     */
    public function execute(string $sql): void
    {

        $connector = $this->_config->getConnectorById($this->_database);

        try {

            Application::trackerSQL($sql);

            $connector->getPDO()->exec($sql);

        } catch (Exception $e) {

            throw new CustomException($e->getMessage());

        }

    }

    public function getChanges($separator = '<br>'): ?string
    {

        $changes = null;

        foreach ($this->_fields as $field => $params) {

            if ($params['not_log'] ?? false) {
                continue;
            }

            if ($field == $this->getFieldId()) {
                continue;
            }

            $atual = $this->$field;
            $anterior = $this->_originals[$field] ?? null;

            if ($atual === []) $atual = null;
            if ($anterior === []) $anterior = null;

            if ($params['type'] == 'html') {

                if (!is_null($atual)) $atual = trim(preg_replace('/<[^>]*>/', '', $atual));
                if (!is_null($anterior)) $anterior = trim(preg_replace('/<[^>]*>/', '', $anterior));

            }

            if ($atual !== $anterior) {

                if ($changes != '') {
                    $changes .= $separator;
                }

                $_field = $params['label'] ?? $field;
                $_original = $this->changeDetails($params, $anterior);
                $_actual = $this->changeDetails($params, $atual);

                //\u2192
                $changes .= "<small class='text-muted'>$_field</small>: $_original <small class='text-muted'>&rarr;</small> $_actual";

            }

        }

        return $changes;

    }

    public function getChangesDeleted($separator = '<br>'): ?string
    {

        $changes = null;

        foreach ($this->_fields as $field => $params) {

            if ($params['not_log'] ?? false) {
                continue;
            }

            if (!is_null($this->_originals[$field] ?? null)) {

                if ($changes != '') {
                    $changes .= $separator;
                }

                $_field = $params['label'] ?? $field;
                $_original = $this->changeDetails($params, $this->_originals[$field] ?? null);

                $changes .= "<small class='text-muted'>$_field</small>: $_original";

            }

        }

        return $changes;

    }

    public function changeDetails($params, $value): string
    {

        $config = $this->_config;
        $auth = $this->_auth;

        if (is_null($value) || $value == []) {
            return '<span class="text-pink"><i>(Vazio)</i></span>';
        }

        if (isset($params['foreign_key'])) {
            $m = $params['foreign_key']::loadById($value, $config, $auth, true);
            return "<span class='text-{$m->color()}'>$m</span>";
        }

        if (isset($params['choices_colors'])) {
            return "<span class='text-{$params['choices_colors'][$value]}'>{$params['choices'][$value]}</span>";
        }

        if (isset($params['choices'])) {
            return (string) $params['choices'][$value];
        }

        if ($params['type'] == 'date') {
            return Util::formatDate($value, 'd/m/Y');
        }

        if ($params['type'] == 'datetime') {
            return Util::formatDate($value);
        }

        if (in_array($params['type'], ['float', 'numeric', 'decimal'])) {
            return Util::formatNumber($value, false);
        }

        if ($params['type'] == 'boolean') { // ⚫⚪
            return ($value) ? '&#x26AB;' : '&#x26AA';
        }

        if (isset($params['many_choices'])) {

            $data = [];

            foreach ($params['many_choices'] as $choice => $description) {
                if (in_array($choice, $value)) {
                    $data[] = $description;
                }
            }

            return implode(', ', $data);

        }

        if (isset($params['many_to_many'])) {

            /** @var self $class */
            $class = new $params['many_to_many']();

            $data = [];

            foreach ($class->query()->filter($class->getFieldId(), $value)->xselect() as $row) {
                //if (in_array($choice, $value)) {
                    $data[] = (string) $row;
                //}
            }

            return implode(', ', $data);

        }

        /*

        if ($params['type'] == 'boolean') { // ⬛⬜
            return ($value) ? '&#x2B1B;' : '&#x2B1C';
        }

        if ($params['type'] == 'boolean') { // ☒☐
            return ($value) ? '&#x2612;' : '&#x2610';
        }

        if ($params['type'] == 'boolean') {
            return ($value) ? '&#x2714;' : '-';
        }

        */

        if ($params['type'] == 'string' && isset($params['mask'])) { // ⚫⚪
            return Util::mask($value, $params['mask']);
        }

        return (string) $value;

    }

    /** @throws CustomException */
    public function onSave(): bool { return true; }

    public function onSaved(): void {}

    public function onDelete(): bool { return true; }

    public function onDeleted(): void {}

    public function afterCrudSaved(): void {}

    public function extrasEdit() { return null; }

    public function extrasView() { return null; }

    /*
     * CRUD
     * */

    const ACTION_APAGAR = 1;
    const ACTION_OCULTAR = 2;
    const ACTION_EXIBIR = 3;
    const ACTION_CANCELAR = 4;
    const ACTION_PRORROGAR = 5;
    const ACTION_LIMPAR = 6;
    const ACTION_LIMPAR_TUDO = 60;
    const ACTION_EDITAR = 7;
    const ACTION_CONFIGURAR = 8;
    const ACTION_DISTRIBUIR = 9;
    const ACTION_HISTORICO = 10;
    const ACTION_DESABILITAR = 11;
    const ACTION_DESABILITAR_TUDO = 12;
    const ACTION_VER_DISTRIBUICAO = 13;
    const ACTION_DUPLICAR = 14;

    public function findById($id): Query
    {

        $class = get_class($this);

        $query = new Query([
            'class' => $class,
            'table' => $this->_table,
            'field_id' => $this->_field_id,
            'database' => $this->_database,
        ], $this);

        $i = 0;

        $arr = explode(',', $id);

        foreach ($this->_primary_keys as $key) {

            $query = $query->filter($key, $arr[$i++]);

        }

        return $query->limit(1);

    }

    public function actions(): array
    {

        //$config = $this->_config;
        $auth = $this->_auth;

        $permissions = $auth->getPermissions($this);

        $actions = [];

        if ($permissions['delete'] && !$this->_view) {

            $actions[] = [
                'id' => self::ACTION_APAGAR,
                'type' => 'action',
                'label' => '<span class="text-red">Apagar</span>',
                'disabled' => false,
                'confirm' => 'Deseja apagar o registro?',
            ];

        }

        return $actions;

    }

    public function executeAction($action): bool
    {

        $config = $this->_config;
        $auth = $this->_auth;

        if ($action == self::ACTION_APAGAR) {

            if (!$this->hasPermission('delete')) {
                return false;
            }

            $this->findById($this->id())->delete($config, $auth);

        }

        return true;

    }

    public function formAction($action): array
    {

        return [];

    }

    public function createModelFromName($name): Model
    {

        return new $name($this->_config, $this->_auth);

    }

    public function filters(stdClass $itens): array
    {

        $config = $this->_config;
        $auth = $this->_auth;

        $filters = [];

        $icons = 1;

        foreach ($this->_fields as $key => $value) {

            if ($value['filter'] && $key != $this->_field_tab) {

                $query = $this->query();

                if ($this->_linked_filters) {

                    $itens_c = clone $itens;

                    if (isset($itens_c->$key)) {
                        unset($itens_c->$key);
                    }

                    $query = $this->filterItens($query, $itens_c);

                }

                $filter = [
                    'field' => $key,
                    'label' => $value['label'],
                    'icon' => $value['filter_icon'] ?? ($icons > 9 ? 'collection-item' : 'collection-item-' . $icons),
                    'multiple' => $value['filter_multiple'] ?? true,
                ];

                $icons++;

                $options = $itens->$key ?? [];

                $choices = [];

                $choices[] = ['id' => null, 'label' => '(Todos)', 'active' => (count($options) == 0)];

                if (isset($value['choices'])) {

                    $permitted = $query->groupBy($key)->toArray($config, $auth);

                    foreach ($value['choices'] as $k => $v) {

                        if (!in_array($k, $permitted)) {
                            continue;
                        }

                        $color = null;

                        if (isset($value['choices_colors'])) {

                            $color = $value['choices_colors'][$k] ?? null;

                        }

                        $choices[] = ['id' => $k, 'label' => $v, 'color' => $color, 'active' => in_array($k, $options)];

                    }

                } elseif ($value['type'] == 'boolean') {

                    $choices[] = ['id' => false, 'label' => 'Não', 'active' => in_array(false, $options)];
                    $choices[] = ['id' => true, 'label' => 'Sim', 'active' => in_array(true, $options)];

                } elseif (isset($value['foreign_key'])) {

                    $m = $this->createModelFromName($value['foreign_key']);

                    $m_id = $m->_field_id;

                    foreach ($m->_order as $k) {

                        $m = $m->orderBy($k);

                    }

                    if (isset($value['foreign_key_filter'])) {

                        foreach ($value['foreign_key_filter'] as $f => $v) {

                            $m = $m->filter($f, $v);

                        }

                    }

                    if ($value['foreign_key'] == 'Esj\Core\Auth\Models\CostCenter'
                        || $value['foreign_key'] == '\Esj\Core\Auth\Models\CostCenter') {

                        if (!$this->_auth->hasPermission($this, 'under')) {
                            $permitted = $this->_auth->getCostCenter()->id;
                        } else {
                            $permitted = $this->_auth->getAllCostCenterAccess();
                        }

                        if (!$this->_auth->hasPermission($this, 'special')) {
                            $m = $m->filter($m_id, $permitted);
                        }

                    }

                    foreach ($m->filterIf($m_id, $query->groupBy($key), !isset($value['filter_show_all']) || !$value['filter_show_all'])
                                 ->xselect($config, $auth) as $k) {

                        $choices[] = ['id' => $k->$m_id, 'label' => strval($k), 'color' => $k->color(), 'active' => in_array($k->$m_id, $options)];

                    }

                } elseif (isset($value['many_to_many'])) {

                    $m = $this->createModelFromName($value['many_to_many']);

                    $mn = new MnModel($this, $key, false, $config, $auth);

                    $m_id = $m->_field_id;

                    foreach ($m->_order as $k) {

                        $m = $m->orderBy($k);

                    }

                    if (isset($value['many_to_many_filter'])) {

                        foreach ($value['many_to_many_filter'] as $f => $v) {

                            $m = $m->filter($f, $v);

                        }

                    }

                    if ($value['many_to_many'] == 'Esj\Core\Auth\Models\CostCenter'
                        || $value['many_to_many'] == '\Esj\Core\Auth\Models\CostCenter') {

                        $permitted = $this->_auth->getAllCostCenterAccess();

                        if (!$this->_auth->hasPermission($this, 'under')) {
                            $permitted = $this->_auth->getCostCenter()->id;
                        }

                        if (!$this->_auth->hasPermission($this, 'special')) {
                            $m = $m->filter($m_id, $permitted);
                        }

                    }

                    foreach ($m->filterIf($m_id, $mn->_groupBy('b')/*->toArray($config, $auth)*/, !isset($value['filter_show_all']) || !$value['filter_show_all'])
                                 ->xselect($config, $auth) as $k) {

                        $choices[] = ['id' => $k->$m_id, 'label' => strval($k), 'color' => $k->color(), 'active' => in_array($k->$m_id, $options)];

                    }

                } elseif (isset($value['many_choices'])) {

                    $mn = new MnChoicesModel($this, $key, $config, $auth);

                    $permitted = $mn->_filter('a', $query->only($this->_field_id))->groupBy('b')->toArray($config, $auth);

                    foreach ($value['many_choices'] as $k => $v) {

                        if (!in_array($k, $permitted)) {
                            continue;
                        }

                        $color = null;

                        if (isset($value['many_choices_colors'])) {

                            $color = $value['many_choices_colors'][$k] ?? null;

                        }

                        $choices[] = ['id' => $k, 'label' => $v, 'color' => $color, 'active' => in_array($k, $options)];

                    }

                } else {

                    $permitted = $this->groupBy($key)->toArray($config, $auth);

                    foreach ($permitted  as $k) {

                        $choices[] = ['id' => $k, 'label' => mb_strtoupper($k, 'utf8'), 'color' => null, 'active' => in_array($k, $options)];

                    }

                }

                $filter['itens'] = $choices;

                $filters[] = $filter;

            }

        }

        return $filters;

    }

    public function filterItens(Query $query, stdClass $itens): Query
    {

        $config = $this->_config;
        $auth = $this->_auth;

        if (!$auth->hasPermission($this, 'special')) {

            foreach ($this->_fields as $key => $value) {

                if (isset($value['foreign_key'])) {

                    if ($value['foreign_key'] == 'Esj\Core\Auth\Models\CostCenter'
                        || $value['foreign_key'] == '\Esj\Core\Auth\Models\CostCenter') {

                        if ($auth->hasPermission($this, 'under')) {
                            $permitted = $auth->getAllCostCenterAccess();
                        } else {
                            $permitted = $auth->getUser()->costCenters();
                        }

                        $query = $query->filter($key, $permitted);

                    }

                } elseif (isset($value['many_to_many'])) {

                    if ($value['many_to_many'] == 'Esj\Core\Auth\Models\CostCenter'
                        || $value['many_to_many'] == '\Esj\Core\Auth\Models\CostCenter') {

                        if ($auth->hasPermission($this, 'under')) {
                            $permitted = $auth->getAllCostCenterAccess();
                        } else {
                            $permitted = $auth->getCostCenter()->id;
                        }

                        $query = $query->filter($key, $permitted);

                    }

                }

            }

        }

        foreach ($itens as $key => $permitted) {

            if (count($permitted) > 0) {

                if (isset($this->_fields[$key]['many_to_many'])) {

                    $mn = new MnModel($this, $key, false, $config, $auth);

                    $itens = $mn->_filter('b', $permitted)->groupBy('a');

                    $query = $query->filter($this->_field_id, $itens);

                } elseif (isset($this->_fields[$key]['many_choices'])) {

                    $mn = new MnChoicesModel($this, $key, $config, $auth);

                    $itens = $mn->_filter('b', $permitted)->groupBy('a');

                    $query = $query->filter($this->_field_id, $itens);

                } elseif (isset($this->_fields[$key])) {

                    $query = $query->filter($key, $permitted);

                }

            }

        }

        return $query;

    }

    public function pageTitle(): string
    {

        return $this->_verbose_name_plural;

    }

    public function htmlTitle($id = null): string
    {
        return (is_null($id) || $id == 'add') ? "$this->_html_title" : "$this->_html_title | $this";
    }

    public function pageSubtitle(): string
    {

        return '#INTERNO.CONFIDENCIAL';

    }

    public function pageDescription(): string
    {

        return 'Utilize os filtros ou a opção de busca para algum item específico.';

    }

    public function notFoundMessage(): string
    {

        return 'Nenhum registro encontrado!';

    }

    public function canDownload(): bool
    {

        return true;

    }

    public function updateTitle(): string
    {

        return 'Atualizado em';

    }

    public function updateFormat(): string
    {

        return 'dd/MM/y HH:mm';

    }

    public function updateInfo(): ?string
    {

        return ($this->_log_fields) ? Util::jsDate($this->_update) : null;

    }

    public function title(): string
    {

        return (string) $this;

    }

    public function subtitle(): string
    {

        return '#' . $this->id();

    }

    public function tags(): array
    {

        return [];

    }

    public function id(): string
    {

        $arr = [];

        foreach ($this->_primary_keys as $key) {

            $arr[] = $this->$key;

        }

        return implode(',', $arr);

    }

    public function orders(): array
    {

        $orders = [];

        if ($this->_log_fields || $this->_field_id_ai) {

            $orders[] = [
                'id' => 0,
                'label' => 'Mais Novos',
            ];

            $orders[] = [
                'id' => 1,
                'label' => 'Mais Antigos',
            ];

        }

        return $orders;

    }

    public function order(Query $query, $order): Query
    {

        $this->_default_order = $order;

        if ($this->_field_id_ai) {

            if ($order == 0) {

                $query->orderBy($this->_field_id, DESC);

            } elseif ($order == 1) {

                $query->orderBy($this->_field_id, ASC);

            }

        } elseif ($this->_log_fields) {

            if ($order == 0) {

                $query->orderBy('_insert', DESC);

            } elseif ($order == 1) {

                $query->orderBy('_insert', ASC);

            }

        }

        return $query;

    }

    protected $_default_tab = null;

    protected $_field_tab = null;

    public function tabs(Query $query): array
    {

        $config = $this->_config;
        $auth = $this->_auth;

        /*if ($auth->getUser()->login != 'c098422') {
            return [];
        }*/

        $query = clone $query;

        $choices = [];

        if (!is_null($this->_field_tab)) {

            $key = $this->_field_tab;

            $value = $this->_fields[$key] ?? Application::error("Campo <b>$key</b> não encontrado!");

            if (isset($value['choices'])) {

                foreach ((clone $query)->groupBy($key)->count($key)->xselect($config, $auth) as $tab) {

                    $label = $value['choices'][$tab->$key] ?? $tab->$key;

                    $choices[] = ['id' => $tab->$key, 'label' => $label, 'itens' => $tab->total];

                }

            } elseif (isset($value['foreign_key'])) {

                foreach ((clone $query)->groupBy($key)->count($key)->xselect($config, $auth) as $tab) {

                    $label = (string) $value['foreign_key']::loadById($tab->$key, $config, $auth);

                    $choices[] = ['id' => $tab->$key, 'label' => $label, 'itens' => $tab->total];

                }

            } elseif (isset($value['many_choices'])) {

                foreach ((clone $query)->groupBy($key)->count($key)->xselect($config, $auth) as $tab) {

                    $label = $value['many_choices'][$tab->$key] ?? $tab->$key;

                    $choices[] = ['id' => $tab->$key, 'label' => $label, 'itens' => $tab->total];

                }

            } elseif (isset($value['many_to_many'])) {

                $m = $this->createModelFromName($value['many_to_many']);

                $mn = new MnModel($this, $key, false);

                $m_id = $m->_field_id;

                foreach ($m->_order as $k) {

                    $m = $m->orderBy($k);

                }

                /*if (isset($value['many_to_many_filter'])) {

                    foreach ($value['many_to_many_filter'] as $f => $v) {

                        $m = $m->filter($f, $v);

                    }

                }*/

                /*if ($value['many_to_many'] == 'Esj\Core\Auth\Models\CostCenter'
                    || $value['many_to_many'] == '\Esj\Core\Auth\Models\CostCenter') {

                    $permitted = $this->_auth->getAllCostCenterAccess();

                    if (!$this->_auth->hasPermission($this, 'under')) {
                        $permitted = $this->_auth->getCostCenter()->id;
                    }

                    if (!$this->_auth->hasPermission($this, 'special')) {
                        $m = $m->filter($m_id, $permitted);
                    }

                }*/

                $q = [];
                foreach ($mn->_groupBy('b')->filter('a', (clone $query)->only($this->getFieldId()))->count('a')->xselect() as $k) {
                    $q[$k->b] = $k->total;
                }

                foreach ($m->filter($m_id, $mn->_groupBy('b')->filter('a', (clone $query)->only($this->getFieldId())))->xselect() as $k) {

                    $choices[] = ['id' => $k->$m_id, 'label' => strval($k), 'itens' => $q[$k->$m_id]];

                }

            } else {

                foreach ((clone $query)->groupBy($key)->count($key)->xselect($config, $auth) as $tab) {

                    $choices[] = ['id' => $tab->$key, 'label' => $tab->$key, 'itens' => $tab->total];

                }

            }

            $choices2 = Util::ordenar($choices, [
                'label' => SORT_ASC,
            ]);

            $choices = [];

            if (is_null($this->_default_tab)) {

                $total = (clone $query)->count($key)->firstOrNew($config, $auth)->total;

                $choices[] = ['id' => null, 'label' => 'Todos', 'itens' => $total];

            }

            foreach ($choices2 as $choice) {

                $choices[] = $choice;

            }

        }

        return $choices;

    }

    public function getDefaultTab()
    {
        return $this->_default_tab;
    }

    public function tab(Query $query, &$tab): Query
    {

        $config = $this->_config;
        $auth = $this->_auth;

        if (is_null($tab)) {

            return $query;

        }

        if (!is_null($this->_field_tab)) {

            $key = $this->_field_tab;

            $value = $this->_fields[$key] ?? Application::error("Campo <b>$key</b> não encontrado!");

            if (isset($value['many_to_many'])) {

                //$m = $this->createModelFromName($value['many_to_many']);

                $mn = new MnModel($this, $key, false);

                $teste = (clone $query)->filter($this->_field_id, $mn->_filter('b', $tab)->groupBy('a'))->firstOrNew();

                if (is_null($teste->{$teste->getFieldId()})) {

                    $tab = $this->_default_tab;

                    if (is_null($tab)) {

                        return $query;

                    }

                }

                $query = $query->filter($this->_field_id, $mn->_filter('b', $tab)->groupBy('a'));

            } else {

                $teste = (clone $query)->filter($key, $tab)->firstOrNew($config, $auth);

                if (is_null($teste->$key)) {

                    $tab = $this->_default_tab;

                    if (is_null($tab)) {

                        return $query;

                    }

                }

                $query = $query->filter($key, $tab);

            }

        }

        return $query;

    }

    public function search(Query $query, $search): Query
    {

        $sql = [];

        $numeric = (is_numeric($search) && $search < LIMITE_BIGINT);

        foreach ($this->_fields as $key => $value) {

            if ($value['search']) {

                switch ($value['type']) {

                    case 'integer':

                        if ($numeric) {

                            $sql[] = Sql::filter($key, (int) $search);

                        }

                        break;

                    case 'string':
                    case 'text':
                    case 'html':

                        if ($value['fulltext'] ?? false) {

                            $sql[] = Sql::filter($key . '__fulltext', $search);

                        } else {

                            $sql[] = Sql::filter($key . '__like', $search . '%');

                        }

                        break;

                    case 'upload':

                        $sql[] = Sql::filter($key . '__like', '%' . $search . '%');

                        break;

                }

            }

        }

        if (count($sql) > 0) {

            $query = $query->filter(Sql::_or($sql));

        }

        return $query;

    }

    public function hasSearch(): bool
    {

        foreach ($this->_fields as $value) {

            if ($value['search']) {

                return true;

            }

        }

        return false;

    }

    public function extras(): array
    {
        return [];
    }

    protected $_search_placeholder = 'Buscar...';

    public function getSearchPlaceholder(): string
    {
        return $this->_search_placeholder;
    }

    public function toData($state = Model::STATE_VIEW): array
    {

        $this->changeState($state);

        return [
            'id' => $this->id(),
            'title' => $this->title(),
            'subtitle' => $this->subtitle(),
            'extras' => $this->extras(),
            'tags' => $this->tags(),
            'actions' => $this->actions(),
            'update' => $this->updateInfo(),
        ];

    }

    public function hasPermission($permission): bool
    {
        return $this->_auth->hasPermission($this, $permission);
    }

    public function color(): ?string
    {
        return null;
    }

    public function loadMnItens($field, $inverted = false): ?array
    {

        $config = $this->_config;
        $auth = $this->_auth;

        if (isset($this->_fields[$field]['many_choices'])) {
            $mn = new MnChoicesModel($this, $field, $config, $auth);
        } else {
            $mn = new MnModel($this, $field, $inverted, $config, $auth);
        }

        return $mn->load($this->{$this->_field_id});

    }

    public function getInlines(): array
    {
        return $this->_inlines;
    }

    protected $_inlines = [];

}
