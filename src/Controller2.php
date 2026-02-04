<?php
namespace Fluxion;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Controller2
{

    /** @var Route[] */
    protected array $routes = [];

    /** @return  Route[] */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @throws ReflectionException
     * @throws CustomException
     */
    #[Route(route: '/setup', methods: ['GET'])]
    public static function setup(RequestInterface $request, ResponseInterface $response): void
    {

        $start_time = microtime(true);

        $stream = $response->getBody();

        $connector = Config2::getConnector();
        $connector->setLogStream($stream);

        $stream->write('<pre>');

        $class_name = get_called_class();

        $stream->write("<b>/* $class_name */</b>\n\n");

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

            if ($ref_model->isSubclassOf(Model2::class) && !$ref_model->isAbstract()) {
                if (!(new $class())->getTable()->view) {
                    $list[$class] = -1;
                }
            }

        }

        # Verificando eventuais dependências

        foreach ($list as $class => $index) {

            /** @var Model2 $model */
            $model = new $class();

            foreach ($model->getForeignKeys() as $foreign_key) {
                if (isset($list[$foreign_key->class_name])) {
                    $list[$foreign_key->class_name] = $list[$class] - 1;
                }
            }

            foreach ($model->getManyToMany() as $many_to_many) {
                if (isset($list[$many_to_many->class_name])) {
                    $list[$many_to_many->class_name] = $list[$class] - 1;
                }
            }

        }

        # Ordenando e executando as sincronizações

        asort($list);
        foreach ($list as $class => $index) {
            $connector->sync($class);
        }

        # Executando scripts SQL

        // TODO

        # Resumo do processo executado

        $time = Format::number(microtime(true) - $start_time);

        $memory = Format::size(memory_get_usage());

        $stream->write("-- Finalizado em <b>$time segundos</b> utilizando <b>$memory</b>");

        $stream->write('</pre>');

    }

    /**
     * @throws ReflectionException
     */
    public function __construct()
    {

        $class_name = get_called_class();

        $reflection = new ReflectionClass($class_name);

        $routes = $reflection->getAttributes(Route::class);

        $base_route = null;

        foreach ($routes as $route) {

            /** @var Route $instance */
            $instance = $route->newInstance();

            $base_route = $instance->route;

        }

        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {

            $routes = $method->getAttributes(Route::class);

            foreach ($routes as $route) {

                /** @var Route $instance */
                $instance = $route->newInstance();

                if ($instance->append) {
                    $instance->route = $base_route . $instance->route;
                }

                $instance->setClass($class_name);
                $instance->setMethod($method->getName());

                $this->routes[] = $instance;

            }

        }

    }

}
