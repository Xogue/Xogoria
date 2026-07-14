<?php

class UserController {
    public function __construct(
        private MySqlManager $mySqlManager,
        private UserContext $userContext
    ) { }

    public function syncTwitchSessionUser(): bool {
        $twitchUserId      = $this->userContext->getUserId();
        $twitchLoginName   = $this->userContext->getLoginName();
        $twitchDisplayName = $this->userContext->getDisplayName() ?? $twitchLoginName;

        if ( $twitchUserId === '' || $twitchLoginName === '' ) {
            (new Logger(Logger::CHANNEL_WEB))->warning('Cannot sync an incomplete Twitch session');
            return false;
        }

        $rows = $this->mySqlManager->selectUserWithId($twitchUserId);
        if ( $rows !== false && !empty( $rows ) ) { return true; }

        $inserted = $this->mySqlManager->insertUser($twitchUserId, $twitchLoginName, $twitchDisplayName);
        if ( !$inserted ) {
            (new Logger(Logger::CHANNEL_WEB))->error('Failed to insert Twitch user', ['user_id' => $twitchUserId]);
            return false;
        }

        return true;
    }

    public function logout(): void {
        $this->userContext->logout();
        session_unset();
        session_destroy();
        setcookie( session_name(), '', time() - 3600, '/' );
        header( 'Location: ' . '/about.php' );
    }
}
