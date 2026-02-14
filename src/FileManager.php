<?php
namespace Fluxion;

use Generator;
use DirectoryIterator;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileManager
{

    public static function createDir($dir): void
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

}
