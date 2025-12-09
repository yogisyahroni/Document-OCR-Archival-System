<?php

namespace App\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    public function dispatch(ServerRequestInterface $request, SimpleContainer $container): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                try {
                    $handler = $route['handler'];
                    return $handler($request, $container);
                } catch (\Exception $e) {
                    // Log error
                    error_log("Route error: " . $e->getMessage());
                    
                    return new \GuzzleHttp\Psr7\Response(
                        500,
                        ['Content-Type' => 'application/json'],
                        json_encode(['error' => 'Internal server error'])
                    );
                }
            }
        }
        
        // Route not found
        return new \GuzzleHttp\Psr7\Response(
            404,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'Route not found'])
        );
    }
}