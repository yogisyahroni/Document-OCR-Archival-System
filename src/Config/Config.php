<?php

namespace App\Config;

class Config
{
    private static ?array $config = null;

    public static function get(string $key, $default = null)
    {
        if (self::$config === null) {
            self::loadConfig();
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
            self::loadConfig();
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

    private static function loadConfig(): void
    {
        $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->safeLoad();

        self::$config = [
            'app' => [
                'env' => $_ENV['APP_ENV'] ?? 'dev',
                'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
                'port' => (int)($_ENV['APP_PORT'] ?? 8000),
            ],
            'database' => [
                'connection' => $_ENV['DB_CONNECTION'] ?? 'pgsql',
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => (int)($_ENV['DB_PORT'] ?? 5432),
                'database' => $_ENV['DB_DATABASE'] ?? 'document_ocr',
                'username' => $_ENV['DB_USERNAME'] ?? 'user',
                'password' => $_ENV['DB_PASSWORD'] ?? 'password',
            ],
            'redis' => [
                'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
                'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
                'password' => $_ENV['REDIS_PASSWORD'] ?? null,
                'db' => (int)($_ENV['REDIS_DB'] ?? 0),
            ],
            'elasticsearch' => [
                'host' => $_ENV['ELASTICSEARCH_HOST'] ?? 'localhost',
                'port' => (int)($_ENV['ELASTICSEARCH_PORT'] ?? 9200),
                'index' => $_ENV['ELASTICSEARCH_INDEX'] ?? 'documents',
            ],
            's3' => [
                'bucket' => $_ENV['S3_BUCKET'] ?? 'document-ocr-bucket',
                'key' => $_ENV['S3_KEY'] ?? '',
                'secret' => $_ENV['S3_SECRET'] ?? '',
                'region' => $_ENV['S3_REGION'] ?? 'us-east-1',
                'endpoint' => $_ENV['S3_ENDPOINT'] ?? null,
            ],
            'jwt' => [
                'secret' => $_ENV['JWT_SECRET'] ?? '',
                'refresh_secret' => $_ENV['JWT_REFRESH_SECRET'] ?? '',
                'ttl' => (int)($_ENV['JWT_TTL'] ?? 15), // in minutes
                'refresh_ttl' => (int)($_ENV['JWT_REFRESH_TTL'] ?? 10080), // in minutes (7 days)
            ],
            'ocr' => [
                'engine' => $_ENV['OCR_ENGINE'] ?? 'tesseract',
                'timeout' => (int)($_ENV['OCR_TIMEOUT'] ?? 300), // in seconds
                'language' => $_ENV['OCR_LANGUAGE'] ?? 'eng',
            ],
            'queue' => [
                'connection' => $_ENV['QUEUE_CONNECTION'] ?? 'redis',
                'failed_driver' => $_ENV['QUEUE_FAILED_DRIVER'] ?? 'database',
                'failed_table' => $_ENV['QUEUE_FAILED_TABLE'] ?? 'failed_jobs',
            ],
            'logging' => [
                'channel' => $_ENV['LOG_CHANNEL'] ?? 'stderr',
                'level' => $_ENV['LOG_LEVEL'] ?? 'info',
            ],
            'monitoring' => [
                'prometheus_enabled' => filter_var($_ENV['PROMETHEUS_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'grafana_url' => $_ENV['GRAFANA_URL'] ?? 'http://localhost:3000',
            ]
        ];
    }
}