<?php

require_once dirname( __DIR__, 4 ) . "/includes/session.php";

$returnTo = isset( $_GET[ "returnTo" ] ) ? (string) $_GET[ "returnTo" ] : "";
if ( $returnTo !== "" ) {
    $returnTo = forceRelativeUrl( $returnTo );
    $_SESSION[ "redirectAfterTwitchLogin" ] = $returnTo;
}

$webController->loginToTwitch( );
