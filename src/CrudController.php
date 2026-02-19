<?php
namespace Fluxion;

use Fluxion\Database\Field\ColorField;
use stdClass;
use ReflectionException;
use Fluxion\Menu\{MenuGroup};
use Fluxion\Query\{QuerySql};
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

            if (!$model->isSaved()) {
                throw new Exception("Dados não encontrados para o ID '$id'!");
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
     * @throws PermissionDeniedException
     * @throws Exception
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
            ['url' => '/typeahead/{field:string}', 'method' => 'POST', 'class' => $class, 'action' => 'typeahead'],
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

        $filters = $model->getFilters($model::query(), $filters);

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
     * @throws ReflectionException
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
     * @noinspection PhpUnused
     */
    #[Transaction]
    public function action(RequestInterface $request, Route $route): MessageInterface
    {

        $is = json_decode($request->getBody()->getContents());

        $id = $is->__id ?? null;

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

    /**
     * @throws PermissionDeniedException
     * @throws Exception
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
            throw new Exception("Campo '$args->field' não possui fonte de dados!");
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

        foreach ($query->limit(10)->select() as $item) {

            $choices[] = [
                'id' => $item->$ref_field_id,
                'label' => (string) $item,
                'color' => Color::tryFrom($item->$field_color_name ?? ''),
            ];

        }

        # Retorna dados

        $json = [
            'items' => $choices,
        ];

        return ResponseFactory::fromJson($json);

    }

}
