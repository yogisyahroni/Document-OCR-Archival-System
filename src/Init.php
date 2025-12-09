<?php

namespace App;

use App\Config\Config;

class Init
{
    public static function initialize(): void
    {
        // Load environment variables
        if (file_exists(dirname(__DIR__) . '/.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
            $dotenv->safeLoad();
        }

        // Initialize configuration
        self::initializeConfig();
        
        // Set up error handling
        self::setupErrorHandling();
        
        // Set up timezone
        date_default_timezone_set(Config::get('app.timezone', 'UTC'));
    }

    private static function initializeConfig(): void
    {
        $configFiles = glob(dirname(__DIR__) . '/config/*.php');
        foreach ($configFiles as $configFile) {
            $configName = basename($configFile, '.php');
            $configData = include $configFile;
            Config::set($configName, $configData);
        }
    }

    private static function setupErrorHandling(): void
    {
        // Set up error reporting
        error_reporting(E_ALL);
        
        // Set up custom error handler
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        
        // Set up exception handler
        set_exception_handler(function ($exception) {
            $error = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'timestamp' => date('c')
            ];
            
            // Log error
            error_log(json_encode($error));
            
            // In production, don't show detailed error information
            if (Config::get('app.debug')) {
                echo json_encode([
                    'error' => $error,
                    'success' => false
                ]);
            } else {
                echo json_encode([
                    'error' => 'An error occurred',
                    'success' => false
                ]);
            }
        });
    }
}