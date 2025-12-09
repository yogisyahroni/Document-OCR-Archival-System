<?php

use App\Config\Config;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Initialize the configuration
Config::get('app.name');

// Simple container implementation for basic dependency injection
class SimpleContainer
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $abstract, callable $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable $concrete): void
    {
        $this->bindings[$abstract] = function ($container) use ($abstract, $concrete) {
            if (!isset($container->instances[$abstract])) {
                $container->instances[$abstract] = $concrete($container);
            }
            return $container->instances[$abstract];
        };
    }

    public function get(string $abstract)
    {
        if (!isset($this->bindings[$abstract])) {
            // Try to instantiate directly if not bound
            if (class_exists($abstract)) {
                return new $abstract();
            }
            throw new Exception("Binding not found: {$abstract}");
        }
        
        return $this->bindings[$abstract]($this);
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }
}

// Create the application container
$app = new SimpleContainer();

// Register core services
$app->bind('config', function () {
    return Config::get('app');
});

// Database connection
$app->singleton(PDO::class, function () {
    $config = Config::get('database');
    
    $dsn = sprintf(
        '%s:host=%s;port=%s;dbname=%s',
        $config['connection'],
        $config['host'],
        $config['port'],
        $config['database']
    );
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    return new PDO($dsn, $config['username'], $config['password'], $options);
});

// S3 Client
$app->singleton(Aws\S3\S3Client::class, function () {
    $config = Config::get('s3');
    
    return new Aws\S3\S3Client([
        'version' => 'latest',
        'region' => $config['region'],
        'credentials' => [
            'key' => $config['key'],
            'secret' => $config['secret'],
        ],
        'endpoint' => $config['endpoint'] ?: null,
    ]);
});

// Register repositories
$app->singleton(App\Repositories\UserRepository::class, function ($container) {
    return new App\Repositories\UserRepository($container->get(PDO::class));
});

$app->singleton(App\Repositories\DocumentRepository::class, function ($container) {
    return new App\Repositories\DocumentRepository($container->get(PDO::class));
});

// Register services
$app->singleton(App\Services\AuthService::class, function ($container) {
    return new App\Services\AuthService(
        $container->get(App\Repositories\UserRepository::class),
        Config::get('jwt.secret'),
        Config::get('jwt.refresh_secret'),
        Config::get('jwt.ttl'),
        Config::get('jwt.refresh_ttl')
    );
});

$app->singleton(App\Services\DocumentService::class, function ($container) {
    return new App\Services\DocumentService(
        $container->get(App\Repositories\DocumentRepository::class),
        $container->get(Aws\S3\S3Client::class),
        $container->get(PDO::class)
    );
});

return $app;