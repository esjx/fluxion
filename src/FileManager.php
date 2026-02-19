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

    public static function createDir($dir): void
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
     * @throws Exception
     */
    public static function loadCsv(string $filename, ?int $length = null, string $separator = ',',
                                   string $enclosure = '"', string $escape = '\\'): Generator
    {

        if (!file_exists($filename)) {
            throw new Exception(message: "Arquivo '$filename' não existe!");
        }

        $handle = fopen($filename, "r");

        if ($handle === false) {
            throw new Exception(message: "Erro ao abrir arquivo '$filename'!");
        }

        while (($data = fgetcsv($handle, $length, $separator, $enclosure, $escape)) !== false) {
            yield $data;
        }

    }

    public static function unzipFile($fileName, $sameDir = true, $deleteOriginal = true): string
    {

        $fileName = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fileName);

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

}
