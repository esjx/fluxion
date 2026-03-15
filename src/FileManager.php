<?php
namespace Fluxion;

use Generator;
use DirectoryIterator;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class FileManager
{

    public static function createDir(&$dir): void
    {

        $dir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);

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

    public static function delTree($dir, $remove_dir = true): void
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

        $file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);

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

    /**
     * @throws FluxionException
     */
    public static function loadCsv(string $filename, ?int $length = null, string $separator = ',',
                                   string $enclosure = '"', string $escape = '\\'): Generator
    {

        if (!file_exists($filename)) {
            throw new FluxionException(message: "Arquivo '$filename' não existe!");
        }

        $handle = fopen($filename, "r");

        if ($handle === false) {
            throw new FluxionException(message: "Erro ao abrir arquivo '$filename'!");
        }

        while (($data = fgetcsv($handle, $length, $separator, $enclosure, $escape)) !== false) {
            yield $data;
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

    public static function unzipFile(string $filename, bool $same_dir = true, $delete_original = true): string
    {

        $filename = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filename);

        if (!file_exists($filename))
            return false;

        if ($same_dir)
            $extractDir = preg_replace('/\/[A-Z0-9-._ =]+$/i', '', $filename);
        else
            $extractDir = preg_replace('/.[A-Z0-9]+$/i', '/', $filename);

        self::createDir($extractDir);

        $zip = new ZipArchive();
        $zip->open($filename);
        $zip->extractTo($extractDir);
        $zip->close();

        unset($zip);

        if ($delete_original)
            unlink($filename);

        return $extractDir;

    }

}
