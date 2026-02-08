<?php
namespace Fluxion\Log;

use Fluxion\Application;
use Fluxion\AuthOld;
use Fluxion\Config;
use Fluxion\Csrf;
use InvalidArgumentException;

class Log
{

    private $script_start = null;
    private $script_end = null;

    private $log = null;

    public function __construct()
    {
        $GLOBALS['LOG'] = $this;
    }

    public function getUserAgent(Config $config, AuthOld $auth) {

        if (isset($_SESSION['HTTP_USER_AGENT___CACHE_ID']))
            return $_SESSION['HTTP_USER_AGENT___CACHE_ID'];

        $userAgent = Models\UserAgent::filter('name', $_SERVER['HTTP_USER_AGENT'])->firstOrNew($config, $auth);

        if (is_null($userAgent->id)) {

            $ua = $this->parseUserAgent($_SERVER['HTTP_USER_AGENT']);

            $ua['browser'] = str_replace('MSIE', 'Internet Explorer', $ua['browser']);
            $ua['platform'] = str_replace('Macintosh', 'Mac OS X', $ua['platform']);

            $os = Models\OS::filter('name', $ua['platform'])->firstOrNew($config, $auth);
            $os->name = $ua['platform'];
            $os->save();

            $browser = Models\Browser::filter('name', $ua['browser'])->firstOrNew($config, $auth);
            $browser->name = $ua['browser'];
            $browser->save();

            if (!$browser->active) {
                Application::error("Navegador <b>$browser->name</b> não compatível!", 500, false, false);
            }

            $userAgent->name = $_SERVER['HTTP_USER_AGENT'];
            $userAgent->os = $os->id;
            $userAgent->browser = $browser->id;
            $userAgent->browser_version = intval($ua['version']);
            $userAgent->save();

        }

        $browser = Models\Browser::loadById($userAgent->browser, $config, $auth, true);

        if (!$browser->active) {
            Application::error("Navegador <b>$browser->name</b> não compatível!", 500, false, false);
        }

        return $_SESSION['HTTP_USER_AGENT__CACHE_ID'] = $userAgent->id;

    }

    public function log(Config $config, AuthOld $auth) {

        if ($this->script_start == null) {

            list($usec, $sec) = explode(' ', microtime());

            $this->script_start = (float) $sec + (float) $usec;

            $arrUri = explode('?', $_SERVER['REQUEST_URI']);
            $uri = preg_replace('/^\//i', '', $arrUri[0]);
            $uri = preg_replace('/^index.php\//i', '', $uri);
            $uri = preg_replace('/\/$/i', '', $uri) . "/";

            $this->log = new Models\Log($config, $auth);

            $this->log->cost_center = $auth->getCostCenter()->id;
            $this->log->login = $auth->getUser()->login;
            $this->log->user_agent = $this->getUserAgent($config, $auth);
            $this->log->ip = $_SERVER['REMOTE_ADDR'];
            $this->log->method = $_SERVER['REQUEST_METHOD'];
            $this->log->uri = $uri;
            $this->log->query_string = $arrUri[1] ?? substr(file_get_contents('php://input'), 0, 2048);
            $this->log->referer = (isset($_SERVER['HTTP_REFERER'])) ? explode('?', $_SERVER['HTTP_REFERER'])[0] : null;
            $this->log->ok = false;

            if ($this->log->method == 'POST') {

                $this->log->csrf = Csrf::getToken();

            }

            if ($uri == '_auth/login/') {
                $this->log->query_string = '{}';
            }

            $this->log->save();

        }

    }

    public function reLog($control, $action, $ok = true, $auth = null)
    {

        if ($this->log == null)
            return;

        list($usec, $sec) = explode(' ', microtime());

        $this->script_end = (float) $sec + (float) $usec;

        $this->log->cost_center = $auth->getCostCenter()->id;
        $this->log->login = $auth->getUser()->login;
        $this->log->control = $control;
        $this->log->action = $action;
        $this->log->ok = $ok;

        if ($ok) {

            $this->log->time = round($this->script_end - $this->script_start, 5);
            $this->log->memory = round((memory_get_peak_usage(true) / 1024) / 1024, 2);

        }

        $this->log->save();

    }

    /**
     * @param $error
     */
    public function errorLog($error) {

        if ($this->log == null)
            return;

        $this->log->ok = 0;
        $this->log->error = $error;

        list($usec, $sec) = explode(' ', microtime());

        $this->script_end = (float) $sec + (float) $usec;

        $this->log->time = round($this->script_end - $this->script_start, 5);

        $this->log->memory = round((memory_get_peak_usage(true) / 1024) / 1024, 2);

        $this->log->save();

    }

    public static function parseUserAgent($u_agent = null ) {

        if( is_null($u_agent) ) {
            if( isset($_SERVER['HTTP_USER_AGENT']) ) {
                $u_agent = $_SERVER['HTTP_USER_AGENT'];
            } else {
                throw new InvalidArgumentException('parse_user_agent requires a user agent');
            }
        }
        $platform = null;
        $browser  = null;
        $version  = null;
        $empty = array( 'platform' => $platform, 'browser' => $browser, 'version' => $version );
        if( !$u_agent ) return $empty;
        if( preg_match('/\((.*?)\)/im', $u_agent, $parent_matches) ) {
            preg_match_all('/(?P<platform>BB\d+;|Android|CrOS|Tizen|iPhone|iPad|iPod|Linux|Macintosh|Windows(\ Phone)?|Silk|linux-gnu|BlackBerry|PlayBook|X11|(New\ )?Nintendo\ (WiiU?|3?DS)|Xbox(\ One)?)
				(?:\ [^;]*)?
				(?:;|$)/imx', $parent_matches[1], $result, PREG_PATTERN_ORDER);
            $priority = array( 'Xbox One', 'Xbox', 'Windows Phone', 'Tizen', 'Android', 'CrOS', 'X11' );
            $result['platform'] = array_unique($result['platform']);
            if( count($result['platform']) > 1 ) {
                if( $keys = array_intersect($priority, $result['platform']) ) {
                    $platform = reset($keys);
                } else {
                    $platform = $result['platform'][0];
                }
            } elseif( isset($result['platform'][0]) ) {
                $platform = $result['platform'][0];
            }
        }
        if( $platform == 'linux-gnu' || $platform == 'X11' ) {
            $platform = 'Linux';
        } elseif( $platform == 'CrOS' ) {
            $platform = 'Chrome OS';
        }
        preg_match_all('%(?P<browser>Camino|Kindle(\ Fire)?|Firefox|Iceweasel|Safari|MSIE|Trident|AppleWebKit|TizenBrowser|Chrome|
				Vivaldi|IEMobile|Opera|OPR|Silk|Midori|Edge|CriOS|UCBrowser|
				Baiduspider|Googlebot|YandexBot|bingbot|Lynx|Version|Wget|curl|
				Valve\ Steam\ Tenfoot|
				NintendoBrowser|PLAYSTATION\ (\d|Vita)+)
				(?:\)?;?)
				(?:(?:[:/ ])(?P<version>[0-9A-Z.]+)|/(?:[A-Z]*))%ix',
            $u_agent, $result, PREG_PATTERN_ORDER);
        // If nothing matched, return null (to avoid undefined index errors)
        if( !isset($result['browser'][0]) || !isset($result['version'][0]) ) {
            if( preg_match('%^(?!Mozilla)(?P<browser>[A-Z0-9\-]+)(/(?P<version>[0-9A-Z.]+))?%ix', $u_agent, $result) ) {
                return array( 'platform' => $platform ?: null, 'browser' => $result['browser'], 'version' => isset($result['version']) ? $result['version'] ?: null : null );
            }
            return $empty;
        }
        if( preg_match('/rv:(?P<version>[0-9A-Z.]+)/si', $u_agent, $rv_result) ) {
            $rv_result = $rv_result['version'];
        }
        $browser = $result['browser'][0];
        $version = $result['version'][0];
        $lowerBrowser = array_map('strtolower', $result['browser']);
        $find = function ( $search, &$key, &$value = null ) use ( $lowerBrowser ) {
            $search = (array)$search;
            foreach( $search as $val ) {
                $xkey = array_search(strtolower($val), $lowerBrowser);
                if( $xkey !== false ) {
                    $value = $val;
                    $key   = $xkey;
                    return true;
                }
            }
            return false;
        };
        $key = 0;
        $val = '';
        if( $browser == 'Iceweasel' ) {
            $browser = 'Firefox';
        } elseif( $find('Playstation Vita', $key) ) {
            $platform = 'PlayStation Vita';
            $browser  = 'Browser';
        } elseif( $find(array( 'Kindle Fire', 'Silk' ), $key, $val) ) {
            $browser  = $val == 'Silk' ? 'Silk' : 'Kindle';
            $platform = 'Kindle Fire';
            if( !($version = $result['version'][$key]) || !is_numeric($version[0]) ) {
                $version = $result['version'][array_search('Version', $result['browser'])];
            }
        } elseif( $find('NintendoBrowser', $key) || $platform == 'Nintendo 3DS' ) {
            $browser = 'NintendoBrowser';
            $version = $result['version'][$key];
        } elseif( $find('Kindle', $key, $platform) ) {
            $browser = $result['browser'][$key];
            $version = $result['version'][$key];
        } elseif( $find('OPR', $key) ) {
            $browser = 'Opera Next';
            $version = $result['version'][$key];
        } elseif( $find('Opera', $key, $browser) ) {
            $find('Version', $key);
            $version = $result['version'][$key];
        } elseif( $find(array( 'IEMobile', 'Edge', 'Midori', 'Vivaldi', 'Valve Steam Tenfoot', 'Chrome' ), $key, $browser) ) {
            $version = $result['version'][$key];
        } elseif( $browser == 'MSIE' || ($rv_result && $find('Trident', $key)) ) {
            $browser = 'MSIE';
            $version = $rv_result ?: $result['version'][$key];
        } elseif( $find('UCBrowser', $key) ) {
            $browser = 'UC Browser';
            $version = $result['version'][$key];
        } elseif( $find('CriOS', $key) ) {
            $browser = 'Chrome';
            $version = $result['version'][$key];
        } elseif( $browser == 'AppleWebKit' ) {
            if( $platform == 'Android' && !($key = 0) ) {
                $browser = 'Android Browser';
            } elseif( strpos($platform, 'BB') === 0 ) {
                $browser  = 'BlackBerry Browser';
                $platform = 'BlackBerry';
            } elseif( $platform == 'BlackBerry' || $platform == 'PlayBook' ) {
                $browser = 'BlackBerry Browser';
            } else {
                $find('Safari', $key, $browser) || $find('TizenBrowser', $key, $browser);
            }
            $find('Version', $key);
            $version = $result['version'][$key];
        } elseif( $pKey = preg_grep('/playstation \d/i', array_map('strtolower', $result['browser'])) ) {
            $pKey = reset($pKey);
            $platform = 'PlayStation ' . preg_replace('/[^\d]/i', '', $pKey);
            $browser  = 'NetFront';
        }
        return array(
            'platform' => $platform ?: null,
            'browser' => $browser ?: null,
            'version' => $version ?: null,
        );
    }

}
