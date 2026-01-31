<?php
namespace Fluxion;

use PDO;
use DateTime;
use Generator;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;
use Fluxion\Auth\Auth;

class Upload
{

    const IMAGENS = [
        '.jpg', '.jpeg', '.png',
    ];

    public static function createFileName(string $prefix, Auth $auth): string
    {
        return $prefix . '-' . $auth->getUser()->login . '-' . microtime(true);
    }

    public static function save($fileId, $dir, Auth $auth, $forceZip = true, $fileName = '', $types = [])
    {

        $max_size = 1024 * 1024 * 50; // 50MB

        if (count($types) == 0) {

            $types = [
                'pdf', 'zip', 'jpg', 'jpeg', 'ppt', 'pptx', 'doc', 'docx', 'csv',
                'xls', 'xlsx', 'xlsm', 'png', 'tif', '7z', 'msg', 'xml',
            ];

        }

        self::createDir($dir);

        if ($fileName == '') {
            $fileName = self::createFileName($fileId, $auth);
        }

        $t1 = explode('.', $_FILES[$fileId]['name']);
        $t2 = end($t1);
        $extension = strtolower($t2);

        if (!in_array($extension, $types))
            Application::error("Extensão do arquivo <b>{$_FILES[$fileId]['name']}</b> não permitida!");

        if ($max_size < $_FILES[$fileId]['size'])
            Application::error("Arquivo <b>{$_FILES[$fileId]['name']}</b> maior que o limite de 20MB!");

        if (move_uploaded_file($_FILES[$fileId]['tmp_name'], $dir . $fileName . '.' . $extension)) {

            chmod($dir . $fileName . '.' . $extension, 0777);

            if ($extension != 'zip' && $forceZip) {

                self::zipFile($dir . $fileName . '.' . $extension, $_FILES[$fileId]['name']);

                $extension = 'zip';

            }

            return $fileName . '.' . $extension;

        } else {

            Application::error("Não foi possível gravar o arquivo <b>{$_FILES[$fileId]['name']}</b>!");

        }

        return false;

    }

    public static function delete($files, $dir): bool
    {

        if (is_array($files))
            foreach ($files as $file)
                if (file_exists($dir . $file->file))
                    unlink($dir . $file->file);

        return true;

    }

    public static function createDir($dir)
    {

        $dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);
        $dir = str_replace('\\', DIRECTORY_SEPARATOR, $dir);

        $d = explode(DIRECTORY_SEPARATOR, $dir);
        $dir_now = '';

        foreach ($d as $part) {

            $dir_now .= $part . DIRECTORY_SEPARATOR;

            if ($part != '' && $part != '..' && $dir_now != '\\\\df7436sr351\\') {

                if (!file_exists($dir_now)) {
                    @mkdir($dir_now);
                    @chmod($dir_now, 0777);
                }

            }

        }

    }

    public static function zipFile($fileName, $localName = '', $deleteOriginal = true): ?string
    {

        if (!file_exists($fileName)) {

            return false;

        }

        $localName = iconv("UTF-8", "CP850", $localName);

        $zip_name = preg_replace('/\.[A-Z0-9]+$/i', '', $fileName) . ".zip";

        $fileName = str_replace('\\', '/', $fileName);

        if ($localName == '') {

            $x = explode('/', $fileName);

            $localName = $x[count($x)-1];

        }

        if (file_exists($zip_name)) {

            unlink($zip_name);

        }

        $zip = new ZipArchive();
        $zip->open($zip_name, ZIPARCHIVE::CREATE);
        $zip->addFile($fileName, $localName);
        $zip->close();

        unset($zip);

        chmod($zip_name, 0777);

        if ($deleteOriginal) {

            unlink($fileName);

        }

        if (!file_exists($zip_name)) {

            return false;

        }

        return $zip_name;

    }

    public static function zipFolder($rootPath, $localName = '', $deleteOriginal = true): ?string
    {

        if (!file_exists($rootPath)) {

            return false;

        }

        $localName = iconv("UTF-8", "CP850", $localName);

        $zip_name = preg_replace('/\.[A-Z0-9]+$/i', '', $rootPath) . ".zip";

        if ($localName == '') {

            $x = explode('\\', $rootPath);

            $localName = $x[count($x)-1];

        }

        if (file_exists($zip_name)) {

            unlink($zip_name);

        }

        /*$zip = new ZipArchive();
        $zip->open($zip_name, ZIPARCHIVE::CREATE);
        $zip->addFile($fileName, $localName);
        $zip->close();

        unset($zip);*/

        // Remove any trailing slashes from the path
        $rootPath = rtrim($rootPath, '\\/');

        // Get real path for our folder
        $rootPath = realpath($rootPath);

        // Initialize archive object
        $zip = new ZipArchive();
        $zip->open($zip_name, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Create recursive directory iterator
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file)
        {
            // Skip directories (they would be added automatically)
            if (!$file->isDir())
            {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();



                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }

        // Zip archive will be created only after closing object
        $zip->close();

        unset($zip);

        chmod($zip_name, 0777);

        if ($deleteOriginal) {

            self::delFilesFromDir($rootPath);

        }

        if (!file_exists($zip_name)) {

            return false;

        }

        return $zip_name;

    }

    public static function unzipFile($fileName, $sameDir = true, $deleteOriginal = true): string
    {

        if (!file_exists($fileName))
            return false;

        if ($sameDir)
            $extractDir = preg_replace('/\/[A-Z0-9-._ =]+$/i', '', $fileName);
        else
            $extractDir = preg_replace('/.[A-Z0-9]+$/i', '/', $fileName);

        self::createDir($extractDir);

        $zip = new ZipArchive();
        $zip->open($fileName);
        $zip->extractTo($extractDir);
        $zip->close();

        unset($zip);

        if ($deleteOriginal)
            unlink($fileName);

        return $extractDir;

    }

    public static function loadFilesFromServer($server, $server_dir, $file_prefix = ''): array
    {

        $domain = 'corpcaixa';
        $user = $_SERVER['PHP_AUTH_USER'];
        $pass = $_SERVER['PHP_AUTH_PW'];

        $files = [];
        $order = [];

        $out = shell_exec("smbclient '$server' -c 'cd $server_dir; ls' -W $domain -U $user%$pass");

        if (preg_match_all('/^\s+(?P<name>' . $file_prefix . '[A-Z0-9-._ =()]+)A\s+(?P<size>[0-9]+)\s+[A-Z]{3}\s+(?P<month>[A-Z]{3})\s+(?P<day>\d{1,2})\s+(?P<hour>\d{2}):(?P<minute>\d{2}):(?P<second>\d{2})\s+(?P<year>\d{4})$/mi', $out, $matches, PREG_SET_ORDER, 0)) {

            foreach ($matches as $match) {

                $modified = DateTime::createFromFormat('j-M-Y H:i:s', $match['day'] . '-' . $match['month'] . '-' . $match['year'] . ' ' . $match['hour'] . ':' . $match['minute'] . ':' . $match['second']);

                $files[] = [
                    'name' => trim($match['name']),
                    'size' => $match['size'],
                    'last_modified' => $modified->format('Y-m-d H:i:s'),
                ];

                $order[] = $modified->format('Y-m-d H:i:s');

            }

        }

        array_multisort($order, SORT_ASC, $files);

        return $files;

    }

    public static function copyFileFromServer($server, $server_dir, $file, $local_dir): bool
    {

        $domain = 'corpcaixa';
        $user = $_SERVER['PHP_AUTH_USER'];
        $pass = $_SERVER['PHP_AUTH_PW'];

        self::createDir($local_dir);

        $local_dir = realpath($local_dir);

        shell_exec("smbclient '$server' -c 'lcd $local_dir; cd $server_dir; get \"$file\"; prompt' -W $domain -U $user%$pass");

        return file_exists($local_dir . '/' . $file);

    }

    public static function copyFileToServer($server, $server_dir, $file, $local_dir): bool
    {

        $domain = 'corpcaixa';
        $user = $_SERVER['PHP_AUTH_USER'];
        $pass = $_SERVER['PHP_AUTH_PW'];

        $local_dir = realpath($local_dir);

        shell_exec("smbclient '$server' -c 'lcd $local_dir; cd $server_dir; put \"$file\"; prompt' -W $domain -U $user%$pass");

        return file_exists($local_dir . '/' . $file);

    }

    public static function delFilesFromDir($dir)
    {

        if (!is_dir($dir))
            return;

        $rdi = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file)
            $file->isFile() ? unlink($file->getPathname()) : rmdir($file->getPathname());

        rmdir($dir);

    }

    public static function loadFilesFromDir($dir): Generator
    {

        $rdi = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file)
            if ($file->isFile())
                yield $file->getPathname();

    }

    public static function loadDirFromDir($dir): Generator
    {

        $rdi = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file)
            if (!$file->isFile())
                yield $file->getPathname();

    }

    public static function exportCsv($pdo, $sql, $file_name)
    {

        $stmt = $pdo->query($sql);

        $file = fopen($file_name, 'w');

        $linhas = 0;

        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $linhas++;

            if ($linhas == 1) {

                $linha = '';

                foreach ($result as $key => $value) {

                    $linha .= $key . ';';

                }

                fwrite($file, $linha . PHP_EOL);

            }

            $linha = '';

            foreach ($result as $key => $value) {

                $linha .= trim(str_replace(';', ',', $value)) . ';';

            }

            fwrite($file, $linha . PHP_EOL);

        }

        fclose($file);

    }


}
