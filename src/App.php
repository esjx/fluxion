<?php
namespace Fluxion;

use stdClass;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class App
{

    /** @var Route[] */
    protected static array $routes = [];

    public static function getUri(): string
    {

        $parsed_uri = parse_url($_SERVER['REQUEST_URI']);

        /*$query_params = [];
        if (isset($parsed_uri['query'])) {
            parse_str($parsed_uri['query'], $query_params);
        }*/

        $uri = $parsed_uri['path'];

        $uri = preg_replace('/\/$/i', '', $uri);

        return ($uri) ?: '/';

    }

    public static function error404(): never
    {

        http_response_code(404);

        echo '<h1>404</h1>';

        exit;

    }

    public static function routeUrl(): void
    {

        $uri = self::getUri();

        if (!self::dispatch($uri, self::$routes)) {
            self::error404();
        }

    }

    public static function getRegExp($url, $end): string
    {

        $end = ($end) ? '$' : '';

        $url = preg_replace('/{([a-z0-9_]+):int}/i', '(?P<$1>[0-9]+)', $url);
        $url = preg_replace('/{([a-z0-9_]+):string}/i', '(?P<$1>[a-z0-9-_.=%]+)', $url);
        $url = preg_replace('/{([a-z0-9_]+):any}/i', '(?P<$1>[a-z0-9-_.=%/]+)', $url);

        $url = str_replace('/', '\/', $url);

        return '/^' . $url . $end . '/i';

    }

    public static function inputStream()
    {
        return json_decode(file_get_contents('php://input')) ?? new stdClass();
    }

    /**
     * @param Route[] $routes
     */
    public static function dispatch(string $uri, array $routes, array $args = []): bool
    {

        $request = new Request('GET', $uri);
        $response = new Response();

        $parameters = new stdClass();

        $request_method = strtoupper($_SERVER['REQUEST_METHOD']);

        foreach ($routes as $route_obj) {

            $route = self::getRegExp($route_obj->route, true);

            if (in_array($request_method, $route_obj->methods)
                && preg_match($route, $uri, $_args)
                /*&& (!isset($key['model']) || $this->modelFromId($key['model'], $_args))*/) {

                $args = array_merge($args, $_args, $route_obj->args);

                if ($method = $route_obj->getMethod()) {

                    $control_name = $route_obj->getClass();

                    $control = new $control_name();

                    foreach ($args as $k=>$v)
                        if (!is_numeric($k))
                            $parameters->$k = $v;

                    $control->$method($request, $response, $parameters);

                    echo $response->getBody();

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
