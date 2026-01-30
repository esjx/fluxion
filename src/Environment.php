<?php
namespace Esj\Core;

class Environment
{

    public function load(string $file): void
    {

        $parsed = parse_ini_file($file, true);

        $_ENV['ENVIRONMENT'] = $_ENV['ENVIRONMENT'] ?? $parsed['ENVIRONMENT'];

        foreach ($parsed[$_ENV['ENVIRONMENT']] as $key => $value) {
            $_ENV[$key] = $value;
        }

    }

}
