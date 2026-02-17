<?php
namespace Fluxion;

use ReflectionException;
use ReflectionMethod;
use stdClass;
use Exception as _Exception;
use Fluxion\Exception\{PageNotFoundException, SqlException};
use GuzzleHttp\Psr7\{Response, ServerRequest};
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Micheh\Cache\CacheUtil;
use Psr\Log\{LoggerInterface, LogLevel, NullLogger};
use Psr\Http\Message\{RequestInterface, ResponseInterface};

class App
{

    protected static ?LoggerInterface $logger = null;

    public function setLogger(LoggerInterface $logger): void
    {
        $this::$logger = $logger;
    }

    public static function getLogger(): ?LoggerInterface
    {
        if (is_null(self::$logger)) {
            self::$logger = new NullLogger();
        }
        return self::$logger;
    }

    /** @var Route[] */
    protected static array $routes = [];

    public function __construct()
    {
        $this->setLogger(new NullLogger());
    }

    /** @noinspection PhpUnused */
    public function route(): void
    {

        $request = ServerRequest::fromGlobals();

        try {

            Config::getAuth($request);

            if (!$this->dispatch($request, $this::$routes)) {
                throw new PageNotFoundException($request->getUri()->getPath());
            }

        }

        catch (_Exception $e) {

            $this->errorHandler($e, $request);

        }

    }

    /**
     * @param Route[] $routes
     * @throws Exception
     * @throws ReflectionException
     */
    public function dispatch(RequestInterface $request, array $routes, array $args = []): bool
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
                                $invoke_parameters[] = Config::getAuth();
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
     * @noinspection PhpUnused
     */
    public function registerApp(string $namespace, string $dir, string $controller): void
    {

        try {

            AutoLoader::addNamespace($namespace, $dir);

            $control_name = $namespace . '\\' . $controller;

            if (!class_exists($control_name)) {
                throw new Exception("Classe $controller não encontrada!");
            }

            /** @var Controller $control */
            $control = new $control_name(ServerRequest::fromGlobals());

            if (!$control instanceof Controller) {
                throw new Exception("Classe $controller não é um Controller!");
            }

            $this::$routes = array_merge($this::$routes, $control->getRoutes());

        }

        catch (_Exception $e) {

            $this->errorHandler($e);

        }

    }

    public function errorHandler(_Exception $e, ?ServerRequest $request = null): never
    {

        $message = $e->getMessage();

        if (method_exists($e, 'getAltMessage')) {
            $message = $e->getAltMessage();
        }

        if (method_exists($e, 'getLogLevel')) {
            $log_level = $e->getLogLevel();
        }

        else {
            $log_level = LogLevel::ERROR;
        }

        $server = $request?->getServerParams() ?? [];

        $is_json = strcasecmp($server['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') == 0;
        $is_text = strcasecmp($server['HTTP_ACCEPT'] ?? '', 'text/strings') == 0;

        $trace = str_replace(__DIR__, '.../...', $e->getTraceAsString());
        $trace = str_replace(dirname(__DIR__, 4), '...', $trace);

        $code = 500;

        $detail = '';

        if ($e instanceof PageNotFoundException) {
            $code = 404;
        }

        elseif ($e instanceof SqlException) {

            $detail .= "<br><br><pre>"
                . SqlFormatter::highlight($e->getSql(), false)
                . "\n<span class=\"text-red\">-- {$e->getOriginalMessage()}" . "</span>"
                . "\n\n<span class=\"text-gray\">/*\n$trace\n*/</span>" . "</pre>";

        }

        elseif ($e instanceof Exception) {

            $detail .= "<br><br><pre>$trace</pre>";

        }

        self::getLogger()->log($log_level, $message);

        /** @var ResponseInterface $response */

        if ($is_json && !$is_text) {
            $response = ResponseFactory::fromJson([
                'status' => $code,
                'message' => $message . $detail,
                'trace' => $e->getTraceAsString()
            ], $code);
        }

        else {

            $view = Config::getErrorView();

            if (is_null($view)) {
                $response = ResponseFactory::fromText($message . $detail, $code);
            }

            else {

                /** @var View $view */
                $view = new $view();

                $view->code = $code;
                $view->message = $message;
                $view->detail = $detail;

                $response = ResponseFactory::fromView($view, $code);

            }

        }

        $util = new CacheUtil();

        $response = $util->withCachePrevention($response);

        (new SapiEmitter())->emit($response);

        exit;

    }

}
