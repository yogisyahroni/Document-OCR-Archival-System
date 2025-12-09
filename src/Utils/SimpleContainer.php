<?php

namespace App\Utils;

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
            throw new \Exception("Binding not found: {$abstract}");
        }
        
        return $this->bindings[$abstract]($this);
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }
}