<?php

namespace Fluxion;

final class AutoLoader2
{

    private array $prefixes = [];

    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {

        if (self::$instance === null) {

            self::$instance = new self();
            spl_autoload_register([self::$instance, 'loadClass']);

        }

        return self::$instance;

    }

    public static function addNamespace($namespace, $base_dir, $prepend = false): void
    {
        self::getInstance()->_addNamespace($namespace, $base_dir, $prepend);
    }

    public function _addNamespace($namespace, $base_dir, $prepend = false): void
    {

        $namespace = trim($namespace, '\\') . '\\';

        $base_dir = rtrim($base_dir, DIRECTORY_SEPARATOR) . '/';

        if (isset($this->prefixes[$namespace]) === false) {
            $this->prefixes[$namespace] = [];
        }

        if ($prepend) {
            array_unshift($this->prefixes[$namespace], $base_dir);
        }

        else {
            $this->prefixes[$namespace][] = $base_dir;
        }

    }

    public function loadClass($class): ?string
    {

        // the current namespace prefix
        $prefix = $class;

        // work backwards through the namespace names of the fully qualified
        // class name to find a mapped file name
        while (false !== $pos = strrpos($prefix, '\\')) {

            // retain the trailing namespace separator in the prefix
            $prefix = substr($class, 0, $pos + 1);

            // the rest is the relative class name
            $relative_class = substr($class, $pos + 1);

            // try to load a mapped file for the prefix and relative class
            $mapped_file = $this->loadMappedFile($prefix, $relative_class);

            if ($mapped_file) {
                return $mapped_file;
            }

            // remove the trailing namespace separator for the next iteration of strrpos()
            $prefix = rtrim($prefix, '\\');

        }

        // never found a mapped file
        return false;

    }

    protected function loadMappedFile($prefix, $relative_class): ?string
    {

        // are there any base directories for this namespace prefix?
        if (isset($this->prefixes[$prefix]) === false) {
            return null;
        }

        // look through base directories for this namespace prefix
        foreach ($this->prefixes[$prefix] as $base_dir) {

            // replace the namespace prefix with the base directory, replace namespace separators with directory
            // separators in the relative class name, append with .php
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            // if the mapped file exists, require it
            if ($this->requireFile($file)) {
                // yes, we're done
                return $file;
            }

        }

        // never found it
        return null;

    }

    protected function requireFile($file): bool
    {

        if (file_exists($file)) {
            require $file;
            return true;
        }

        return false;

    }

}
