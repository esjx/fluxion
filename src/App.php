<?php
namespace Fluxion;

use stdClass;
use ReflectionMethod;
use ReflectionException;
use Exception as _Exception;
use Fluxion\Exception\PageNotFoundException;
use GuzzleHttp\Psr7\{Response, ServerRequest};
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Micheh\Cache\CacheUtil;

class App
{

    /** @var Route[] */
    protected static array $routes = [];

    /** @noinspection PhpUnused */
    public static function routeUrl(): void
    {

        $request = ServerRequest::fromGlobals();

        try {

            if (!self::dispatch($request, self::$routes)) {
                throw new PageNotFoundException($request->getUri()->getPath());
            }

        }

        catch (_Exception $e) {

            $server = $request->getServerParams();

            $is_json = strcasecmp($server['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') == 0;
            $is_text = strcasecmp($server['HTTP_ACCEPT'] ?? '', 'text/strings') == 0;

            $code = 500;

            if ($e instanceof PageNotFoundException) {
                $code = 404;
            }

            $message = $e->getMessage();

            if ($code == 500) {

                $message .= '<br><br><pre>'
                    . str_replace(dirname(__DIR__, 4), '...', $e->getTraceAsString())
                    . '</pre>';

            }

            /** @var ResponseInterface $response */

            if ($is_json && !$is_text) {
                $response = ResponseFactory::fromJson([
                    'status' => $code,
                    'message' => $message,
                    'trace' => $e->getTraceAsString()
                ], $code);
            }

            else {
                $response = ResponseFactory::fromText($message, $code);
            }

            $util = new CacheUtil();

            $response = $util->withCachePrevention($response);

            (new SapiEmitter())->emit($response);

        }

    }

    /**
     * @param Route[] $routes
     * @throws Exception
     * @throws ReflectionException
     */
    public static function dispatch(RequestInterface $request, array $routes, array $args = []): bool
    {

        $path = $request->getUri()->getPath();

        $path = '/'. trim($path, '/');

        foreach ($routes as $route) {

            if (in_array($request->getMethod(), $route->methods)
                && preg_match($route->getRegExp(), $path, $_args)) {

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

                            elseif ($type == Auth::class) {
                                $invoke_parameters[] = Config::getAuth($request);
                            }

                            elseif ($type == stdClass::class) {

                                $p = new stdClass();

                                $query_params = [];
                                parse_str($request->getUri()->getQuery(), $query_params);

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

                    $out = $reflection->invokeArgs(new $control_name($request), $invoke_parameters);

                    if ($out instanceof ResponseInterface) {
                        (new SapiEmitter())->emit($out);
                    }

                    elseif ($out === false) {
                        return false;
                    }

                    return true;

                }

            }

        }

        return false;

    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    public static function registerApp(string $namespace, string $dir, string $controller): void
    {

        AutoLoader::addNamespace($namespace, $dir);

        $control_name = $namespace . '\\' . $controller;

        if (!class_exists($control_name)) {
            throw new Exception("Classe $controller não encontrada!");
        }

        /** @var ControllerOld $control */
        $control = new $control_name(ServerRequest::fromGlobals());

        if (!$control instanceof Controller) {
            throw new Exception("Classe $controller não é um Controller!");
        }

        self::$routes = array_merge(self::$routes, $control->getRoutes());

    }

}
