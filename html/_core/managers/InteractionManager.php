<?php

class InteractionManager {
    public function __construct(
        private ConfigManager $configManager, 
        private UserContext $userContext
    ) { }

    public function sendCommands( string $serverId, array $commands ): bool {
        $url      = $this->getInteractUrl( $serverId );
        $success  = true;

        foreach ( $commands as $command ) {
            $command = trim( (string) $command );
            if ( $command === '' ) {
                continue;
            }

            $curlRequest = new CurlController(
                $url,
                $this->configManager->getPanelApiKey(),
                ['command' => $command]
            );

            $response = $curlRequest->send();
            if ( !empty( $response ) ) {
                $success = false;
                (new Logger(Logger::CHANNEL_API))->error('Interaction command failed', [
                    'url' => $url,
                    'response' => (string) $response,
                    'command' => $command,
                ]);
            }
        }

        return $success;
    }

    public function viewerBatClaimed( string $batName ): bool {
        if ( !$this->userContext->userLoggedIn() ) {
            (new Logger(Logger::CHANNEL_API))->warning('Interaction attempted without an identified user');
            return false;
        }

        $viewerId   = $this->userContext->getUserId();
        $viewerName = $this->userContext->getDisplayName();
        $batName    = $batName;
        $payload    = [
            'viewer_id' => $viewerId,
            'name'      => '' !== $batName ? ['text' => $batName] : ['text' => "{$viewerName}'s Pet"],
        ];

        $json = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( !is_string( $json ) || '' === $json ) {
            return false;
        }

        $serverId = $this->configManager->getActiveProfile()->getServerId();
        $command  = 'say This command needs to be updated';
        // [
        // 'data modify storage xogoria:bat claim set value ' . $json,
        // 'execute as Xogue at @s run function xogoria:claim_bat',
        // ];

        $success = $this->sendCommands( $serverId, [$command] );
        return $success;
    }

    // PRIVATE FUNCTIONS
    private function getInteractUrl( string $serverId ): string {
        $panelBase = $this->configManager->getPanelBaseUrl();
        $serverPath = $this->configManager->getServerPath();
        $interactUrl = $panelBase . $serverPath . $serverId . '/command';
        return $interactUrl;
    }
}
