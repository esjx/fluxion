<?php
namespace Fluxion;

use Exception;
use PhpOffice\PhpSpreadsheet as Excel;
use stdClass;
use ZipArchive;

class ControllerOld extends Service
{

    const HOME_CONTROLLER = 'Esj\App\Home\HomeController';

    protected $class = __CLASS__;
    protected $namespace = __NAMESPACE__;
    protected $dir = __DIR__;

    protected $GROUP = 'group';
    protected $TITLE = 'title';
    protected $ICON = '';

    protected $MODELS = [];

    protected $SETUP_SCRIPTS = [];

    protected $_config;
    protected $_auth;
    protected $_application;

    const COLORS = [
        '#2196F3',
        '#444444',
        '#32c787',
        '#ffc721',
        '#ff85af',
        '#ff6b68',
        '#39bbb0',
        '#d066e2',
        '#673AB7',
        '#3F51B5',
        '#03A9F4',
        '#00BCD4',
        '#8BC34A',
        '#CDDC39',
        '#FFEB3B',
        '#FF9800',
        '#FF5722',
        '#795548',
        '#9E9E9E',
        '#607D8B',
    ];

    public function __construct(Config $config = null, Auth $auth = null, Application $application = null)
    {

        $this->_config = $config ?? $GLOBALS['CONFIG'] ?? null;
        $this->_auth = $auth ?? $GLOBALS['AUTH'] ?? null;
        $this->_application = $application;

    }

    public function getGroup(): string
    {
        return $this->GROUP;
    }

    public function testDomainList()
    {

        $config = $this->_config;

        $re = '/^(?P<protocol>\w+):\/\/(?P<host>[\w.]*(?P<domain>\w+.\w+))\//U';

        if (!preg_match($re, $_SERVER['HTTP_REFERER'] ?? '', $dados)) {
            Application::error('Operação não permitida');
        }

        if (!in_array($dados['domain'], $config->getDomainList())) {
            Application::error('Operação não permitida para o domínio <b>' . $dados['domain'] . '</b>!' . '<br>' . implode(';', $config->getDomainList()));
        }

    }

    /*
     * ROUTES
     * */

    public function createRoute(string $model): array
    {

        $args = [
            'group' => $this->GROUP,
            'namespace' => $this->namespace . '\Models',
            'model' => $model,
        ];

        $model_name = $this->namespace . '\Models\\' . Application::strToClass($model);

        return [

            ['method' => 'POST', 'route' => '/' . $this->GROUP . '/' . $model . '/data', 'control' => __CLASS__, 'action' => 'data', 'args' => $args],
            ['method' => 'POST', 'route' => '/' . $this->GROUP . '/' . $model . '/fields', 'control' => __CLASS__, 'action' => 'fields', 'args' => $args],
            ['method' => 'POST', 'route' => '/' . $this->GROUP . '/' . $model . '/save', 'control' => __CLASS__, 'action' => 'save', 'args' => $args],
            ['method' => 'POST', 'route' => '/' . $this->GROUP . '/' . $model . '/action', 'control' => __CLASS__, 'action' => 'action', 'args' => $args],
            ['method' => 'POST', 'route' => '/' . $this->GROUP . '/' . $model . '/action-fields', 'control' => __CLASS__, 'action' => 'actionFields', 'args' => $args],
            ['method' => 'POST', 'route' => '/' . $this->GROUP . '/' . $model . '/action-save', 'control' => __CLASS__, 'action' => 'actionSave', 'args' => $args],
            ['method' => 'POST', 'route' => '/' . $this->GROUP . '/' . $model . '/typeahead/{field:string}', 'control' => __CLASS__, 'action' => 'typeahead', 'args' => $args],

            ['route' => '/' . $this->GROUP . '/' . $model . '/download', 'control' => __CLASS__, 'action' => 'excel', 'args' => $args],
            ['route' => '/' . $this->GROUP . '/' . $model . '/download/?d={filter:string}', 'control' => __CLASS__, 'action' => 'excel', 'args' => $args],
            ['route' => '/' . $this->GROUP . '/' . $model . '/download/{order:string}/?d={filter:string}', 'control' => __CLASS__, 'action' => 'excel', 'args' => $args],
            ['route' => '/' . $this->GROUP . '/' . $model . '/download/{filter:string}', 'control' => __CLASS__, 'action' => 'excel', 'args' => $args],
            ['route' => '/' . $this->GROUP . '/' . $model . '/download/{order:string}/{filter:string}', 'control' => __CLASS__, 'action' => 'excel', 'args' => $args],

            ['route' => '/' . $this->GROUP . '/' . $model, 'control' => self::HOME_CONTROLLER, 'action' => 'index'],
            ['model' => $model_name, 'route' => '/' . $this->GROUP . '/' . $model . '/{id:string}', 'control' => self::HOME_CONTROLLER, 'action' => 'index'],

        ];

    }

    public function getPhpRoutes(): array
    {

        $arr = [];

        $arr[] = [
            'route' => '/' . $this->GROUP . '/{model:string}/install',
            'control' => __CLASS__,
            'action' => 'install',
            'args' => [
            'group' => $this->GROUP,
            'namespace' => $this->namespace . '\Models',
            ]
        ];

        foreach ($this->MODELS as $model => $title) {

            $arr = array_merge($arr, $this->createRoute($model));

        }

        $arr[] = ['route' => '/' . $this->GROUP . '/cronjob', 'control' => $this->class, 'action' => 'cronjob'];
        $arr[] = ['route' => '/' . $this->GROUP . '/setup', 'control' => $this->class, 'action' => 'setup'];

        return $arr;

    }

    public function getSiteMap(): array
    {

        $show = false;

        $arr = [];

        foreach ($this->MODELS as $model => $title) {

            $arr[] = [
                'title' => $title,
                'route' => '/' . $this->GROUP . '/' . $model,
                'visible' => $showB = $this->_auth->hasPermission($this->namespace . '\Models\\' . Application::strToClass($model), 'view'),
            ];

            $show = ($show || $showB);

        }

        return [[
            'title' => $this->TITLE,
            'icon' => $this->ICON,
            'route' => '/' . $this->GROUP,
            'sub' => $arr,
            'visibility' => 'inactive',
            'visible' => $show,
        ]];

    }

    /*
     * MISC
     * */

    public function cronjob(): void {}

    public function redirect($arr): void
    {

        Application::redirect($arr->url, $arr->status_code ?? 301);

    }

    /**
     * @throws \ReflectionException
     */
    public function setup($arr)
    {

        $config = $this->_config;
        $auth = $this->_auth;

        $connector = $config->getConnectorById(0);

        echo '<pre>';

        echo 'Localizando arquivos...' . PHP_EOL . PHP_EOL;

        $list = [];

        foreach (Util::loadAllFiles($this->dir . DIRECTORY_SEPARATOR . 'Models') as $file) {

            $file = str_replace($this->dir, '', $file);
            $file = preg_replace('/\.php$/i', '', $file);

            $file = str_replace(DIRECTORY_SEPARATOR, '\\', $file);

            $file = $this->namespace . $file;

            $test = new \ReflectionClass($file);

            if (is_subclass_of($file, ModelOld::class) && !$test->isAbstract()) {

                /** @var ModelOld $model */
                $model = ModelOld::createFromName($file, $config, $auth);

                $list[$file] = $model->getCreateOrder();

            }

        }

        echo 'Verificando dependências...' . PHP_EOL . PHP_EOL;

        foreach ($list as $class=>$index) {

            $test = new \ReflectionClass($class);

            if (is_subclass_of($class, ModelOld::class) && !$test->isAbstract()) {

                $model = ModelOld::createFromName($class, $config, $auth);

                foreach ($model->getFields() as $value) {

                    if (isset($value['foreign_key'])
                        && isset($list[$value['foreign_key']])
                        && $list[$value['foreign_key']] >= $list[$class]) {

                        $list[$value['foreign_key']] = $list[$class] - 1;

                    }

                }

                foreach ($model->getFields() as $value) {

                    if (isset($value['many_to_many'])
                        && isset($list[$value['many_to_many']])
                        && $list[$value['many_to_many']] >= $list[$class]) {

                        $list[$value['many_to_many']] = $list[$class] - 1;

                    }

                }

                foreach ($model->getViewDependencies() as $key) {

                    if (isset($list[$key]) && $list[$key] >= $list[$class]) {

                        $list[$key] = $list[$class] - 1;

                    }

                }

            }

        }

        asort($list);

        print_r($list);

        foreach ($list as $class => $index) {

            $test = new \ReflectionClass($class);

            if (is_subclass_of($class, 'Fluxion\ModelOld') && !$test->isAbstract()) {

                echo 'Model <b>' . $class . '</b>';

                ModelManipulate::sync($class, false, $config, $auth);

                echo ' - OK' . PHP_EOL;

            }

        }

        foreach ($this->SETUP_SCRIPTS as $script) {

            if (!file_exists($script)) {
                continue;
            }

            $sql = file_get_contents($script);

            echo 'Script <b>' . $script . '</b>';

            echo SqlFormatter::format($sql);

            ModelManipulate::exec($sql, $connector->getPDO());

            echo ' - OK' . PHP_EOL;

        }

        echo PHP_EOL . '<b>Finalizado!</b>';

    }

    public function install($arr)
    {

        $this->_auth->testPermission(__NAMESPACE__ . '\Auth\Models\User', 'special');

        echo Application::strToClass($arr->model);

        ModelManipulate::sync($arr->namespace . '\\' . Application::strToClass($arr->model), false, $this->_config, $this->_auth);

    }

    /*
     * CRUD
     * */

    public function data($arr)
    {

        $this->testDomainList();

        $config = $this->_config;
        $auth = $this->_auth;

        $is = Application::inputStream();

        $model = $this->createModelFromRoute($arr, 'view');

        $permissions = $auth->getPermissions($model);

        $permissions['download'] = $model->canDownload();

        if ($model->isView()) {

            $permissions['insert'] = false;
            $permissions['delete'] = false;
            $permissions['update'] = false;

        }

        $order = $is->order ?? $model->getDefaultOrder();
        $tab = $is->tab ?? $model->getDefaultTab();

        $query = $model->query();

        $model->changeState(ModelOld::STATE_FILTER);

        $is->search = str_replace("'", '', $is->search ?? '');
        $is->search = str_replace('"', '', $is->search);
        $is->search = str_replace('#', '', $is->search);

        $is->search = trim($is->search);

        if ($is->search != '') {

            $query = $model->filterItens($query, new stdClass());

            $tabs = $model->tabs($query);

            $query = $model->search($query, $is->search);

        } else {

            $query = $model->filterItens($query, $is->filters ?? (new stdClass()));

            $tabs = $model->tabs($query);

            $query = $model->tab($query, $tab);

        }

        $query = $model->order($query, $order);

        $page = (int) $this->initialize('page', 1);

        $pages = 1;

        $data = [];

        foreach ($query->xpaginate($page, $pages, $model->getIpp(), $config, $auth) as $k) {

            $data[] = $k->toData();

        }

        $html_title = $model->htmlTitle();

        if (isset($_ENV['ENVIRONMENT_TITLE'])) {
            $html_title = "[{$_ENV['ENVIRONMENT_TITLE']}] $html_title";
        }

        Application::printJson([
            'refresh' => $model->getRefreshTime(),
            'title' => $model->pageTitle(),
            'html_title' => $html_title,
            'subtitle' => $model->pageSubtitle(),
            'description' => $model->pageDescription(),
            'not_found_message' => $model->notFoundMessage(),
            'has_search' => $model->hasSearch(),
            'search_placeholder' => $model->getSearchPlaceholder(),
            'update_title' => $model->updateTitle(),
            'update_format' => $model->updateFormat(),
            'order' => $order,
            'orders' => $model->orders(),
            'tab' => $tab,
            'tabs' => $tabs,
            'page' => $page,
            'pages' => $pages,
            'itens_per_page' => $model->getIpp(),
            'permissions' => $permissions,
            'filters' => $model->filters($is->filters ?? new stdClass()),
            'data' => $data,
        ]);

    }

    public function fields($arr)
    {

        $config = $this->_config;
        $auth = $this->_auth;

        $this->testDomainList();

        $is = Application::inputStream();

        $permission = ($is->__id && $is->__id != 'add') ? 'update' : 'insert';

        $model = $this->createModelFromRoute($arr, $permission);

        if ($is->__id != 'add') {
            $model = $model->findById($is->__id)->firstOrNew($config, $auth);
        }

        $model->changeState(ModelOld::STATE_EDIT);

        $save = (!$model->isView() && $model->hasPermission($permission));

        $campos = $this->getFields($model, $is->__id, $save);

        $inlines = $this->getInlines($model);

        Application::printJson([
            'size' => $model->getFormSize(),
            'header' => $model->getFormHeader(null),
            'footer' => $model->getFormFooter(null),
            'title' => $model->getVerboseName(),
            'fields' => $campos,
            'inlines' => $inlines,
            'html_title' => $model->htmlTitle($is->__id),
            'save' => $save,
        ]);

    }

    public function actionFields($arr)
    {

        $this->testDomainList();

        $config = $this->_config;
        $auth = $this->_auth;

        $is = Application::inputStream();

        $model = $this->createModelFromRoute($arr, 'view');

        $model = $model->findById($is->id)->firstOrNew($config, $auth);

        $campos = $model->getActionFields($is->action);

        $save = !$model->isView();

        Application::printJson([
            'size' => $model->getFormSize(),
            'header' => $model->getFormHeader($is->action),
            'footer' => $model->getFormFooter($is->action),
            'tipo' => $model->getVerboseName(),
            'save' => $save,
            //'type' => $model->getVerboseName(),
            'campos' => $campos,
            //'fields' => $campos,
        ]);

    }

    public function actionSave($arr)
    {

        $this->testDomainList();

        $config = $this->_config;
        $auth = $this->_auth;

        $is = Application::inputStream();

        $model = $this->createModelFromRoute($arr, 'view');

        $model = $model->findById($is->id)->firstOrNew($config, $auth);

        Application::printJson([
            'type' => 'refresh',
            'ok' => $model->actionSave($is->action, $is)
        ]);

    }

    public function form($arr)
    {

        $this->testDomainList();

        $config = $this->_config;
        $auth = $this->_auth;

        $is = Application::inputStream();

        $model = $this->createModelFromRoute($arr, 'view');

        $save = (!$model->isView() && $model->hasPermission('update'));

        $model = $model->findById($is->id)->firstOrNew($config, $auth);

        Application::printJson([
            'size' => $model->getFormSize(),
            'header' => $model->getFormHeader(null),
            'footer' => $model->getFormFooter(null),
            'type' => $model->getVerboseName(),
            'save' => $save,
            'fields' => $model->formAction($is->action),
        ]);

    }

    public function save($arr)
    {

        $this->testDomainList();

        $config = $this->_config;
        $auth = $this->_auth;

        $is = Application::inputStream();

        $model = $this->createModelFromRoute($arr, ($is->__id && $is->__id != 'add') ? 'update' : 'insert');

        if (!is_null($is->__id) && $is->__id != 'add') {

            $model = $model->findById($is->__id)->firstOrNew($config, $auth);

            if ($model->preLoad()) {
                $model->setOriginals();
            }

        }

        try {

            if (!$this->saveToModel($model, $is)) {
                Application::error('Erro ao salvar!');
            }

        } catch (Exception $e) {
            Application::error($e->getMessage());
        }

        $model->afterCrudSaved();

        Application::printJson([
            'type' => 'refresh',
            'ok' => true,
            'id' => $model->id(),
        ]);

    }

    public function action($arr)
    {

        $this->testDomainList();

        $config = $this->_config;
        $auth = $this->_auth;

        $is = Application::inputStream();

        $model = $this->createModelFromRoute($arr, 'view');

        $model = $model->findById($is->id)->firstOrNew($config, $auth);

        Application::printJson([
            'type' => 'refresh',
            'ok' => $model->executeAction($is->action)
        ]);

    }

    public function typeahead($arr)
    {

        $config = $this->_config;
        $auth = $this->_auth;

        $choices = [];

        $is = Application::inputStream();

        $m = $this->createModelFromRoute($arr, 'view');

        $m->changeState(ModelOld::STATE_TYPEAHEAD);

        $field = $m->getFields()[$arr->field] ?? Application::error("Campos <b>$arr->field</b> não encontrado!");

        $model_name = $field['foreign_key']
            ?? $field['many_to_many']
            ?? $field['i_many_to_many']
            ?? Application::error("Campos <b>$arr->field</b> não possui fonte de dados!");

        $filters = $field['foreign_key_filter']
            ?? $field['many_to_many_filter']
            ?? $field['i_many_to_many_filter']
            ?? [];

        $model = $this->createModelFromName($model_name);

        $m_id = $model->getFieldId();

        $query = $model->query();

        $query = $model->search($query, $is->busca);

        foreach ($model->getOrder() as $k)
            $query = $query->orderBy($k[0], $k[1]);

        foreach ($filters as $f => $v)
            $query = $query->filter($f, $v);

        $limit = self::TYPEAHEAD_LIMIT;

        foreach ($query->limit($limit)->xselect($config, $auth) as $k) {
            $choices[] = ['id' => $k->$m_id, 'label' => strval($k)];
        }

        Application::printJson([
            'dados' => $choices,
        ]);

    }

    /*
     * EXCEL
     * */

    public function excel($arr)
    {

        $this->testDomainList();

        $config = $this->_config;
        $auth = $this->_auth;

        $model = $this->createModelFromRoute($arr, 'view');

        if (!$model->canDownload()) {
            Application::error('Download não permitido!');
        }

        $query = $model->query();

        $query = $model->filterItens($query, json_decode(base64_decode($arr->filter ?? $_GET['d']))->filters);

        $total = (clone $query)->count()->firstOrNew($config, $auth)->total;

        if ($total <= 500) {

            $this->excelFormatted($arr, $total);

        } else {

            $this->excelFast($arr, $total);

        }

    }

    public function excelFast($arr, $total)
    {

        $config = $this->_config;
        $auth = $this->_auth;

        $model_name = $arr->namespace . '\\' . Application::strToClass($arr->model);

        $baseDir = dirname(__FILE__) . '/../../';

        $excelTempFileName =  $auth->getUser()->login . '_' . time() . '.xls';

        $excel = new ExportDataExcel('file', $baseDir . 'uploads/' . $excelTempFileName);

        $excel->setTitle($arr->model);

        $excel->initialize();

        $model = $this->createModelFromRoute($arr, 'view');

        $model->changeState(ModelOld::STATE_EXCEL);

        // Headers

        $row = [];

        foreach ($model->getFields() as $value) {

            if (!$value['protected']) {

                $row[] = preg_replace('/<[^>]*>/', '', $value['label']);

            }

        }

        $excel->addRow($row);

        // Results

        $query = $model->query();

        $query = $model->filterItens($query, json_decode(base64_decode($arr->filter ?? $_GET['d']))->filters);

        $queryO = clone $query;

        $query = $query->limit($total + 100);

        foreach ($model->getOrder() as $order) {
            $query = $query->orderBy($order[0], $order[1]);
        }

        foreach ($query->xselect($config, $auth) as $k) {

            $row = [];

            foreach ($model->getFields() as $key => $value) {

                if (!$value['protected']) {

                    if (isset($value['choices'])) {

                        $row[] = $value['choices'][$k->$key] ?? $k->$key;

                    } elseif (isset($value['foreign_key'])
                        && (isset($value['foreign_key_show'])
                            && $value['foreign_key_show'])) {

                        $nome = $model_name . '_FK_' . $value['foreign_key'];

                        if (!isset($GLOBALS[$nome])) {

                            $m = $this->createModelFromName($value['foreign_key']);
                            $m_arr = array();

                            $field_id = $m->getFieldId();

                            foreach ($m->filter($field_id, $queryO->clear()->groupBy($key))->xselect($this->_config, $this->_auth) as $m_ret)
                                $m_arr[$m_ret->$field_id] = strval($m_ret);

                            $GLOBALS[$nome] = $m_arr;

                        }

                        if ($k->$key === '') {

                            $row[] = '';

                        } else {

                            $row[] = $GLOBALS[$nome][$k->$key] ?? ($k->$key . ' - #ERROR#');

                        }

                    } else {

                        $row[] = ($value['type'] == 'string' && is_numeric($k->$key)) ? ('\'' . $k->$key) : $k->$key;

                    }

                }

            }

            $excel->addRow($row);

        }

        $excel->finalize();

        // ZIP

        $zip = new ZipArchive();
        $zip_name = $baseDir . 'uploads/' . $this->_auth->getUser()->login . '_' . time() . ".zip";
        $zip->open($zip_name, ZipArchive::CREATE);

        $zip->addFile($baseDir . 'uploads/' . $excelTempFileName, iconv("UTF-8", "CP850", str_replace('/', '-', $model->getVerboseName())) . '.xls');
        $zip->close();

        unlink($baseDir . 'uploads/' . $excelTempFileName);

        // Download

        header('Content-type: application/zip charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.str_replace('/', '-', $model->getVerboseNamePlural()).'.zip"');
        header('Content-Length: ' . filesize($zip_name));

        readfile($zip_name);
        unlink($zip_name);

    }

    public function excelFormatted($arr, $total)
    {

        $config = $this->_config;
        $auth = $this->_auth;

        $model_name = $arr->namespace . '\\' . Application::strToClass($arr->model);

        $model = $this->createModelFromRoute($arr, 'view');

        $model->changeState(ModelOld::STATE_EXCEL);

        try {

            $excel = new Excel\Spreadsheet();

            $sheet = $excel->getActiveSheet();

            $sheet->setTitle($arr->model);

            // Headers

            $c = 1;

            foreach ($model->getFields() as $value) {

                if (!$value['protected']) {

                    $sheet->getStyleByColumnAndRow($c, 1)->getFont()->setBold(true);
                    $sheet->getStyleByColumnAndRow($c, 1)->getAlignment()->setHorizontal('center');
                    $sheet->setCellValueByColumnAndRow($c, 1, preg_replace('/<[^>]*>/', '', $value['label']));

                    $c++;

                }

            }

            // Results

            $query = $model->query();

            $query = $model->filterItens($query, json_decode(base64_decode($arr->filter ?? $_GET['d']))->filters);

            $queryO = clone $query;

            $query = $query->limit($total + 100);

            foreach ($model->getOrder() as $order) {
                $query = $query->orderBy($order[0], $order[1]);
            }

            $r = 2;

            foreach ($query->xselect($config, $auth) as $k) {

                $c = 1;

                foreach ($model->getFields() as $key => $value) {

                    if (!$value['protected']) {

                        if (!is_null($k->$key)) {

                            switch ($value['type']) {

                                case 'float':

                                    $sheet->setCellValueByColumnAndRow($c, $r, $k->$key);
                                    $sheet->getStyleByColumnAndRow($c, $r)->getNumberFormat()->setFormatCode('#,##0.00');

                                    break;

                                case 'date':

                                    $t = strtotime($k->$key);

                                    $sheet->setCellValueByColumnAndRow($c, $r, Excel\Shared\Date::FormattedPHPToExcel(date('Y', $t), date('m', $t), date('d', $t)));
                                    $sheet->getStyleByColumnAndRow($c, $r)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                                    $sheet->getStyleByColumnAndRow($c, $r)->getAlignment()->setHorizontal('center');

                                    break;

                                case 'datetime':

                                    $t = strtotime($k->$key);

                                    $sheet->setCellValueByColumnAndRow($c, $r, Excel\Shared\Date::FormattedPHPToExcel(date('Y', $t), date('m', $t), date('d', $t), date('H', $t), date('i', $t), date('s', $t)));
                                    $sheet->getStyleByColumnAndRow($c, $r)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm:ss');
                                    $sheet->getStyleByColumnAndRow($c, $r)->getAlignment()->setHorizontal('center');

                                    break;

                                case 'boolean':

                                    $sheet->setCellValueByColumnAndRow($c, $r, ($k->$key) ? 'S' : 'N');
                                    $sheet->getStyleByColumnAndRow($c, $r)->getAlignment()->setHorizontal('center');

                                    break;

                                /*case 'upload':

                                    $files = count($k->$key);

                                    switch ($files) {

                                        case 1:

                                            $richText2 = new Excel\RichText\RichText();
                                            $blue = $richText2->createTextRun($k->{$key}[0]->name);
                                            $blue->getFont()->setUnderline(true);
                                            $blue->getFont()->setColor(new Excel\Style\Color(Excel\Style\Color::COLOR_BLUE));

                                            $sheet->setCellValueByColumnAndRow($c, $r, $richText2);

                                            $sheet->getCellByColumnAndRow($c, $r)
                                                ->getHyperlink()
                                                ->setUrl($config->getBaseHref() . $arr->group . '/' . $arr->model . '/download/' . $k->{$key}[0]->file)
                                                ->setTooltip('Acessar o arquivo');

                                            break;

                                        default:
                                            $sheet->setCellValueByColumnAndRow($c, $r, $files . ' arquivos');

                                    }

                                    $sheet->getStyleByColumnAndRow($c, $r)
                                        ->getAlignment()
                                        ->setHorizontal(Excel\Style\Alignment::HORIZONTAL_LEFT);

                                    break;*/

                                default:

                                    if (isset($value['choices'])) {

                                        if (isset($value['choices_colors'])
                                            && isset($value['choices_colors'][$k->$key])) {

                                            $color = $value['choices_colors'][$k->$key];

                                            $rt = new Excel\RichText\RichText();
                                            $cor = $rt->createTextRun($value['choices'][$k->$key] ?? $k->$key);

                                            if (isset(ModelOld::COLOR_MAP[$color])) {

                                                $cor->getFont()->setColor(new Excel\Style\Color('FF' . strtoupper(ModelOld::COLOR_MAP[$color])));

                                            }

                                            $sheet->setCellValueByColumnAndRow($c, $r, $rt);

                                        } else {

                                            $sheet->setCellValueByColumnAndRow($c, $r, $value['choices'][$k->$key] ?? $k->$key);

                                        }

                                    } elseif (isset($value['foreign_key'])
                                        && (isset($value['foreign_key_show'])
                                            && $value['foreign_key_show'])) {

                                        $nome = $model_name . '_FK_' . $value['foreign_key'];

                                        if (!isset($GLOBALS[$nome])) {

                                            $m = $this->createModelFromName($value['foreign_key']);
                                            $m_arr = array();

                                            $field_id = $m->getFieldId();

                                            foreach ($m->filter($field_id, $queryO->clear()->groupBy($key))->xselect($this->_config, $this->_auth) as $m_ret)
                                                $m_arr[$m_ret->$field_id] = strval($m_ret);

                                            $GLOBALS[$nome] = $m_arr;

                                        }

                                        if ($k->$key === '') {

                                            $sheet->setCellValueByColumnAndRow($c, $r, null);

                                        } else {

                                            $sheet->setCellValueByColumnAndRow($c, $r, (isset($GLOBALS[$nome][$k->$key])) ? $GLOBALS[$nome][$k->$key] : $k->$key . ' - #ERROR#');

                                        }

                                    } else {

                                        $sheet->setCellValueByColumnAndRow($c, $r, ($value['type'] == 'string' && is_numeric($k->$key)) ? ('\'' . $k->$key) : $k->$key);

                                    }

                                    $sheet->getStyleByColumnAndRow($c, $r)->getAlignment()->setHorizontal(Excel\Style\Alignment::HORIZONTAL_LEFT);

                            }

                        }

                        $c++;

                    }

                }

                $r++;

            }

            // AutoSize

            $c = 1;

            foreach ($model->getFields() as $value) {

                if (!$value['protected']) {

                    $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);

                    $c++;

                }

            }

            // AutoFilter on Headers

            $sheet->setAutoFilter('A1:' . Excel\Cell\Coordinate::stringFromColumnIndex($c - 1) . '1');

            // Download

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $model->getVerboseNamePlural() . '.xlsx"');

            $objWriter = Excel\IOFactory::createWriter($excel, 'Xlsx');

            $objWriter->save('php://output');

        } catch (Exception $e) {

            Application::error($e->getMessage());

        }

    }

    /*
     * GENÉRICOS
     * */

    public function createModelFromRoute($arr, string $permission = 'view'): ModelOld
    {

        $model_name = $arr->namespace . '\\' . Application::strToClass($arr->model);

        if (!class_exists($model_name)) {

            Application::error("Classe <b>$model_name</b> não encontrada!", 404);

        }

        $m = $this->createModelFromName($model_name);

        $this->_auth->testPermission($m, $permission);

        return $m;

    }

    public function createModelFromName($name): ModelOld
    {

        return new $name($this->_config, $this->_auth);

    }

    public function initialize($name, $default = null, $obj = null)
    {

        $class = get_called_class();

        if (is_null($obj)) {
            $obj = Application::inputStream();
        }

        if (isset($obj->$name)) {
            $default = $obj->$name;
        }

        if (!isset($obj->$name) && isset($_SESSION[$class . '_' . $name])) {
            $default = $_SESSION[$class . '_' . $name];
        }

        $_SESSION[$class . '_' . $name] = $default;

        return $default;

    }

    public function chosenArray($query, $id_type = 'id', $method = '__toString'): array
    {

        $arr = [];

        if (is_array($query)) {

            foreach ($query as $k => $v) {

                if (!is_null($k) && $id_type == 'string') {
                    $k = strval($k);
                }

                $arr[] = ['id' => ($k === '') ? null : $k, 'label' => $v];

            }

        } else {

            foreach ($query->xselect($this->_config, $this->_auth) as $k) {

                $arr[] = ['id' => $k->$id_type, 'label' => $k->$method()];

            }

        }

        return $arr;

    }

}
