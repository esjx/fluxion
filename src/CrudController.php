<?php
namespace Fluxion;

use stdClass;
use RuntimeException;
use InvalidArgumentException;
use Fluxion\Menu\{MenuGroup};
use Fluxion\Query\{QuerySql};
use Fluxion\Exception\{PermissionDeniedFluxionException};
use Fluxion\Database\Field\{ForeignKeyField, ManyToManyField, ColorField};
use Psr\Http\Message\{MessageInterface, RequestInterface, UploadedFileInterface};

/**
 * @noinspection PhpUnused
 */
class CrudController extends Controller
{

    /**
     * @throws PermissionDeniedFluxionException
     * @throws FluxionException
     */
    protected function getModel(Model $model, string $id): Model
    {

        # Dados básicos

        $auth = Config::getAuth();

        # Permissões do usuário

        if ($id == 'add') {

            if (!$auth->hasPermission($model, Permission::INSERT)) {
                throw new PermissionDeniedFluxionException('Usuário sem acesso à inclusão!');
            }

        }

        else {

            if (!$auth->hasPermission($model, Permission::VIEW)) {
                throw new PermissionDeniedFluxionException('Usuário sem acesso à visualização!');
            }

            $model = $model::loadById($id);

            if (!$model->isSaved()) {
                throw new FluxionException("Dados não encontrados para o ID '$id'!");
            }

        }

        return $model;

    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public function home(RequestInterface $request): MessageInterface
    {
        return ResponseFactory::fromText('HOME');
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @throws PermissionDeniedFluxionException
     * @throws FluxionException
     */
    public function edit(RequestInterface $request, Route $route, stdClass $args): MessageInterface
    {

        $ids = [];
        foreach ($route->getModel()->getPrimaryKeys() as $key => $pk) {
            $ids[] = $args->$key;
        }

        $this->getModel($route->getModel(), implode(';', $ids));

        return $this->home($request);

    }

    /**
     * @throws FluxionException
     * @noinspection PhpUnused
     */
    public function createRoutes(string $base_url,
                                 Model $model,
                                 Controller $controller,
                                 ?MenuGroup $menu = null,
                                 ?Auth $auth = null): void
    {

        $class = get_called_class();

        $crud_details = $model->getCrud();

        $visible = false;

        if ($auth?->hasPermission($model, Permission::LIST) ?? false) {
            $visible = true;
        }

        if ($auth?->hasPermission($model, Permission::LIST_UNDER) ?? false) {
            $visible = true;
        }

        if ($auth?->hasPermission($model, Permission::LIST_ALL) ?? false) {
            $visible = true;
        }

        $menu?->addSub(new Menu\MenuItem(
            title: $crud_details->plural_title,
            route: $base_url,
            visible: $visible
            ));

        $list = [
            ['url' => '', 'method' => 'GET', 'class' => $class, 'action' => 'home'],
            ['url' => '/add', 'method' => 'GET', 'class' => $class, 'action' => 'home'],
            ['url' => '/data', 'method' => 'POST', 'class' => $class, 'action' => 'data'],
            ['url' => '/fields', 'method' => 'POST', 'class' => $class, 'action' => 'fields'],
            ['url' => '/save', 'method' => 'POST', 'class' => $class, 'action' => 'save'],
            ['url' => '/action', 'method' => 'POST', 'class' => $class, 'action' => 'action'],
            ['url' => '/action-fields', 'method' => 'POST', 'class' => $class, 'action' => 'actionFields'],
            ['url' => '/typeahead/{field:string}', 'method' => 'POST', 'class' => $class, 'action' => 'typeahead'],
            ['url' => '/download', 'method' => 'GET', 'class' => $class, 'action' => 'download'],
            ['url' => '/upload', 'method' => 'POST', 'class' => $class, 'action' => 'upload'],
            ['url' => '/download/?d={filter:string}', 'method' => 'GET', 'class' => $class, 'action' => 'download'],
        ];

        $keys = [];

        foreach ($model->getPrimaryKeys() as $key => $pk) {

            if ($pk->getType() == 'integer') {
                $keys[] = "{{$key}:int}";
            }

            else {
                $keys[] = "{{$key}:string}";
            }

        }

        if (count($keys) > 0) {

            $list[] = [
                'url' => "/" . implode(';', $keys),
                'method' => 'GET',
                'class' => $class,
                'action' => 'edit',
            ];

        }

        foreach ($list as $item) {

            $route = new Route(
                route: $base_url . $item['url'],
                methods: $item['method'],
                args: ['model' => $model]
            );

            $route->setClassMethod($item['class'], $item['action']);
            $route->setModel($model);

            $controller->addRoute($route);

        }

    }

    /** @noinspection PhpUnusedParameterInspection */
    public function permissionFilter(Query $query, Auth $auth): Query
    {
        return $query;
    }

    /**
     * @throws FluxionException
     */
    public function data(RequestInterface $request, Route $route): MessageInterface
    {

        # Dados básicos

        $auth = Config::getAuth();
        $model = $route->getModel();

        $crud_details = $model->getCrud();
        $class_name = $model->getComment();
        $has_search = false;

        # Dados da requisição

        $is = json_decode($request->getBody()->getContents());

        $page = $is->page ?? 1;
        $pages = $page;
        $order = $is->order ?? null;
        $tab = $is->tab ?? null;
        $filters = $is->filters ?? new stdClass();
        $search = trim($is->search ?? '');

        # Permissões do usuário

        if (!$auth->hasPermission($model, Permission::LIST)
            && !$auth->hasPermission($model, Permission::LIST_UNDER)
            && !$auth->hasPermission($model, Permission::LIST_ALL)) {

            throw new PermissionDeniedFluxionException('Usuário sem acesso à visualização!');

        }

        $permissions = [];

        $permissions['upload'] = $auth->hasPermission($model, Permission::UPLOAD);
        $permissions['download'] = $auth->hasPermission($model, Permission::DOWNLOAD);
        $permissions['insert'] = $auth->hasPermission($model, Permission::INSERT);
        $permissions['delete'] = $auth->hasPermission($model, Permission::DELETE);
        $permissions['view'] = $auth->hasPermission($model, Permission::LIST);
        $permissions['update'] = $auth->hasPermission($model, Permission::VIEW);
        $permissions['under'] = $auth->hasPermission($model, Permission::LIST_UNDER);
        $permissions['special'] = $auth->hasPermission($model, Permission::LIST_ALL);

        # Filtros e campos de busca

        foreach ($model->getDetails() as $detail) {

            if ($detail->searchable) {
                $has_search = true;
                break;
            }

        }

        # Executa a busca

        $data = [];

        $query = $this->permissionFilter($model::query(), $auth);

        $query = $model->preFilter($query);

        $model->changeState(State::FILTER);

        // Executa busca
        if (!empty($search)) {

            $query = $model->search($query, $search);

            $tabs = $model->getTabs(clone $query);

        }

        // Executa filtros
        else {

            $query = $model->filterItens($query, $filters);

            $tabs = $model->getTabs(clone $query);
            $default_tab = $tabs[0]->id ?? null;

            $query = $model->tab($query, $tab, $default_tab);

        }

        $filters = $model->getFilters($model->preFilter($model::query()), $filters);

        $query = $model->order($query, $order);

        $model->setActiveOrder($order);

        $query = $query->paginate(
            page: $page,
            pages: $pages,
            items: $crud_details->items_per_page
        );

        $model->changeState(State::LIST);

        /** @var Model $k */
        foreach ($query->select() as $k) {

            $k->changeState(State::LIST);

            $k->setActiveOrder($order);

            $data[] = [
                'id' => $k->id(),
                'title' => $k->title(),
                'subtitle' => $k->subtitle(),
                'extras' => $k->extras(),
                'tags' => $k->getTags(),
                'actions' => $k->getActions(),
                'update' => $k->updateInfo(),
            ];

        }

        # Retorna dados

        $json = [
            'refresh' => $crud_details->refresh_time,
            'title' => $crud_details->plural_title ?? $class_name,
            'html_title' => $crud_details->plural_title ?? $class_name,
            'subtitle' => $crud_details->subtitle,
            'description' => $crud_details->description,
            'not_found_message' => $crud_details->not_found_message,
            'has_search' => $has_search,
            'search_placeholder' => $crud_details->search_placeholder,
            'update_title' => $crud_details->update_title,
            'update_format' => $crud_details->update_format,
            'order' => $order,
            'orders' => $model->getOrders(),
            'tab' => $tab,
            'tabs' => $tabs,
            'page' => $page,
            'pages' => $pages,
            'items_per_page' => $crud_details->items_per_page,
            'permissions' => $permissions,
            'filters' => $filters,
            'data' => $data,
        ];

        return ResponseFactory::fromJson($json);

    }

    /**
     * @throws FluxionException
     */
    public function fields(RequestInterface $request, Route $route): MessageInterface
    {

        $is = json_decode($request->getBody()->getContents());

        $id = $is->__id ?? null;

        # Dados básicos

        $auth = Config::getAuth();
        $model = $this->getModel($route->getModel(), $id);

        $model->changeState(State::VIEW);

        # Verificar se habilita o campo de salvar

        $save = ($id == 'add')
            ? $auth->hasPermission($model, Permission::INSERT)
            : $auth->hasPermission($model, Permission::UPDATE);

        $table = $model->getTable();

        $save = (!$table->view && $save);

        $base_route = preg_replace('/\/fields$/', '', $route->route);

        # Campos

        $fields = $model->getFormFields($save, $base_route);

        # Inlines

        $inlines = $model->getFormInlines($save, $base_route);

        # Retorna dados

        $crud_details = $model->getCrud();

        $json = [
            'size' => $crud_details->form_size,
            'header' => $model->getFormHeader(),
            'footer' => $model->getFormFooter(),
            'title' => strip_tags($model->getFormTitle()),
            'subtitle' => $crud_details->subtitle,
            'fields' => $fields,
            'inlines' => $inlines,
            'html_title' => $crud_details->title,
            'save' => $save,
        ];

        return ResponseFactory::fromJson($json);

    }

    /**
     * @throws FluxionException
     */
    public function upload(RequestInterface $request, Route $route): MessageInterface
    {

        # Dados básicos

        set_time_limit(-1);

        $model = $route->getModel();

        $model->changeState(State::UPLOAD);

        # Upload

        $uploadedFiles = $request->getUploadedFiles();

        if (!array_key_exists('file', $uploadedFiles)) {
            throw new FluxionException("Arquivo não identificado.");
        }

        /* @var $file UploadedFileInterface */
        $file = $uploadedFiles['file'];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new FluxionException("File upload failed with error code: " . $file->getError());
        }

        $uploadDirectory = Config::mapUploadDir('temp/');

        FileManager::createDir($uploadDirectory);

        $clientFilename = $file->getClientFilename();
        //$clientMediaType = $file->getClientMediaType();

        try {

            $targetPath = $uploadDirectory . basename($clientFilename);
            $file->moveTo($targetPath);

            $model->upload($targetPath);

            if (file_exists($targetPath)) {
                unlink($targetPath);
            }

        }

        catch (InvalidArgumentException $e) {
            throw new FluxionException("Invalid target path: " . $e->getMessage());
        }

        catch (RuntimeException $e) {
            throw new FluxionException("Failed to move uploaded file: " . $e->getMessage());
        }

        # Retorna dados

        $json = [
            'ok' => true,
        ];

        return ResponseFactory::fromJson($json);

    }

    /**
     * @throws PermissionDeniedFluxionException
     * @throws FluxionException
     */
    #[Transaction]
    public function save(RequestInterface $request, Route $route): MessageInterface
    {

        $is = json_decode($request->getBody()->getContents());

        $id = $is->__id ?? null;

        # Dados básicos

        $auth = Config::getAuth();
        $model = $this->getModel($route->getModel(), $id);

        # Verificar se tem permissão para salvar

        $save = ($id == 'add')
            ? $auth->hasPermission($model, Permission::INSERT)
            : $auth->hasPermission($model, Permission::UPDATE);

        if (!$save) {
            throw new PermissionDeniedFluxionException('Usuário sem acesso à salvar os dados!');
        }

        # Salva os dados

        $model->saveFromForm($is);

        # Retorna dados

        $json = [
            'type' => 'refresh',
            'ok' => true,
            'id' => $model->id(),
        ];

        return ResponseFactory::fromJson($json);

    }

    /**
     * @throws PermissionDeniedFluxionException
     * @throws FluxionException
     * @noinspection PhpUnused
     */
    #[Transaction]
    public function action(RequestInterface $request, Route $route): MessageInterface
    {

        $is = json_decode($request->getBody()->getContents());

        $id = $is->__id ?? null;
        $action = Action::from($is->action ?? '')
            ?? throw new FluxionException('Ação não identificada!');

        # Dados básicos

        $model = $this->getModel($route->getModel(), $id);

        # Executa a ação

        $model->executeAction($action);

        # Retorna dados

        $json = [
            'type' => 'refresh',
            'ok' => true,
            'id' => $model->id(),
        ];

        return ResponseFactory::fromJson($json);

    }

    /**
     * @throws FluxionException
     * @noinspection PhpUnused
     */
    public function actionFields(RequestInterface $request, Route $route): MessageInterface
    {

        $is = json_decode($request->getBody()->getContents());

        $id = $is->__id ?? null;
        $action = Action::from($is->action ?? '')
            ?? throw new FluxionException('Ação não identificada!');

        # Dados básicos

        $model = $this->getModel($route->getModel(), $id);

        $model->changeState(State::ACTION);

        # Verificar se habilita o campo de salvar

        $save = false;

        # Campos

        $fields = $model->getActionFields($save, $action);

        # Retorna dados

        $json = [
            'size' => $model->getActionFormSize($action),
            'header' => $model->getFormHeader($action),
            'footer' => $model->getFormFooter($action),
            'title' => $model->getActionFormTitle($action),
            'subtitle' => $model->getActionFormSubtitle($action),
            'fields' => $fields,
            'inlines' => [],
            'html_title' => $model->getCrud()->plural_title,
            'save' => $save,
        ];

        return ResponseFactory::fromJson($json);

    }

    /**
     * @throws PermissionDeniedFluxionException
     * @throws FluxionException
     * @noinspection PhpUnused
     */
    public function typeahead(RequestInterface $request, Route $route, stdClass $args): MessageInterface
    {

        # Dados da requisição

        $is = json_decode($request->getBody()->getContents());

        # Dados básicos

        $model = $route->getModel();

        $field = $model->getField($args->field);

        if (!$field->isForeignKey() && !$field->isManyToMany()) {
            throw new FluxionException("Campo '$args->field' não possui fonte de dados!");
        }

        $ref_model = $field->getReferenceModel();

        $ref_field_id = $ref_model->getFieldId()->getName();

        # Executa a busca

        $query = $ref_model->query();

        if (count($field->filters) > 0) {
            $query = $query->filter(QuerySql::_and($field->filters));
        }

        $query = $ref_model->search($query, $is->search);

        $query = $ref_model->order($query, $order);

        $field_color_name = '';
        foreach ($ref_model->getFields() as $f) {
            if ($f instanceof ColorField) {
                $field_color_name = $f->getName();
                break;
            }
        }

        $choices = [];

        foreach ($query->limit(10)->select() as $row) {

            /** @var Model $row */
            $row->changeState(State::LIST_CHOICE);

            $choices[] = [
                'id' => $row->$ref_field_id,
                'label' => (string) $row,
                'color' => Color::tryFrom($row->$field_color_name ?? ''),
            ];

        }

        # Retorna dados

        $json = [
            'items' => $choices,
        ];

        return ResponseFactory::fromJson($json);

    }

    private function encode(string $string): string
    {

        $encoding = $_ENV['EXPORT_CSV_ENCODING'] ?? 'UTF-8';

        if ($encoding != 'UTF-8') {
            $string = iconv('UTF-8', $encoding, $string);
        }

        return $string;

    }

    protected int $export_limit = 10000;

    /**
     * @throws FluxionException
     */
    public function download(RequestInterface $request, Route $route, stdClass $args): void
    {

        set_time_limit(-1);

        $auth = Config::getAuth();

        $dir = $_ENV['LOCAL_TEMP'] ?? 'temp';

        FileManager::createDir($dir);

        $uid = bin2hex(random_bytes(10));

        $file_name = $auth->getUser()->login . '_' . $uid . '.csv';

        $model = $route->getModel();

        $model->changeState(State::DOWNLOAD);

        $fields = $model->getFields();

        $file = fopen($dir . $file_name, 'w');

        # Consultas

        $query = $this->permissionFilter($model::query(), $auth);

        $obj = new stdClass();

        if (!empty($args->d)) {
            $obj = json_decode(base64_decode($args->d));
        }

        if (!empty($obj->search)) {
            $query = $model->search($query, $obj->search);
        }

        elseif (!empty($obj->filters)) {
            $query = $model->filterItens($query, $obj->filters);
        }

        $quantity = (clone $query)->count()->firstOrNew()->total;

        if ($quantity == 0) {
            fputcsv($file, ['Nenhum registro encontrado!'], ';');
        }

        elseif ($quantity > $this->export_limit) {
            fputcsv($file, ["Quantidade de registros limitada a $this->export_limit!"], ';');
            $query = $query->limit($this->export_limit);
        }

        # Cabeçalho

        $itens = [];
        $cache = [];

        foreach ($fields as $key => $field) {

            $detail = $model->getDetail($key);

            if ($field->protected) {
                continue;
            }

            if ($field instanceof ForeignKeyField) {

                $cache[$key] = [];

                $mn_field_id = $field->getReferenceModel()->getFieldId()->getName();

                foreach ($field->getReferenceModel()::query()
                             ->filter($mn_field_id, (clone $query)->only($key))->select() as $row) {

                    $cache[$key][$row->$mn_field_id] = $this->encode((string) $row);

                }

            }

            elseif ($field instanceof ManyToManyField) {

                $cache[$key] = [];

                $field_id = $model->getFieldId()->getName();
                $mn_field_id = $field->getReferenceModel()->getFieldId()->getName();

                $mn_model = $field->getManyToManyModel();

                $list_right = $mn_model->_query()
                    ->filter($mn_model->getLeft(), (clone $query)->only($field_id))
                    ->groupBy($mn_model->getRight());

                foreach ($field->getReferenceModel()::filter($mn_field_id, $list_right)
                             ->select() as $row) {

                    $cache[$key][$row->$mn_field_id] = $this->encode((string) $row);

                }

            }

            $itens[] = $this->encode($detail->label);

        }

        fputcsv($file, $itens, ';');

        # Dados

        if (!empty($obj->order)) {
            $query = $model->order($query, $obj->order);
        }

        foreach ($query->select() as $row) {

            $row->changeState(State::DOWNLOAD);

            $itens = [];

            foreach ($fields as $key => $field) {

                if ($field->protected) {
                    continue;
                }

                $value = $row->$key;

                if (is_null($value) || $value === '' || $value === []) {
                    $itens[] = '';
                }

                elseif ($field instanceof ForeignKeyField) {
                    $itens[] = $cache[$key][$value] ?? $value;
                }

                elseif ($field instanceof ManyToManyField) {

                    $list = [];

                    foreach ($value as $v) {
                        $list[] = $cache[$key][$v] ?? $v;
                    }

                    $itens[] = implode(', ', $list);

                }

                else {
                    $itens[] = $this->encode($field->getExportValue($value));
                }

            }

            fputcsv($file, $itens, ';');

        }

        fclose($file);

        $title = $model->getCrud()->plural_title;

        $zip_file = FileManager::zipFile($dir . $file_name, $title . '.csv');

        FileManager::downloadFileAndDelete($zip_file, $title . '.zip');

    }

}
