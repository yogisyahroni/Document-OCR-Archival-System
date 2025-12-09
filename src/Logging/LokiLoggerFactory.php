<?php

namespace App\Logging;

use Monolog\Logger;
use GuzzleHttp\Client;
use Monolog\Level;

class LokiLoggerFactory
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('loki');
        
        // Create a handler that sends logs to Loki
        $handler = $this->createLokiHandler($config);
        
        // Set formatter if specified
        if (isset($config['formatter'])) {
            $formatter = new $config['formatter']();
            $handler->setFormatter($formatter);
        } else {
            $handler->setFormatter(new \Monolog\Formatter\JsonFormatter());
        }
        
        $logger->pushHandler($handler);
        
        return $logger;
    }
    
    private function createLokiHandler(array $config): \Monolog\Handler\HandlerInterface
    {
        $lokiUrl = $config['loki_url'] ?? 'http://localhost:3100';
        $labels = $config['labels'] ?? [];
        
        // In a real implementation, we would use a proper Loki handler
        // For now, we'll create a custom handler that sends logs to Loki
        return new CustomLokiHandler($lokiUrl, $labels, $this->parseLevel($config['level'] ?? 'debug'));
    }
    
    private function parseLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Info,
        };
    }
}

class CustomLokiHandler extends \Monolog\Handler\AbstractProcessingHandler
{
    private string $lokiUrl;
    private array $labels;
    private Client $httpClient;
    protected ?\Monolog\Formatter\FormatterInterface $formatter = null;
    
    public function __construct(string $lokiUrl, array $labels, Level $level = Level::Debug)
    {
        $this->lokiUrl = $lokiUrl;
        $this->labels = $labels;
        $this->httpClient = new Client();
        
        parent::__construct($level);
    }
    
    public function setFormatter(\Monolog\Formatter\FormatterInterface $formatter): self
    {
        $this->formatter = $formatter;
        return $this;
    }
    
    protected function write(\Monolog\LogRecord $record): void
    {
        $formattedRecord = $record;
        if ($this->formatter) {
            $formattedRecord = $this->formatter->format($record);
        }
        
        $logData = [
            'streams' => [
                [
                    'stream' => $this->labels,
                    'values' => [
                        [
                            $record->datetime->format('U.u0') . '000', // timestamp in nanoseconds
                            json_encode([
                                'level' => $record->level->getName(),
                                'message' => $record->message,
                                'context' => $record->context,
                                'extra' => $record->extra
                            ])
                        ]
                    ]
                ]
            ]
        ];
        
        try {
            $response = $this->httpClient->request('POST', $this->lokiUrl . '/loki/api/v1/push', [
                'json' => $logData,
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
        } catch (\Exception $e) {
            // In a real implementation, we might want to handle errors differently
            error_log("Failed to send log to Loki: " . $e->getMessage());
        }
    }
}