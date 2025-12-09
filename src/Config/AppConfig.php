<?php

namespace App\Config;

class AppConfig
{
    private static ?array $config = null;

    public static function load(): void
    {
        $configFiles = glob(__DIR__ . '/../../config/*.php');
        self::$config = [];
        
        foreach ($configFiles as $configFile) {
            $configName = basename($configFile, '.php');
            $configData = include $configFile;
            self::$config[$configName] = $configData;
        }
    }

    public static function get(string $key, $default = null)
    {
        if (self::$config === null) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function set(string $key, $value): void
    {
        if (self::$config === null) {
            self::load();
        }

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }
}