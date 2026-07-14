<?php

final class AdminConfigManager {
    private const CONFIG_DIRECTORY = XOG_ROOT . '/_core/_init/config';
    private const BACKUP_DIRECTORY = XOG_ROOT . '/_core/_init/config/_backups';
    private const FILES = [
        'core' => 'core.json',
        'twitch' => 'twitch.json',
        'minecraft' => 'gameConfigs/minecraft.json',
        'hytale' => 'gameConfigs/hytale.json'
    ];

    public function __construct(private JsonHandler $json, private Logger $logger) { }

    public function files(): array {
        $files = [];
        foreach (self::FILES as $key => $relativePath) {
            $files[$key] = [
                'label' => ucfirst($key),
                'data' => $this->json->safeLoad(self::CONFIG_DIRECTORY . '/' . $relativePath),
            ];
        }
        return $files;
    }

    public function save(string $key, string $source): bool {
        $relativePath = self::FILES[$key] ?? null;
        if ($relativePath === null) { throw new InvalidArgumentException('Unknown configuration file'); }

        try {
            $data = json_decode($source, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Invalid JSON: ' . $error->getMessage());
        }
        if (!is_array($data) || $data === []) { throw new InvalidArgumentException('Configuration must be a non-empty JSON object'); }
        $this->validate($key, $data);

        $path = self::CONFIG_DIRECTORY . '/' . $relativePath;
        $this->backup($key, $path);
        if (!safeWriteJson($path, $data)) { return false; }
        $this->logger->info('Admin configuration updated', ['config' => $key]);
        return true;
    }

    private function validate(string $key, array $data): void {
        if (in_array($key, ['minecraft', 'hytale'], true)) {
            foreach (['simpleTypes', 'specialTypes', 'profiles'] as $required) {
                if (!isset($data[$required]) || !is_array($data[$required])) {
                    throw new InvalidArgumentException("Game configuration requires {$required}");
                }
            }
        }
        if ($key === 'core' && (!isset($data['activeGame'], $data['activeProfile']))) {
            throw new InvalidArgumentException('Core configuration requires activeGame and activeProfile');
        }
    }

    private function backup(string $key, string $path): void {
        if (!is_dir(self::BACKUP_DIRECTORY) && !mkdir(self::BACKUP_DIRECTORY, 0775, true) && !is_dir(self::BACKUP_DIRECTORY)) {
            throw new RuntimeException('Unable to create configuration backup directory');
        }
        $destination = self::BACKUP_DIRECTORY . '/' . $key . '-' . date('Ymd-His') . '.json';
        if (!copy($path, $destination)) { throw new RuntimeException('Unable to back up configuration'); }
    }
}
