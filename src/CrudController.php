<?php
namespace Fluxion;

use Psr\Http\Message\{MessageInterface, RequestInterface};

class CrudController extends Controller
{

    public function home(): MessageInterface
    {
        return ResponseFactory::fromText('HOME');
    }

    /**
     * @throws Exception
     */
    public function createRoutes(string $base_url, Model $model, Controller $controller): void
    {

        $class = get_called_class();

        $base_url = $controller->getBaseRoute() . $base_url;

        $list = [
            ['url' => '', 'method' => 'GET', 'class' => $class, 'action' => 'home'],
            ['url' => '/data', 'method' => 'POST', 'class' => $class, 'action' => 'data'],
        ];

        $primary_keys = $model->getPrimaryKeys();

        $keys = [];

        foreach ($model->getPrimaryKeys() as $key => $pk) {

            if ($pk->getType() == 'integer') {
                $keys[] = '{' . "$key:int}";
            }

            else {
                $keys[] = '{' . "$key:string}";
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

    public function hasPermission(Model $model, Permission $permission, RequestInterface $request): bool
    {
        return true;
    }

    public function permissionFilter(Query $query, RequestInterface $request): Query
    {
        return $query;
    }

    /**
     * @throws Exception
     */
    public function data(RequestInterface $request, Route $route): MessageInterface
    {

        $is = json_decode($request->getBody()->getContents());

        $model = $route->getModel();

        $permissions = [];

        $permissions['download'] = $this->hasPermission($model, Permission::DOWNLOAD, $request);
        $permissions['insert'] = true;
        $permissions['delete'] = true;
        $permissions['update'] = true;
        $permissions['view'] = true;
        $permissions['under'] = true;
        $permissions['special'] = true;

        $data = [];

        $query = $this->permissionFilter($model->query(), $request);

        /** @var Model $k */
        foreach ($query->select() as $k) {

            $data[] = [
                'id' => $k->id(),
                'title' => $k->nome ?? 'NOME',//$this->title(),
                'subtitle' => $k->id ?? 'ID',//$this->subtitle(),
                'extras' => [],//$this->extras(),
                'tags' => [],//$this->tags(),
                'actions' => [],//$this->actions(),
                'update' => null,//$this->updateInfo(),
            ];

        }

        $json = [
            'refresh' => 50000,//$model->getRefreshTime(),
            'title' => 'Título',//$model->pageTitle(),
            'html_title' => 'HTML Título',//$html_title,
            'subtitle' => 'Subtítulo',//$model->pageSubtitle(),
            'description' => 'Descrição',//$model->pageDescription(),
            'not_found_message' => 'Nenhum registro encontrado',//$model->notFoundMessage(),
            'has_search' => true,//$model->hasSearch(),
            'search_placeholder' => 'Buscar...',//$model->getSearchPlaceholder(),
            'update_title' => 'Criado em',//$model->updateTitle(),
            'update_format' => 'dd/MM/y HH:mm',//$model->updateFormat(),
            'order' => 0,//$order,
            'orders' => [],//$model->orders(),
            'tab' => null,//$tab,
            'tabs' => [],//$tabs,
            'page' => 1,//$page,
            'pages' => 5,//$pages,
            'itens_per_page' => 20,//$model->getIpp(),
            'permissions' => $permissions,
            'filters' => [],//$model->filters($is->filters ?? new stdClass()),
            'data' => $data,
            'request_data' => $is,
        ];

        return ResponseFactory::fromJson($json);

    }

}
