<?php

require_once dirname(__DIR__, 4) . '/includes/session.php';

function redirectTwitchLoginSuccess(WebController $webController): void {
    unset( $_SESSION['twitchLoginError'] );
    header( 'Location: ' . $webController->getPostLoginRedirect() );
    exit;
}

function redirectTwitchLoginFailure( string $reason ): void {
    $logger = new Logger(Logger::CHANNEL_WEB);
    $_SESSION['twitchLoginError'] = $reason;

    $target = (string) ( $_SESSION['redirectAfterTwitchLogin'] ?? '/interact.php' );
    $target = forceRelativeUrl( $target );
    $separator = str_contains( $target, '?' ) ? '&' : '?';

    $logger->error('Twitch login failed', ['reason' => $reason]);
    header( 'Location: ' . $target . $separator . 'login=failed&reason=' . rawurlencode( $reason ) );
    exit;
}

$accessToken = $webController->getAccessCode();
if ( $accessToken === null ) {
    redirectTwitchLoginFailure( 'no_access_token' );
}

if ( !$webController->getUserData( $accessToken ) ) {
    redirectTwitchLoginFailure( 'user_data_failed' );
}

if ( !$webController->syncTwitchSessionUser() ) {
    redirectTwitchLoginFailure( 'local_user_sync_failed' );
}

redirectTwitchLoginSuccess($webController);
?>
