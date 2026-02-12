<?php
namespace Fluxion;

use stdClass;
use ReflectionException;
use Fluxion\Menu\{MenuGroup};
use Fluxion\Exception\{PermissionDeniedException};
use Psr\Http\Message\{MessageInterface, RequestInterface};

class CrudController extends Controller
{

    /**
     * @throws PermissionDeniedException
     * @throws Exception
     */
    protected function getModel(Model $model, string $id): Model
    {

        # Dados básicos

        $auth = Config::getAuth();

        # Permissões do usuário

        if ($id == 'add') {

            if (!$auth->hasPermission($model, Permission::INSERT)) {
                throw new PermissionDeniedException('Usuário sem acesso à inclusão!');
            }

        }

        else {

            if (!$auth->hasPermission($model, Permission::VIEW)) {
                throw new PermissionDeniedException('Usuário sem acesso à visualização!');
            }

            $model = $model::loadById($id);

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
     * @throws Exception
     * @throws ReflectionException
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

        $query = $this->permissionFilter($model::query(), $auth);

        $tabs = $model->getTabs(clone $query);
        $default_tab = $tabs[0]->id ?? null;

        $model->changeState(State::FILTER);

        // Executa busca
        if (!empty($search)) {

            $query = $model->search($query, $search);

        }

        // Executa filtros
        else {

            $query = $model->filterItens($query, $filters);
            $query = $model->tab($query, $tab, $default_tab);

        }

        $filters = $model->getFilters(clone $query, $filters);

        $query = $model->order($query, $order);

        $query = $query->paginate(
            page: $page,
            pages: $pages,
            itens: $crud_details->itens_per_page
        );

        $model->changeState(State::LIST);

        /** @var Model $k */
        foreach ($query->select() as $k) {

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

        # Campos

        $fields = $model->getFormFields($save);

        # Inlines

        $inlines = $model->getFormInlines($save);

        # Retorna dados

        $crud_details = $model->getCrud();

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

    /**
     * @throws PermissionDeniedException
     * @throws Exception
     */
    #[Transaction]
    public function save(RequestInterface $request, Route $route): MessageInterface
    {

        $is = json_decode($request->getBody()->getContents());

        $id = $is->__grupo ?? null;

        # Dados básicos

        $auth = Config::getAuth();
        $model = $this->getModel($route->getModel(), $id);

        # Verificar se tem permissão para salvar

        $save = ($id == 'add')
            ? $auth->hasPermission($model, Permission::INSERT)
            : $auth->hasPermission($model, Permission::UPDATE);

        if (!$save) {
            throw new PermissionDeniedException('Usuário sem acesso à salvar os dados!');
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
     * @throws PermissionDeniedException
     * @throws Exception
     */
    #[Transaction]
    public function action(RequestInterface $request, Route $route): MessageInterface
    {

        $is = json_decode($request->getBody()->getContents());

        $id = $is->id ?? null;

        # Dados básicos

        $model = $this->getModel($route->getModel(), $id);

        # Executa a ação

        $model->executeAction(Action::from($is->action ?? ''));

        # Retorna dados

        $json = [
            'type' => 'refresh',
            'ok' => true,
            'id' => $model->id(),
        ];

        return ResponseFactory::fromJson($json);

    }

}
