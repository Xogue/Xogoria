<?php

class InteractionManager {
    use reporter;
    public function __construct(
        private ConfigManager $configManager, 
        private UserContext $userContext
    ) { $this->initReporter(); }

    public function sendCommands( string $serverId, array $commands ): bool {
        $url      = $this->getInteractUrl( $serverId );
        $postData = [];

        foreach($commands as $command) {
            $postData[] = ['command' => $command];
        }

        $curlRequest = new CurlController( $url, $this->configManager->getPanelApiKey(), $postData );
        $response = $curlRequest->send();
        return true;

        // if ( $requestInfo['errorNumber'] ) {
        //     ReportController::createReport(CodeLibrary::CURL__URL_ERROR, [
        //         'url'   => $url,
        //         'error' => $requestInfo['errorMessage']
        //     ]);
        //     return false;
        // }

        // if ( $requestInfo['responseCode'] >= 200 && $requestInfo['responseCode'] < 300 ) {
        //     ReportController::createReport(CodeLibrary::CURL__COMMAND_SEND_SUCCESS, [
        //         'serverId' => $serverId,
        //         'command'  => $command
        //     ]);
        //     return true;
        // } else {
        //     ReportController::createReport(CodeLibrary::CURL__COMMAND_SEND_FAILED, [
        //         'serverId' => $serverId,
        //         'httpCode' => $requestInfo['responseCode'],
        //         'response' => $requestInfo['responseBody']
        //     ]);
        //     return false;
        // }
    }

    public function viewerBatClaimed( string $batName ): bool {
        if ( !$this->userContext->userLoggedIn() ) {
            $this->statereportLogger->saveState(CodeLibrary::USER__NOT_FOUND);
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
            $this->statereportLogger->saveState(CodeLibrary::JSON__ENCODE_FAILED);
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