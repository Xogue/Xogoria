<?php

final class Logger {
    public const CHANNEL_API = 'api';
    public const CHANNEL_WEB = 'web';
    public const CHANNEL_COMMON = 'common';

    private const LOG_DIRECTORY = XOG_ROOT . '/_core/_init/_logs';

    public function __construct(private string $channel = self::CHANNEL_COMMON) {
        if (!in_array($channel, [self::CHANNEL_API, self::CHANNEL_WEB, self::CHANNEL_COMMON], true)) {
            throw new InvalidArgumentException("Unknown log channel: {$channel}");
        }
    }

    public function info(string $message, array $context = []): void {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->write('error', $message, $context);
    }

    public function exception(Throwable $error, array $context = []): void {
        $this->error($error->getMessage(), $context + [
            'exception' => $error::class,
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);
    }

    private function write(string $level, string $message, array $context): void {
        if (!is_dir(self::LOG_DIRECTORY) && !mkdir(self::LOG_DIRECTORY, 0775, true) && !is_dir(self::LOG_DIRECTORY)) {
            throw new RuntimeException('Unable to create log directory');
        }

        $record = [
            'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('America/Chicago')))->format(DateTimeInterface::ATOM),
            'level' => $level,
            'channel' => $this->channel,
            'message' => $message,
            'context' => $this->normalize($context),
        ];
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false || file_put_contents(self::LOG_DIRECTORY . "/{$this->channel}.log", $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Unable to write log record');
        }
    }

    private function normalize(array $context): array {
        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                $context[$key] = ['type' => $value::class, 'message' => $value->getMessage()];
            } elseif (is_object($value)) {
                $context[$key] = method_exists($value, '__toString') ? (string) $value : $value::class;
            } elseif (is_resource($value)) {
                $context[$key] = get_resource_type($value);
            }
        }
        return $context;
    }
}
