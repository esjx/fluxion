<?php
namespace Fluxion;

use stdClass;
use Fluxion\Exception\{PermissionDeniedException};
use Fluxion\Menu\{MenuGroup};
use Psr\Http\Message\{MessageInterface, RequestInterface};

class CrudController extends Controller
{

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public function home(RequestInterface $request): MessageInterface
    {
        return ResponseFactory::fromText('HOME');
    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    public function createRoutes(string $base_url,
                                 Model $model,
                                 Controller $controller,
                                 ?MenuGroup $menu = null,
                                 ?Auth $auth = null): void
    {

        $class = get_called_class();

        $base_url = $controller->getBaseRoute() . $base_url;
        $crud_details = $model->getCrud();

        $menu?->addSub(new Menu\MenuItem(
            title: $crud_details->plural_title,
            route: $base_url,
            visible: $auth?->hasPermission($model, Permission::LIST) ?? false)
        );

        $list = [
            ['url' => '', 'method' => 'GET', 'class' => $class, 'action' => 'home'],
            ['url' => '/add', 'method' => 'GET', 'class' => $class, 'action' => 'home'],
            ['url' => '/data', 'method' => 'POST', 'class' => $class, 'action' => 'data'],
            ['url' => '/fields', 'method' => 'POST', 'class' => $class, 'action' => 'fields'],
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
                'action' => 'home',
            ];

        }

        foreach ($list as $item) {

            $route = new Route(
                route: $base_url . $item['url'],
                methods: $item['method'],
                args: ['model' => $model]
            );

            $route->setClass($item['class']);
            $route->setMethod($item['action']);
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
     * @throws Exception
     */
    public function data(RequestInterface $request, Route $route): MessageInterface
    {

        # Dados básicos

        $auth = Config::getAuth($request);
        $model = $route->getModel();
        $model->changeState(State::VIEW);

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

        if (!$auth->hasPermission($model, Permission::LIST)) {
            throw new PermissionDeniedException('Usuário sem acesso à visualização!');
        }

        $permissions = [];

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

        $query = $this->permissionFilter($model->query(), $auth);

        // Executa busca
        if (!empty($search)) {

            $query = $model->search($query, $search);

        }

        // Executa filtros
        else {

            $query = $model->filterItens($query, $filters);
            $query = $model->tab($query, $order);

        }

        $tabs = $model->getTabs(clone $query);

        $filters = $model->getFilters(clone $query, $filters);

        $query = $model->order($query, $order);

        $query = $query->paginate(
            page: $page,
            pages: $pages,
            itens: $crud_details->itens_per_page
        );

        /** @var Model $k */
        foreach ($query->select() as $k) {

            $data[] = [
                'id' => $k->id(),
                'title' => $k->title(),
                'subtitle' => $k->subtitle(),
                'extras' => $k->extras(),
                'tags' => $k->getTags(),
                'actions' => $k->getActions($auth),
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
            'itens_per_page' => $crud_details->itens_per_page,
            'permissions' => $permissions,
            'filters' => $filters,
            'data' => $data,
        ];

        return ResponseFactory::fromJson($json);

    }

    /**
     * @throws Exception
     */
    public function fields(RequestInterface $request, Route $route): MessageInterface
    {

        # Dados básicos

        $auth = Config::getAuth($request);
        $model = $route->getModel();

        # Dados da requisição

        $is = json_decode($request->getBody()->getContents());

        # Permissões do usuário

        if ($is->__id == 'add') {

            $save = $auth->hasPermission($model, Permission::INSERT);

            if (!$save) {
                throw new PermissionDeniedException('Usuário sem acesso à inclusão!');
            }

        }

        else {

            if (!$auth->hasPermission($model, Permission::VIEW)) {
                throw new PermissionDeniedException('Usuário sem acesso à visualização!');
            }

            $save = $auth->hasPermission($model, Permission::UPDATE);

            $ids = explode(';', $is->__id);

            $args = array_map(function () use (&$ids) {
                return array_shift($ids);
            }, $model->getPrimaryKeys());

            $model = $model::loadById($args);

        }

        $model->changeState(State::VIEW);

        $crud_details = $model->getCrud();
        $table = $model->getTable();

        $save = (!$table->view && $save);

        # Campos

        $fields = [];

        foreach ($model->getFields() as $f) {

            if ($f->protected) {
                continue;
            }

            $form_field = $f->getFormField();

            if (in_array($form_field->type, ['choices', 'colors'])) {
                $form_field->choices = $form_field->getChoices();
            }

            if (!$save) {
                $form_field->enabled = false;
            }

            $fields[] = $form_field;

        }

        # Inlines

        $inlines = [];

        #TODO

        # Retorna dados

        $json = [
            'size' => $crud_details->form_size,
            'header' => $model->getFormHeader(),
            'footer' => $model->getFormFooter(),
            'title' => $crud_details->title,
            'fields' => $fields,
            'inlines' => $inlines,
            'html_title' => $crud_details->title,
            'save' => $save,
            '$is' => $is,
        ];

        return ResponseFactory::fromJson($json);

    }

}
