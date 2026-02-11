<?php
namespace Fluxion;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use GuzzleHttp\Psr7\{Response, Utils};
use Psr\Http\Message\{MessageInterface, RequestInterface};
use Micheh\Cache\CacheUtil;

class Controller
{

    /** @var Route[] */
    private array $routes = [];

    private string $base_route;

    public function getBaseRoute(): string
    {
        return $this->base_route;
    }

    /** @return  Route[] */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function addRoute(Route $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @noinspection PhpUnused
     */
    #[Route(route: '/setup', methods: 'GET')]
    public static function setup(): MessageInterface
    {

        $start_time = microtime(true);

        $stream = Utils::streamFor();

        $connector = Config::getConnector();
        $connector->setLogStream($stream);

        $stream->write('<pre>');

        $class_name = get_called_class();

        $stream->write("<b>/* $class_name */</b>\n");

        # Buscando todos os arquivos de Models

        $list = [];

        $ref_controller = new ReflectionClass($class_name);

        $dir = dirname($ref_controller->getFileName());

        foreach (Util::loadAllFiles("$dir/Models") as $file) {

            $file = str_replace($dir, '', $file);
            $file = preg_replace('/\.php$/i', '', $file);
            $file = str_replace(DIRECTORY_SEPARATOR, '\\', $file);

            $class = $ref_controller->getNamespaceName() . $file;

            $ref_model = new ReflectionClass($class);

            if ($ref_model->isSubclassOf(Model::class) && !$ref_model->isAbstract()) {
                if (!(new $class())->getTable()->view) {
                    $list[$class] = -1;
                }
            }

        }

        # Verificando eventuais dependências

        foreach ($list as $class => $index) {

            /** @var Model $model */
            $model = new $class();

            foreach ($model->getForeignKeys() as $foreign_key) {
                if (isset($list[$foreign_key->class_name])) {
                    $list[$foreign_key->class_name] = $list[$class] - 1;
                }
            }

            foreach ($model->getManyToMany() as $many_to_many) {
                if (!$many_to_many->inverted && isset($list[$many_to_many->class_name])) {
                    $list[$many_to_many->class_name] = $list[$class] - 1;
                }
            }

        }

        # Tratando a classe de permissões

        $perm_model = Config::getPermissionModel();

        if (!is_null($perm_model)) {

            $perm_model_class = get_class($perm_model);

            if (array_key_exists($perm_model_class, $list)) {

                unset($list[$perm_model_class]);

                $connector->sync($perm_model_class);

            }

        }

        # Ordenando e executando as sincronizações

        asort($list);
        foreach ($list as $class => $index) {

            $connector->sync($class);

            if (!is_null($perm_model) && get_class($perm_model) != $class) {

                $field_id = $perm_model->getFieldId()->getName();

                $perm_model = $perm_model::loadById($class);

                if (is_null($perm_model->$field_id)) {

                    $perm_model->$field_id = $class;

                    $perm_model->save();

                }

            }

        }

        # Executando scripts SQL

        // TODO

        # Resumo do processo executado

        $time = Formatter::number(microtime(true) - $start_time);

        $memory = Formatter::size(memory_get_usage());

        $stream->write("-- Finalizado em <b>$time segundos</b> utilizando <b>$memory</b>");

        $stream->write('</pre>');

        $response = new Response();

        $util = new CacheUtil();

        $response = $util->withCachePrevention($response);

        return $response->withBody($stream);

    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @noinspection PhpUnusedParameterInspection
     */
    public function __construct(RequestInterface $request)
    {

        $class_name = get_called_class();

        $reflection = new ReflectionClass($class_name);

        # Busca as rotas do Controller

        $routes = $reflection->getAttributes(Route::class);

        $this->base_route = '';

        foreach ($routes as $route) {

            /** @var Route $instance */
            $instance = $route->newInstance();

            $this->base_route = $instance->route;

        }

        # Buscas as rotas dos métodos existentes

        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {

            $routes = $method->getAttributes(Route::class);

            foreach ($routes as $route) {

                /** @var Route $instance */
                $instance = $route->newInstance();

                if ($instance->append) {
                    $instance->route = $this->base_route . $instance->route;
                }

                $instance->setClassMethod($class_name, $method->getName());

                $this->addRoute($instance);

            }

        }

    }

}
