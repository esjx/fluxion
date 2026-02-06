<?php
namespace Fluxion;

use DateTime;
use Exception;
use Generator;
use FilesystemIterator;
use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use PDO;

class Util
{

    const PADRAO_EMAIL = '/^[\w+.-]+@[\w.-]+\.\w{2,}(?:\.\w{2})?$/i';
    const PADRAO_TELEFONE = '/^\([1-9]{2}\) ?[2-9]\d{3,4}-\d{4}$/i';
    const PADRAO_TELEFONE_NUMERICO = '/^[1-9]{2}[2-9]\d{7,8}$/i';
    const PADRAO_CELULAR = '/^\([1-9]{2}\) ?[2-9]\d{4}-\d{4}$/i';
    const PADRAO_CELULAR_NUMERICO = '/^[1-9]{2}[2-9]\d{8}$/i';

    public static function dateAgo($datetime, $full = false, $sufixo = ' atrás'): ?string
    {

        try {

            $now = new DateTime;
            $ago = new DateTime($datetime);
            $diff = $now->diff($ago);

            $diff->w = floor($diff->d / 7);
            $diff->d -= $diff->w * 7;

            $string = array(
                'y' => 'ano',
                'm' => 'mês',
                'w' => 'semana',
                'd' => 'dia',
                'h' => 'hora',
                'i' => 'minuto',
                's' => 'segundo',
            );

            $string_plural = array(
                'y' => 'anos',
                'm' => 'meses',
                'w' => 'semanas',
                'd' => 'dias',
                'h' => 'horas',
                'i' => 'minutos',
                's' => 'segundos',
            );

            foreach ($string as $k => &$v) {
                if ($diff->$k) {
                    if ($diff->$k > 1) {
                        $v = $diff->$k . ' ' . $string_plural[$k];
                    } else {
                        $v = $diff->$k . ' ' . $v;
                    }
                } else {
                    unset($string[$k]);
                }
            }

            if (!$full) $string = array_slice($string, 0, 1);

            return $string ? implode(', ', $string) . $sufixo : 'agora mesmo';

        } catch (Exception $e) {
            return null;
        }

    }

    public static function delTree($dir, $remove_dir = true)
    {

        $rdi = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file)
            $file->isFile() ? unlink($file->getPathname()) : rmdir($file->getPathname());

        if ($remove_dir) {

            rmdir($dir);

        }

    }

    public static function loadTextFile($file): Generator
    {

        $f = fopen($file, 'r');

        try {
            while ($line = fgets($f))
                yield $line;
        } finally {
            fclose($f);
        }

    }

    public static function loadAllFiles($dir): Generator
    {

        $rdi = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file)
            if ($file->isFile())
                yield $file->getPathname();

    }

    public static function loadAllDirs($dir): Generator
    {

        $rdi = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file)
            if ($file->isDir() && !$file->isDot())
                yield $file->getPathname();

    }

    public static function loadFiles($dir): Generator
    {

        $rdi = new DirectoryIterator($dir);
        //$files = new IteratorIterator($rdi);

        foreach ($rdi as $file)
            if ($file->isDir() && !$file->isDot())
                yield $file->getPathname();

    }

    public static function uniqueMultidimArray($array, $key): array
    {

        $i = 0;

        $temp_array = [];
        $key_array = [];

        foreach($array as $val) {

            if (!in_array($val[$key], $key_array))
            {
                $key_array[$i] = $val[$key];
                $temp_array[$i] = $val;
            }

            $i++;

        }

        return $temp_array;

    }

    public static function abreviaNome($nome): string
    {

        $partes = explode(' ', $nome);

        $meio = ' ';

        for ($i = 1; $i < (count($partes) - 1); $i++) {

            if (strlen($partes[$i]) > 3) {

                $meio .= substr($partes[$i], 0, 1) . ' ';

            }

        }

        return $partes[0] . $meio . $partes[count($partes) - 1];

    }

    public static function slug($str): string
    {

        $str = preg_replace('/[áàãâä]/u', 'a', $str);
        $str = preg_replace('/[éèêë]/u', 'e', $str);
        $str = preg_replace('/[íìîï]/u', 'i', $str);
        $str = preg_replace('/[óòõôö]/u', 'o', $str);
        $str = preg_replace('/[úùûü]/u', 'u', $str);
        $str = preg_replace('/ç/u', 'c', $str);

        $str = preg_replace('/[ÁÀÃÂÄ]/u', 'A', $str);
        $str = preg_replace('/[ÉÈÊË]/u', 'E', $str);
        $str = preg_replace('/[ÍÌÎÏ]/u', 'I', $str);
        $str = preg_replace('/[ÓÒÕÔÖ]/u', 'O', $str);
        $str = preg_replace('/[ÚÙÛÜ]/u', 'U', $str);
        $str = preg_replace('/Ç/u', 'c', $str);

        // $str = preg_replace('/[,(),;:|!"#$%&/=?~^><ªº-]/', '_', $str);
        $str = preg_replace('/[^a-z0-9]/i', '-', $str);
        $str = preg_replace('/-+/', '-', $str); // ideia do Bacco :)

        return mb_strtolower($str, 'UTF-8');

    }

    public static function sanitizeString($str): string
    {

        $str = preg_replace('/[áàãâä]/u', 'a', $str);
        $str = preg_replace('/[éèêë]/u', 'e', $str);
        $str = preg_replace('/[íìîï]/u', 'i', $str);
        $str = preg_replace('/[óòõôö]/u', 'o', $str);
        $str = preg_replace('/[úùûü]/u', 'u', $str);
        $str = preg_replace('/ç/u', 'c', $str);

        $str = preg_replace('/[ÁÀÃÂÄ]/u', 'A', $str);
        $str = preg_replace('/[ÉÈÊË]/u', 'E', $str);
        $str = preg_replace('/[ÍÌÎÏ]/u', 'I', $str);
        $str = preg_replace('/[ÓÒÕÔÖ]/u', 'O', $str);
        $str = preg_replace('/[ÚÙÛÜ]/u', 'U', $str);
        $str = preg_replace('/Ç/u', 'c', $str);

        // $str = preg_replace('/[,(),;:|!"#$%&/=?~^><ªº-]/', '_', $str);
        $str = preg_replace('/[^a-z0-9]/i', '_', $str);
        $str = preg_replace('/_+/', '_', $str); // ideia do Bacco :)

        return preg_replace('/_/', ' ', $str);

    }

    public static function retiraAcentos($str): string
    {

        $str = preg_replace('/[áàãâä]/ui', 'a', $str);
        $str = preg_replace('/[éèêë]/ui', 'e', $str);
        $str = preg_replace('/[íìîï]/ui', 'i', $str);
        $str = preg_replace('/[óòõôö]/ui', 'o', $str);
        $str = preg_replace('/[úùûü]/ui', 'u', $str);
        $str = preg_replace('/ç/ui', 'c', $str);
        // $str = preg_replace('/[,(),;:|!"#$%&/=?~^><ªº-]/', '_', $str);
        $str = preg_replace('/[^a-z0-9-]/i', '_', $str);
        $str = preg_replace('/_+/', '_', $str); // ideia do Bacco :)
        // ideia do Bacco :)
        return mb_strtoupper($str, 'utf8');

    }

    public static function retiraAcentos2($str): string
    {

        $str = preg_replace('/[áàãâä]/ui', 'a', $str);
        $str = preg_replace('/[éèêë]/ui', 'e', $str);
        $str = preg_replace('/[íìîï]/ui', 'i', $str);
        $str = preg_replace('/[óòõôö]/ui', 'o', $str);
        $str = preg_replace('/[úùûü]/ui', 'u', $str);
        $str = preg_replace('/ç/ui', 'c', $str);
        // $str = preg_replace('/[,(),;:|!"#$%&/=?~^><ªº-]/', '_', $str);
        $str = preg_replace('/[^a-z0-9-]/i', '_', $str);
        $str = preg_replace('/_+/', '_', $str); // ideia do Bacco :)
        // ideia do Bacco :)
        return mb_strtolower($str, 'utf8');

    }

    public static function retiraAcentos3($str): string
    {

        $str = preg_replace('/[áàãâä]/ui', 'a', $str);
        $str = preg_replace('/[éèêë]/ui', 'e', $str);
        $str = preg_replace('/[íìîï]/ui', 'i', $str);
        $str = preg_replace('/[óòõôö]/ui', 'o', $str);
        $str = preg_replace('/[úùûü]/ui', 'u', $str);
        $str = preg_replace('/ç/ui', 'c', $str);
        // $str = preg_replace('/[,(),;:|!"#$%&/=?~^><ªº-]/', '_', $str);
        $str = preg_replace('/[^a-z0-9-]/i', ' ', $str);
        //$str = preg_replace('/_+/', '_', $str); // ideia do Bacco :)
        // ideia do Bacco :)
        return mb_strtoupper($str, 'utf8');

    }

    public static function retiraEspeciais($str): string
    {

        $str = preg_replace('/[áàãâä]/ui', 'a', $str);
        $str = preg_replace('/[éèêë]/ui', 'e', $str);
        $str = preg_replace('/[íìîï]/ui', 'i', $str);
        $str = preg_replace('/[óòõôö]/ui', 'o', $str);
        $str = preg_replace('/[úùûü]/ui', 'u', $str);
        $str = preg_replace('/ç/ui', 'c', $str);
        $str = preg_replace('/[^a-z0-9-\s,]/i', ' ', $str);
        $str = preg_replace('/\s+/', ' ', $str); // ideia do Bacco :)
        // ideia do Bacco :)
        return mb_strtolower($str, 'utf8');

    }

    public static function acentosLike($str): string
    {

        $str = preg_replace('/[áàãâä]/ui', '_', $str);
        $str = preg_replace('/[éèêë]/ui', '_', $str);
        $str = preg_replace('/[íìîï]/ui', '_', $str);
        $str = preg_replace('/[óòõôö]/ui', '_', $str);
        $str = preg_replace('/[úùûü]/ui', '_', $str);
        $str = preg_replace('/ç/ui', '_', $str);
        // $str = preg_replace('/[,(),;:|!"#$%&/=?~^><ªº-]/', '_', $str);
        $str = preg_replace('/[^a-z0-9-]/i', '_', $str);
        //$str = preg_replace('/_+/', '_', $str); // ideia do Bacco :)
        // ideia do Bacco :)
        return preg_replace('/[^a-z0-9-]/i', '_', $str);

    }

    public static function somenteNumeros($str): string
    {

        return preg_replace('/[^0-9]/i', '', $str);

    }

    public static function simplificaCpfCnpj($str): string
    {

        return preg_replace('/[.\-\/%]/i', '', $str);

    }

    public static function jsDate(?string $date, $format = 'Y/m/d H:i:s'): ?string
    {

        if (is_null($date)) {
            return null;
        }

        try {

            return (new DateTime($date))->format($format);

        } catch (Exception $e) {

            return null;

        }

    }

    public static function formatTime(?int $minutes): ?string
    {

        if (is_null($minutes)) {
            return '--:--';
        }

        $hours = floor($minutes / 60);
        $minutes %= 60;

        return sprintf("%02d:%02d", $hours, $minutes);

    }

    public static function excelToDbDate($date): ?string
    {

        if (is_null($date) || trim($date) == '') {

            return null;

        }

        try {

            $date -= 2;

            $date = (int) $date;

            return (new DateTime('1900-01-01'))->modify("+$date days")->format('Y-m-d');

        } catch (Exception $e) {

            return null;

        }

    }

    public static function capturaConteudoUrl(string $url, bool $encode = true): ?string
    {

        try {

            $curl = curl_init();

            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HEADER, false);

            // Define o Proxy (se precisar)
            /*if (!empty($_ENV['PROXY'] ?? '')) {

                curl_setopt($curl, CURLOPT_PROXY, $_ENV['PROXY']);
                curl_setopt($curl, CURLOPT_PROXYUSERPWD, ':');
                curl_setopt($curl, CURLOPT_PROXYAUTH, CURLAUTH_NTLM);

            }*/

            //curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

            $output = curl_exec($curl);

            if ($encode) {

                //$output = utf8_encode($output);

            }

            //$info = curl_getinfo($curl);

            curl_close($curl);

            //Application::error(json_encode($info));

            return $output;

        } catch (Exception $e) {

            Application::silentError($e->getMessage());

            return null;

        }

    }

    public static function downloadFileFromUrl(string $url, string $file, bool $follow = true)
    {

        $ch = curl_init($url);

        Upload::createDir(dirname($file));

        $fp = fopen($file, 'wb');

        // It set an option for a cURL transfer
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow);

        // Define o Proxy (se precisar)
        /*if (!empty($_ENV['PROXY'] ?? '')) {

            curl_setopt($ch, CURLOPT_PROXY, $_ENV['PROXY']);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, ':');
            curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_NTLM);

        }*/

        // Perform a cURL session
        curl_exec($ch);

        $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        // Closes a cURL session and frees all resources
        curl_close($ch);

        // Close file
        fclose($fp);

        if ($response_code != 200) {
            unlink($file);
        }

    }

    public static function formatNumber(?float $value, bool $minimizar = true, int $decimals = 2): string
    {

        $sufix = '';

        $gatilho = 900;

        if ($minimizar) {

            if ($value > $gatilho) {
                $value /= 1000;
                $sufix = 'mil';
            }

            if ($value > $gatilho) {
                $value /= 1000;
                $sufix = 'milhões';
            }

            if ($value > $gatilho) {
                $value /= 1000;
                $sufix = 'bilhões';
            }

        }

        return trim(number_format($value ?? 0, $decimals, ',', '.') . ' ' . $sufix);

    }

    public static function formatNumber2(?float $value, bool $minimizar = true, int $decimals = 2): string
    {

        $sufix = '';

        $gatilho = 900;

        if ($minimizar) {

            if ($value > $gatilho) {
                $value /= 1000;
                $sufix = 'mil';
            }

            if ($value > $gatilho) {
                $value /= 1000;
                $sufix = 'mi';
            }

            if ($value > $gatilho) {
                $value /= 1000;
                $sufix = 'bi';
            }

        }

        return trim(number_format($value ?? 0, $decimals, ',', '.') . ' ' . $sufix);

    }

    public static function formatNumberNull(?float $value, bool $minimizar = true, int $decimals = 2): string
    {

        if (is_null($value)) {
            return '';
        }

        $sufix = '';

        $gatilho = 900;

        if ($minimizar) {

            if ($value > $gatilho) {
                $value /= 1000;
                $sufix = 'mil';
            }

            if ($value > $gatilho) {
                $value /= 1000;
                $sufix = 'mi';
            }

            if ($value > $gatilho) {
                $value /= 1000;
                $sufix = 'bi';
            }

        }

        return trim(number_format($value ?? 0, $decimals, ',', '.') . ' ' . $sufix);

    }

    public static function formatDate(?string $date, string $format = 'd/m/Y H:i:s'): ?string
    {

        if (is_null($date)) {
            return null;
        }

        try {

            return (new DateTime($date))->format($format);

        } catch (Exception $e) {

            return null;

        }

    }

    public static function modifyDate(?string $input, string $modifier, string $format = 'Y-m-d'): ?string
    {

        if (is_null($input)) {
            return null;
        }

        $base = DateTime::createFromFormat($format, $input);

        try {

            if (!$base) {
                $base = new DateTime($input);
            }

            return $base->modify($modifier)->format($format);

        } catch (Exception $e) {

            return null;

        }

    }

    public static function formatSize($size): string
    {
        $mod = 1024;
        $units = explode(' ','B KB MB GB TB PB');
        for ($i = 0; $size > $mod; $i++) {
            $size /= $mod;
        }

        return self::formatNumber($size, false) . ' ' . $units[$i];
    }

    public static function ordenar($array, $cols): array
    {

        $colarr = [];

        foreach ($cols as $col => $order) {
            $colarr[$col] = array();
            foreach ($array as $k => $row) { $colarr[$col]['_'.$k] = strtolower($row[$col]); }
        }

        $eval = 'array_multisort(';

        foreach ($cols as $col => $order) {
            $eval .= '$colarr[\''.$col.'\'],'.$order.',';
        }

        $eval = substr($eval,0,-1).');';

        eval($eval);

        $ret = [];

        foreach ($colarr as $col => $arr) {
            foreach ($arr as $k => $v) {
                $k = substr($k,1);
                if (!isset($ret[$k])) $ret[$k] = $array[$k];
                $ret[$k][$col] = $array[$k][$col];
            }
        }

        return $ret;

    }

    public static function anoSafra(string $data): string
    {

        try {

            $d = new DateTime($data);

            $a = $d->format('Y');

            $ano = ($d->format('m') <= 6) ? $a - 1 : $a;

            return $ano . '/' . ($ano + 1);

        } catch (Exception $e) {

            return 'ERRO';

        }

    }

    public static function validaData($data): bool
    {

        try {

            $d = new DateTime($data);

            if (is_null($d->format('Y-m-d'))) {
                return false;
            }

            return true;

        } catch (Exception $e) {

            return false;

        }

    }

    public static function date_diff($date_1, $date_2, $ignore_time = false): array
    {

        if ($ignore_time) {
            $date_1 = substr($date_1, 0, 10);
            $date_2 = substr($date_2, 0, 10);
        }

        $datetime1 = date_create($date_1);
        $datetime2 = date_create($date_2);

        return (array) date_diff($datetime1, $datetime2);

    }

    public static function gerarJSON($pdo, $sql, $file_name = null)
    {

        $stmt = $pdo->query($sql);

        $json = [];

        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $registro = [];

            foreach ($result as $key => $value) {

                $registro[$key] = $value;

            }

            $json[] = $registro;

        }

        if (is_null($file_name)) {

            file_put_contents($file_name, json_encode($json));

        } else {

            Application::printJson($json);

        }

    }

    public static function acertaDados(&$var)
    {

        foreach ($var as $k => $v) {

            if (preg_match('/^[\d,.]+$/', $v)) {
                $var[$k] = str_replace(',', '.', str_replace('.', '', $v)) * 1;
            } else {
                $var[$k] = $v;
            }

        }

    }

    public static function padLeft(?string $string, int $length, string $pad_string = '0'): ?string
    {

        if (is_null($string)) {
            return null;
        }

        return str_pad($string, $length, $pad_string, STR_PAD_LEFT);

    }

    public static function hexToRgb($hex, $alpha = false): array
    {

        $hex = str_replace('#', '', $hex);
        $length = strlen($hex);

        $rgb = [];

        $rgb['r'] = hexdec($length == 6 ? substr($hex, 0, 2) : ($length == 3 ? str_repeat(substr($hex, 0, 1), 2) : 0));
        $rgb['g'] = hexdec($length == 6 ? substr($hex, 2, 2) : ($length == 3 ? str_repeat(substr($hex, 1, 1), 2) : 0));
        $rgb['b'] = hexdec($length == 6 ? substr($hex, 4, 2) : ($length == 3 ? str_repeat(substr($hex, 2, 1), 2) : 0));

        if ( $alpha ) {
            $rgb['a'] = $alpha;
        }

        return $rgb;

    }

    public static function trataCoresQuill(string $texto): string
    {

        foreach (ModelOld::COLOR_MAP as $nome => $cor) {

            $cor = self::hexToRgb("#$cor");

            $cor = "rgb({$cor['r']}, {$cor['g']}, {$cor['b']})";

            $texto = str_replace("style=\"color: $cor;\"", "class=\"text-$nome\"", $texto);
            $texto = str_replace("style=\"background-color: $cor;\"", "class=\"bg-$nome\"", $texto);

        }

        return $texto;

    }

    public static function diasUteis(string $data_1, string $data_2): int
    {

        $dias = 0;
        $multiplicador = 1;

        $data_1 = substr($data_1, 0, 10);
        $data_2 = substr($data_2, 0, 10);

        if (($temp = DateTime::createFromFormat('Y-m-d', $data_1))
            && $temp->format('Y-m-d') != $data_1) {
            Application::error("Data <b>$data_1</b> inválida!");
        }

        if (($temp = DateTime::createFromFormat('Y-m-d', $data_2))
            && $temp->format('Y-m-d') != $data_2) {
            Application::error("Data <b>$data_2</b> inválida!");
        }

        if ($data_1 == $data_2) {
            return 0;
        }

        $data = $data_1;
        $alvo = $data_2;

        if ($data_1 > $data_2) {

            $multiplicador = -1;

            $data = $data_2;
            $alvo = $data_1;

        }

        try {

            while ($data < $alvo) {

                $data = DateTime::createFromFormat('Y-m-d', $data)
                    ->modify('+1 day')
                    ->format('Y-m-d');

                if (self::diaUtil($data)) {
                    $dias++;
                }

            }

        } catch (Exception $e) {
            Application::error($e->getMessage());
        }

        return $dias * $multiplicador;

    }

    public static function proximoDiaUtil(string $data): string
    {

        try {

            $c = 0;

            while (!self::diaUtil($data)) {

                $data = DateTime::createFromFormat('Y-m-d', $data)
                    ->modify('+1 day')
                    ->format('Y-m-d');

                if ($c++ >= 10) {
                    Application::error('Mais que interações!');
                    break;
                }

            }

        } catch (Exception $e) {
            Application::error($e->getMessage());
        }

        return $data;

    }

    public static function diaUtil($data = HOJE): ?bool
    {

        try {

            $d = DateTime::createFromFormat('Y-m-d', $data);

            return (

                // De segunda (1) à sexta (5)
                $d->format('N') <= 5

                // Demais feriados fixos e móveis
                && !in_array($d->format('d-m'), self::feriadosAno($d->format('Y')))

            );

        } catch (Exception $e) {
            Application::error($e->getMessage());
        }

        return null;

    }

    public static function feriadosAno($a): array
    {

        if (!isset($GLOBALS['FERIADOS_ANO_' . $a])) {

            $f = [
                '01-01', // Confraternização universal
                '21-04', // Tiradentes
                '01-05', // Dia do Trabalho
                '07-09', // Independência do Brasil
                '12-10', // Nsa. Sra. Aparecida
                '02-11', // Finados
                '15-11', // Proclamação da República
                '25-12', // Natal
                //'31-12', // Sem movimento bancário
            ];

            if ($a >= 2024) {
                $f[] = '20-11'; // Consciência Negra
            }

            if ($p = self::easter_date($a)) {

                $f[] = date('d-m', strtotime('-48 days', $p)); // Segunda-feira de Carnaval
                $f[] = date('d-m', strtotime('-47 days', $p)); // Terça-feira de Carnaval
                $f[] = date('d-m', strtotime('-2 days', $p)); // Sexta-feira Santa
                $f[] = date('d-m', strtotime('+60 days', $p)); // Corpus Christi

            }

            $GLOBALS['FERIADOS_ANO_' . $a] = $f;

        }

        return $GLOBALS['FERIADOS_ANO_' . $a];

    }

    public static function easter_date($year): int
    {

        // https://www.calendarr.com/brasil/calendario-2040/
        switch ($year) {

            case 2038:
                return strtotime('2038-04-25');

            case 2039:
                return strtotime('2039-04-10');

            case 2040:
                return strtotime('2040-04-01');

            default:
                return easter_date($year);

        }

    }

    public static function mask(string $valor, string $formato): string
    {

        $out = '';
        $pos = 0;

        for ($i = 0; $i <= strlen($formato) - 1; $i++) {

            if (in_array($formato[$i], ['0', 'A', '#'])) {

                if (isset($valor[$pos])) {

                    $out .= $valor[$pos++];

                }

            } else {

                $out .= $formato[$i];

            }

        }

        return $out;

    }

    public static function dateDiff(string $date1, string $date2, string $period): ?string
    {

        $diff = self::date_diff($date1, $date2);

        if ($period == 'm') {
            return $diff['y'] * 12 + $diff['m'];
        }

        return null;

    }

    public static function digito11(string $dado, int $numDig, int $limMult, bool $x10 = true): ?string
    {

        $out = "";

        if (!$x10) {
            $numDig = 1;
        }

        for ($n = 1; $n <= $numDig; $n++) {

            $soma = 0;
            $mult = 2;

            for ($i = strlen($dado) - 1; $i >= 0; $i--) {

                $e = substr($dado, $i, 1);

                $soma += $mult * intval(substr($dado, $i, 1));
                $mult++;

                if ($mult > $limMult) {
                    $mult = 2;
                }

            }

            if ($x10) {
                $dig = (($soma * 10) % 11) % 10;
            }

            else {

                $dig = $soma % 11;

                if ($dig == 10) {
                    $dig = 'X';
                }

            }

            $out .= $dig;
            $dado .= $dig;

        }

        return $out;

    }

}
