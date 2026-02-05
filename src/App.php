<?php
namespace Fluxion;

use stdClass;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Psr7\{ServerRequest, Response};
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

class App
{

    /** @var Route[] */
    protected static array $routes = [];

    public static function error404(): never
    {

        http_response_code(404);

        echo '<h1>404</h1>';

        exit;

    }

    public static function routeUrl(): void
    {

        $request = ServerRequest::fromGlobals();

        if (!self::dispatch($request, self::$routes)) {
            self::error404();
        }

    }

    public static function inputStream()
    {
        return json_decode(file_get_contents('php://input')) ?? new stdClass();
    }

    /**
     * @param Route[] $routes
     */
    public static function dispatch(RequestInterface $request, array $routes, array $args = []): bool
    {

        //$query_params = [];
        //parse_str($request->getUri()->getQuery(), $query_params);

        $response = new Response();

        $parameters = new stdClass();

        foreach ($routes as $route) {

            if (in_array($request->getMethod(), $route->methods)
                && preg_match($route->getRegExp(), $request->getUri()->getPath(), $_args)
                /*&& (!isset($key['model']) || $this->modelFromId($key['model'], $_args))*/) {

                $args = array_merge($args, $_args, $route->args);

                if ($method = $route->getMethod()) {

                    $control_name = $route->getClass();

                    $control = new $control_name();

                    foreach ($args as $k=>$v)
                        if (!is_numeric($k))
                            $parameters->$k = $v;

                    $control->$method($request, $response, $parameters);

                    $emitter = new SapiEmitter();
                    $emitter->emit($response);

                    return true;

                }

                /*elseif (isset($key['include'])) {

                    $control = new $key['control']();
                    $include = $key['include'];

                    $new_uri = preg_replace($route, '', $uri);

                    if ($new_uri == '')
                        $new_uri = '/';

                    if ($this->dispatch($new_uri, $control->$include(), $args))
                        return true;

                }*/

            }

        }

        return false;

    }

    /**
     * @throws CustomException
     */
    public static function registerApp(string $namespace, string $dir, string $controller): void
    {

        AutoLoader2::addNamespace($namespace, $dir);

        $control_name = $namespace . '\\' . $controller;

        if (!class_exists($control_name)) {
            throw new CustomException("Classe $controller não encontrada!");
        }

        /** @var Controller $control */
        $control = new $control_name();

        if (!$control instanceof Controller2) {
            throw new CustomException("Classe $controller não é um Controller!");
        }

        self::$routes = array_merge(self::$routes, $control->getRoutes());

    }

}
