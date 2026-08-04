<?php

class InteractionManager {
    private array $lastCommandResults = [ ];

    // MAGIC FUNCTIONS
    public function __construct(
        private ConfigManager $configManager,
        private UserContext $userContext,
    ) { }

    // PUBLIC FUNCTIONS
    public function sendCommands( string $serverId, array $commands ): bool {
        $url = $this->getInteractUrl( $serverId );
        $success = true;
        $this->lastCommandResults = [ ];

        foreach ( $commands as $command ) {
            $command = trim( (string) $command );
            if ( $command === "" ) {
                continue;
            }

            $curlRequest = new CurlController( $url, $this->configManager->getPanelApiKey( ), [
                "command" => $command,
            ] );

            $response = $curlRequest->send( );
            $responseInfo = $curlRequest->getLastResponseInfo( );
            $httpStatus = (int) ( $responseInfo[ "httpStatus" ] ?? 0 );
            $commandSucceeded =
                (int) ( $responseInfo[ "errorNumber" ] ?? 0 ) === 0 &&
                $httpStatus >= 200 &&
                $httpStatus < 300;

            $this->lastCommandResults[ ] = [
                "serverId" => $serverId,
                "url" => $url,
                "command" => $command,
                "httpStatus" => $httpStatus,
                "transportErrorNumber" => (int) ( $responseInfo[ "errorNumber" ] ?? 0 ),
                "transportError" => (string) ( $responseInfo[ "errorMessage" ] ?? "" ),
                "response" => is_string( $response ) ? mb_substr( $response, 0, 2000 ) : $response,
                "success" => $commandSucceeded,
            ];

            if ( !$commandSucceeded ) {
                $success = false;

                new Logger( Logger::CHANNEL_API )->error( "Interaction command failed", [
                    "url" => $url,
                    "http_status" => $httpStatus,
                    "transport_error_number" => $responseInfo[ "errorNumber" ] ?? 0,
                    "transport_error" => $responseInfo[ "errorMessage" ] ?? "",
                    "response" => is_scalar( $response ) ? (string) $response : gettype( $response ),
                    "command" => $command,
                ] );
            }
        }

        return $success;
    }

    public function getLastCommandResults( ): array { return $this->lastCommandResults; }

    public function viewerBatClaimed( string $batName ): bool {
        if ( !$this->userContext->userLoggedIn( ) ) {
            new Logger( Logger::CHANNEL_API )->warning(
                "Interaction attempted without an identified user",
            );

            return false;
        }

        $viewerId = $this->userContext->getUserId( );
        $viewerName = $this->userContext->getDisplayName( );
        $batName = $batName;
        $payload = [
            "viewer_id" => $viewerId,
            "name" => "" !== $batName ? [ "text" => $batName ] : [ "text" => "{$viewerName}'s Pet" ],
        ];

        $json = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( !is_string( $json ) || "" === $json ) {
            return false;
        }

        $serverId = $this->configManager->getActiveProfile( )->getServerId( );
        $commands = [
            "data modify storage xogoria:bat claim set value {$json}",
            "execute as Xogue at @s run function xogoria:claim_bat",
        ];

        $success = $this->sendCommands( $serverId, $commands );
        return $success;
    }

    // PRIVATE FUNCTIONS
    private function getInteractUrl( string $serverId ): string {
        $panelBase = $this->configManager->getPanelBaseUrl( );
        $serverPath = $this->configManager->getServerPath( );
        $interactUrl = $panelBase . $serverPath . $serverId . "/command";
        return $interactUrl;
    }
}
