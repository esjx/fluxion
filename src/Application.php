<?php
namespace Fluxion;

//use Esj\App\Home\HomeController;
use Fluxion\Auth\Auth;
use Fluxion\Log\Log;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use stdClass;

class Application
{

    // TODO: Separar Request e Response em outras classes

    public $URL_PARAMETERS;

    private $_autoloader;
    private $_config;
    private $_auth;
    private $_log;

    private $_php_routes = [];
    private $_angular_routes = [];
    private $_site_map = [];

    public $ajax = false;

    public function __construct(AutoLoader $autoloader, Config $config, Auth $auth, Log $log = null)
    {

        //session_start();

        $this->_autoloader = $autoloader;
        $this->_config = $config;
        $this->_auth = $auth;
        $this->_log = $log;

        if (!$this->_auth->authenticate($config)) {
            exit;
        }

    }

    public function getConfig(): Config
    {
        return $this->_config;
    }

    public function getAuth(): Auth
    {
        return $this->_auth;
    }

    public function routeUrl()
    {

        if ($this->_log !== null)
            $this->_log->log($this->_config, $this->_auth);

        $this->URL_PARAMETERS = new stdClass();

        $uri = $this->getUri();

        if (!$this->dispatch($uri, $this->_php_routes)) {
            $this->error404();
            /*$c = new HomeController($this->_config, $this->_auth, $this);
            $c->index([]);*/
        }

    }

    public function getUri(): string
    {

        $uri = explode('?', $_SERVER['REQUEST_URI'])[0];

        $uri = preg_replace('/\/$/i', '', $uri);

        if ($uri == '') $uri = '/';

        return $uri;

    }

    public function createController($namespace, $controller): ControllerOld
    {
        $name = $namespace . '\\' . $controller;
        return new $name($this->_config, $this->_auth, $this);
    }

    public function registerApp($namespace, $dir, $controller = null)
    {

        $this->_autoloader->addNamespace($namespace, $dir);

        if (!is_null($controller)) {

            $c = $this->createController($namespace, $controller);

            $this->addPhpRoutes($c->getPhpRoutes());

            $group = $c->getGroup();

            $merge = true;

            if (isset($_ENV['ITENS_MENU'])) {

                $items = explode(';', $_ENV['ITENS_MENU']);

                if (!in_array($group, $items)) {
                    $merge = false;
                }

            }

            if ($merge) {
                $this->mergeSiteMap($c->getSiteMap());
            }

        }

    }

    public function addPhpRoutes($routes)
    {
        $this->_php_routes = array_merge($this->_php_routes, $routes);
    }

    public function addAngularRoutes($routes)
    {
        $this->_angular_routes = array_merge($this->_angular_routes, $routes);
    }

    public function mergeSiteMap($map)
    {
        $this->_site_map = array_merge_recursive($this->_site_map, $map);
    }

    public function getAngularRoutes(): array
    {
        return $this->_angular_routes;
    }

    public function getSiteMap(): array
    {
        return $this->_site_map;
    }

    public function getRegExp($url, $end): string
    {

        $end = ($end) ? '$' : '';

        $url = preg_replace('/{([a-z0-9_]+):int}/i', '(?P<$1>[0-9]+)', $url);
        $url = preg_replace('/{([a-z0-9_]+):string}/i', '(?P<$1>[a-z0-9-_.=%]+)', $url);
        $url = preg_replace('/{([a-z0-9_]+):any}/i', '(?P<$1>[a-z0-9-_.=%/]+)', $url);

        $url = str_replace('/', '\/', $url);

        return '/^' . $url . $end . '/i';

    }

    public function modelFromId($model, &$args): bool
    {

        $config = $this->_config;
        $auth = $this->_auth;

        $is = Application::inputStream();

        $id = $args['id'] ?? $is->__id ?? null;

        if ($id == 'add') {

            $args['model'] = new $model($config, $auth);

            return true;

        }

        $model = $model::loadById($id, $config, $auth);

        $field_id = $model->getFieldId();

        if (is_null($model->$field_id)) {

            return false;

        } else {

            $args['model'] = $model;

        }

        return true;

    }

    public function dispatch($uri, $patterns, $args = []): bool
    {

        $request_method = strtoupper($_SERVER['REQUEST_METHOD']);

        foreach ($patterns as $key) {

            if (!isset($key['args']))
                $key['args'] = array();

            $route = $this->getRegExp($key['route'], !isset($key['include']));

            $method = $key['method'] ?? $request_method;

            $csrf = $key['csrf'] ?? true;

            $csrf = ($csrf && ($_ENV['CSRF'] ?? true));

            if ($method == $request_method
                && preg_match($route, $uri, $_args)
                && (!isset($key['model']) || $this->modelFromId($key['model'], $_args))) {

                $args = array_merge($args, $_args, $key['args']);

                if (isset($key['action'])) {

                    if ($csrf) {

                        if ($request_method === 'GET') {

                            Csrf::createToken();

                        } elseif ($request_method === 'POST') {

                            Csrf::verifyToken();

                        }

                    }

                    $control = new $key['control']($this->_config, $this->_auth, $this);
                    $action = $key['action'];

                    foreach ($args as $k=>$v)
                        if (!is_numeric($k))
                            $this->URL_PARAMETERS->$k = $v;

                    if ($this->_log !== null)
                        $this->_log->reLog($key['control'], $key['action'], false, $this->_auth);

                    $control->$action($this->URL_PARAMETERS);

                    if ($this->_log !== null)
                        $this->_log->reLog($key['control'], $key['action'], true, $this->_auth);

                    return true;

                } elseif (isset($key['include'])) {

                    $control = new $key['control']($this->_config, $this->_auth, $this);
                    $include = $key['include'];

                    $new_uri = preg_replace($route, '', $uri);

                    if ($new_uri == '')
                        $new_uri = '/';

                    if ($this->dispatch($new_uri, $control->$include(), $args))
                        return true;

                }

            }

        }

        return false;

    }

    static function debugTrace($bt): string
    {

        $caller = array_shift($bt);

        $dir = explode('/', dirname(__FILE__));
        array_pop($dir);
        $dir = implode('/', $dir);

        $file = str_replace($dir, '', $caller['file']);
        $line = $caller['line'];

        return "$file($line)";

    }

    static function trackerSQL($sql)
    {

        if (!isset($GLOBALS['TRACKER_SQL']))
            $GLOBALS['TRACKER_SQL'] = array();

        if ($_ENV['ENVIRONMENT'] != 'PRODUCTION' && $_ENV['ENVIRONMENT'] != 'API_PRODUCTION') {

            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            $detail = $backtrace[0]['file'] . ':' . $backtrace[0]['line'];

            $i = 1;

            while (isset($backtrace[$i])
                && (str_contains($detail, '\Esj\ModelManipulate.php')
                    || str_contains($detail, '\Esj\Query.php')
                    || str_contains($detail, '\Esj\Model.php'))) {

                $detail = $backtrace[$i]['file'] . ':' . $backtrace[$i]['line'];

                $i++;

            }

            $detail = str_replace(dirname(__DIR__, 2), '', $detail);

            $tempo = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
            $t = sprintf("Tempo até aqui: %0.6fs", $tempo);

            $sql = "/* $t | $detail */" . PHP_EOL . $sql;

        }

        array_push($GLOBALS['TRACKER_SQL'], /*microtime() . ' - '  . */$sql);

    }

    static function jsonError($obj)
    {
        self::error('<pre>' . json_encode($obj, JSON_PRETTY_PRINT) . '</pre>');
    }

    static function error(string $error, int $code = 500, bool $json = false, bool $log = true): never
    {

        http_response_code(500);

        if (in_array($code, [
            400, 401, 403, 404, 405, 406, 407, 408, 409, 410, 411, 412, 413, 414, 415, 416, 417, 418,
            421, 422, 423, 424, 425, 426, 428, 429, 431, 451, 500, 501, 502, 503, 504, 505, 506, 507,
            508, 510, 511, 200
            ])) {

            http_response_code($code);

        }

        $e = new Exception();

        $dir = explode(DIRECTORY_SEPARATOR, dirname(__FILE__));
        array_pop($dir);
        array_pop($dir);
        $dir = implode(DIRECTORY_SEPARATOR, $dir);

        //echo $e->getMessage();

        $trace = $e->getTraceAsString();

        $trace = str_replace($dir, '', $trace);

        $caller = Application::debugTrace(debug_backtrace());

        $caller = str_replace($dir, '', $caller);

        $sql = '-';

        $dir_log = 'uploads/log/' . date('Y_m') . '/';
        $file_log = 'LOG_' . date('Y_m_d') . '.csv';

        Upload::createDir($dir_log);

        $data = date('Y-m-d H:i:s');

        if (isset($GLOBALS['AUTH'])) {

            $linha = $data . ';' . ($GLOBALS['AUTH']->getUser()->login ?? '')
                . ';' . sprintf("%04d", $GLOBALS['AUTH']->getCostCenter()->id ?? 0)
                . ';' . sprintf("%03d", $code) . ';' . $caller
                . ';' . explode('?', $_SERVER['REQUEST_URI'])[0]
                . ';' . preg_replace('/<[^>]*>/', '', $error) . ';';

        } else {

            $linha = $data . ';' . 'x000000'
                . ';' . '0000'
                . ';' . sprintf("%03d", $code) . ';' . $caller
                . ';' . explode('?', $_SERVER['REQUEST_URI'])[0]
                . ';' . preg_replace('/<[^>]*>/', '', $error) . ';';

        }

        @file_put_contents($dir_log . $file_log, $linha . PHP_EOL, FILE_APPEND);

        if ($log) {

            $view = new View(dirname(__DIR__, 4) . '/apps/_home/views/erro-mail.phtml');

            if (isset($GLOBALS['TRACKER_SQL'])) {

                $sql = $GLOBALS['TRACKER_SQL'][count($GLOBALS['TRACKER_SQL']) - 1];

            }

            $is = self::inputStream();

            if ($is == '') {
                $is = [];
            }

            $view->error = $error;
            $view->trace = nl2br(preg_replace('/<[^>]*>/', '', $trace));
            $view->uri = explode('?', $_SERVER['REQUEST_URI'])[0];
            $view->method = $_SERVER['REQUEST_METHOD'];
            $view->referer = $_SERVER['HTTP_REFERER'] ?? '-';
            $view->user_agent = $_SERVER['HTTP_USER_AGENT'];
            $view->ip = $_SERVER['REMOTE_ADDR'];

            if (isset($GLOBALS['AUTH'])) {

                $view->user = $GLOBALS['AUTH']->getUser();
                $view->cost_center = $GLOBALS['AUTH']->getCostCenter();

            }

            $view->headers = nl2br(print_r(self::getRequestHeaders(), true));
            $view->get = nl2br(print_r($_GET, true));
            $view->post = nl2br(print_r($_POST, true));
            $view->cookies = nl2br(print_r($_COOKIE ?? null, true));
            $view->files = nl2br(print_r($_FILES ?? null, true));
            $view->is = nl2br(substr(print_r($is, true), 0, 2048));
            $view->sql = nl2br(SqlFormatter::format($sql, true, false));

            $view_base = new View(dirname(__DIR__, 4) . '/apps/_home/views/email-base.phtml');

            $view_base->conteudo = $view->load();

            $mail = new PHPMailer();

            $mail->CharSet = 'UTF-8';

            $mail->AltBody = '';

            $mail->Subject = 'Erro ' . sprintf("%03d", $code) . ' - ' . preg_replace('/<[^>]*>/', '', $error);

            $mail->setFrom('gefoa07@caixa.gov.br', 'Portal Agro');

            $mail->addAddress('edivan.junior@caixa.gov.br');
            //$mail->addAddress('atila.puppim@caixa.gov.br');

            $mail->msgHTML($view_base->load(), __DIR__ . '/../../../..');

            $mail->send();

        }

        $arrTrace = explode("\n", $trace);
        //array_shift($arrTrace);
        //array_pop($arrTrace);
        $trace = implode("\n", $arrTrace);

        $arrError = array(
            'status' => sprintf("%03d", $code),
            'caller' => $caller,
            'message' => $error,
            'trace' => $arrTrace,
            'sql' => $sql,
        );

        if ($_ENV['ENVIRONMENT'] == 'API_PRODUCTION') {

            Application::printJson([
                'erro' => [
                    'codigo' => $code,
                    'descricao' => preg_replace('/<[^>]*>/', '', $error),
                    'complemento' => null,
                ]
            ]);

        }

        elseif ($_SERVER['HTTP_ACCEPT'] == 'text/strings') {

            echo mb_strtoupper($error, 'utf8');

        }

        elseif ($json || self::isAjax()) {

            self::printJson($arrError);

        }

        else {

            $sql_out = '';

            if (isset($GLOBALS['TRACKER_SQL'])) {

                foreach ($GLOBALS['TRACKER_SQL'] as $sql)
                    $sql_out .= $sql . PHP_EOL . PHP_EOL;

            }

            $view = new View(dirname(__DIR__, 4) . '/apps/_home/views/erro.phtml');

            $view->code = sprintf("%03d", $code);
            $view->error = $error;
            $view->trace = $trace;
            $view->caller = $caller;
            $view->sql = $sql_out;

            $view->show();

            /*echo "<pre>";
            echo "<b>Erro " . sprintf("%04d", $code) . "!</b>\n\n";
            echo "<b>Origem:</b>\n$caller\n\n";
            echo "<b>Mensagem:</b>\n$error\n\n";
            echo "<b>Rota:</b>\n$trace\n\n";

            echo "<b>SQL:</b>\n";

            echo "</pre>";*/

        }

        exit;

	}

    static function silentError(string $error)
    {

        $e = new Exception();

        $dir = explode(DIRECTORY_SEPARATOR, dirname(__FILE__));
        array_pop($dir);
        array_pop($dir);
        $dir = implode(DIRECTORY_SEPARATOR, $dir);

        $trace = $e->getTraceAsString();

        $trace = str_replace($dir, '', $trace);

        $sql = '-';

        $view = new View(dirname(__DIR__, 4) . '/apps/_home/views/erro-mail.phtml');

        if (isset($GLOBALS['TRACKER_SQL'])) {

            $sql = $GLOBALS['TRACKER_SQL'][count($GLOBALS['TRACKER_SQL']) - 1];

        }

        $is = self::inputStream();

        if ($is == '') {
            $is = [];
        }

        $view->error = $error;
        $view->trace = nl2br(preg_replace('/<[^>]*>/', '', $trace));
        $view->uri = explode('?', $_SERVER['REQUEST_URI'])[0];
        $view->method = $_SERVER['REQUEST_METHOD'];
        $view->referer = $_SERVER['HTTP_REFERER'] ?? '-';
        $view->user_agent = $_SERVER['HTTP_USER_AGENT'];

        $view->user = null;
        $view->cost_center = null;

        if (isset($GLOBALS['AUTH'])) {

            $view->user = $GLOBALS['AUTH']->getUser();
            $view->cost_center = $GLOBALS['AUTH']->getCostCenter();

        }

        $view->headers = nl2br(print_r(self::getRequestHeaders(), true));
        $view->get = nl2br(print_r($_GET, true));
        $view->post = nl2br(print_r($_POST, true));
        $view->cookies = nl2br(print_r($_COOKIE ?? null, true));
        $view->files = nl2br(print_r($_FILES ?? null, true));
        $view->is = nl2br(substr(print_r($is, true), 0, 2048));
        $view->sql = nl2br(SqlFormatter::format($sql, true, false));

        $view_base = new View(dirname(__DIR__, 4) . '/apps/_home/views/email-base.phtml');

        $view_base->conteudo = $view->load();

        $mail = new PHPMailer();

        $mail->CharSet = 'UTF-8';

        $mail->AltBody = '';

        $mail->Subject = 'Notificação - ' . preg_replace('/<[^>]*>/', '', $error);

        $mail->setFrom('gefoa07@caixa.gov.br', 'Portal Agro');

        $mail->addAddress('edivan.junior@caixa.gov.br');
        //$mail->addAddress('atila.puppim@caixa.gov.br');

        //$mail->addAttachment(__DIR__ . '/../../uploads/temp/unidades.txt', 'unidades.txt');

        $mail->msgHTML($view_base->load(), __DIR__ . '/../../../..');

        $mail->send();

	}

    static function redirect($url, $statusCode = 303)
    {

        header('Location: ' . $url, true, $statusCode);

        die();

    }

    public function error404()
    {

        http_response_code(404);

        if ($this->_log !== null)
            $this->_log->errorLog('404');

        //if (self::isAjax()){

            $uri = $this->getUri();

            self::error("Página <b>$uri</u></b> não encontrada!", 404);

        /*} else {

            header('Content-Type: text/html; charset=utf-8');

            $view = new View($this->_config->getPageNotFound(), [
                '_config' => $this->_config,
                '_auth' => $this->_auth,
            ]);

            $view->show();

        }*/

        exit;

    }

    static function strToClass($str): string
    {
        return str_replace(' ', '', str_replace('  ', '\\', ucwords(str_replace('_', '  ', str_replace('-', ' ', $str)))));
    }

    static function classToStr($str): string
    {

        $values = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        for ($i = 0; $i < strlen($values); $i++)
            $str = str_replace($values[$i], '-' . $values[$i], $str);

        $str = strtolower($str);

        $str = str_replace('models', '', $str);
        $str = str_replace(' ', '', $str);
        $str = str_replace('\\-', '\\', $str);
        $str = str_replace('-esj\app\\', '', $str);
        $str = str_replace('esj\app\\', '', $str);
        $str = str_replace('\\\\', '\\', $str);

        $str = str_replace('comercializacao\produto', 'comercializacao_produto', $str);

        $str = str_replace('\\', DIRECTORY_SEPARATOR, $str);

        return trim($str) . DIRECTORY_SEPARATOR;

    }

    public static function inputStream()
    {
        return json_decode(file_get_contents('php://input')) ?? new stdClass();
    }

    public static function isAjax(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
    }

    public static function printJson($out = array(), $cache = null)
    {

        //Csrf::renewToken();

        if (!is_null($cache))
            Application::setCache($cache);

        header('Content-Type: application/json; charset=utf-8');

        if ($_ENV['ENVIRONMENT'] != 'PRODUCTION'
            && $_ENV['ENVIRONMENT'] != 'API_PRODUCTION'
            && isset($GLOBALS['TRACKER_SQL']) && !isset($out[0])) {
            $out['__sql'] = $GLOBALS['TRACKER_SQL'];
        }

        print(json_encode($out, JSON_PRETTY_PRINT));

        //exit;

    }

    public static function render($template, $vars = null)
    {
        $view = new View($template, $vars);
        $view->show();
    }

    public static function getRequestHeaders(): array
    {

        if (function_exists('apache_request_headers')) {
            if($headers = apache_request_headers()) {
                return $headers;
            }
        }

        $headers = [];

        // Grab the IF_MODIFIED_SINCE header
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $headers['If-Modified-Since'] = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
        }

        return $headers;

    }

    public static function setCache($seconds = 60)
    {

        $ts = gmdate("D, d M Y H:i:s", time() + $seconds) . " GMT";
        $ls = gmdate("D, d M Y H:i:s", time()) . " GMT";
        header("Expires: $ts");
        header("Last-Modified: $ls");
        header("Pragma: cache");
        header("Cache-Control: max-age=$seconds");

    }

    public static function mail($from, $to, $cc, $subject, $message, $bcc = ''): bool
    {

        // TODO: Passar para uma classe própria

        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $headers  = 'MIME-Version: 1.0' . PHP_EOL;
        $headers .= 'Content-type: text/html; charset=utf-8' . PHP_EOL;
        $headers .= 'Date: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT' . PHP_EOL;
        $headers .= 'Message-ID:' . PHP_EOL;
        $headers .= 'From: ' . $from . PHP_EOL;
        //$headers .= 'Reply-To: ' . $from . PHP_EOL;
        //$headers .= 'X-Sender: <' . $from . '>' . PHP_EOL;
        $headers .= 'X-Mailer: PHP/' . phpversion() . PHP_EOL;
        $headers .= 'Return-Path: ' . $from . PHP_EOL;

        $arr = explode(';', $cc);

        foreach ($arr as $cc)
            if ($cc != '')
                $headers .= 'Cc: ' . $cc . PHP_EOL;

        $arr = explode(';', $bcc);

        foreach ($arr as $bcc)
            if ($bcc != '')
                $headers .= 'Bcc: ' . $bcc . PHP_EOL;

        return !!mail(str_replace(';', ',', $to), $subject, $message, $headers);

    }

    public static function fileCache(string $filename): string
    {

        $cache = '';

        if (file_exists($filename))
            $cache = filemtime($filename);

        return $filename . '?x=' . $cache;

    }

}
