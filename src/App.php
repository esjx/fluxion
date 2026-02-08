<?php
namespace Fluxion;

use stdClass;
use ReflectionMethod;
use ReflectionException;
use GuzzleHttp\Psr7\{Response, ServerRequest};
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Http\Message\{RequestInterface, ResponseInterface};

class App
{

    /** @var Route[] */
    protected static array $routes = [];

    public static function error404(): never
    {

        http_response_code(404);

        echo '<h1>404</h1>' . $_SERVER['REQUEST_URI'];

        exit;

    }

    /**
     * @throws ReflectionException
     */
    public static function routeUrl(): void
    {

        $request = ServerRequest::fromGlobals();

        if (!self::dispatch($request, self::$routes)) {
            self::error404();
        }

    }

    public static function setCache(ResponseInterface $response, $seconds = 60): ResponseInterface
    {

        $ts = gmdate("D, d M Y H:i:s", time() + $seconds) . " GMT";
        $ls = gmdate("D, d M Y H:i:s", time()) . " GMT";

        return $response->withHeader('Expires', $ts)
            ->withHeader('Last-Modified', $ls)
            ->withHeader('Pragma', 'cache')
            ->withHeader('Cache-Control', "max-age=$seconds");

    }

    /**
     * @param Route[] $routes
     * @throws ReflectionException
     */
    public static function dispatch(RequestInterface $request, array $routes, array $args = []): bool
    {

        $query_params = [];
        parse_str($request->getUri()->getQuery(), $query_params);

        foreach ($routes as $route) {

            if (in_array($request->getMethod(), $route->methods)
                && preg_match($route->getRegExp(), $request->getUri()->getPath(), $_args)) {

                if ($route->getClass() && $route->getMethod()) {

                    $reflection = new ReflectionMethod($route->getClass(), $route->getMethod());

                    $parameters = $reflection->getParameters();

                    $invoke_parameters = [];

                    foreach ($parameters as $param) {

                        if ($param->hasType()) {

                            $type = (string) $param->getType();

                            if ($type == RequestInterface::class) {
                                $invoke_parameters[] = $request;
                            }

                            elseif ($type == ResponseInterface::class) {
                                $invoke_parameters[] = new Response();
                            }

                            elseif ($type == Route::class) {
                                $invoke_parameters[] = $route;
                            }

                            /*elseif ($type == Auth::class) {
                                $invoke_parameters[] = $auth;
                            }*/

                            /*elseif ($type == Model::class) {
                                $invoke_parameters[] = $model;
                            }*/

                            elseif ($type == stdClass::class) {

                                $p = new stdClass();

                                foreach (array_merge($args, $_args, $route->args, $query_params) as $k=>$v) {
                                    if (!is_numeric($k)) {
                                        $p->$k = $v;
                                    }
                                }

                                $invoke_parameters[] = $p;
                            }

                        }

                    }

                    $control_name = $route->getClass();

                    $out = $reflection->invokeArgs(new $control_name(), $invoke_parameters);

                    if ($out instanceof ResponseInterface) {
                        (new SapiEmitter())->emit($out);
                    }

                    return true;

                }

            }

        }

        return false;

    }

    /**
     * @throws Exception
     */
    public static function registerApp(string $namespace, string $dir, string $controller): void
    {

        AutoLoader::addNamespace($namespace, $dir);

        $control_name = $namespace . '\\' . $controller;

        if (!class_exists($control_name)) {
            throw new Exception("Classe $controller não encontrada!");
        }

        /** @var ControllerOld $control */
        $control = new $control_name();

        if (!$control instanceof Controller) {
            throw new Exception("Classe $controller não é um Controller!");
        }

        self::$routes = array_merge(self::$routes, $control->getRoutes());

    }

}
