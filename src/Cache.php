<?php
namespace Fluxion;

class Cache
{

    public const CACHE_LIMIT = 100;

    private static array $_data = [];

    private function __construct() {}

    public static function setValue($key, $value): void
    {

        if (count(self::$_data) >= self::CACHE_LIMIT) {
            array_shift(self::$_data);
        }

        self::$_data[$key] = $value;

    }

    public static function getValue($key): mixed
    {
        return self::$_data[$key] ?? null;
    }

    public static function hasValue($key): bool
    {
        return array_key_exists($key, self::$_data);
    }

    /** @noinspection PhpUnused */
    public static function getValues(): array
    {
        return self::$_data;
    }

    public static function clear(): void
    {
        self::$_data = [];
    }

}