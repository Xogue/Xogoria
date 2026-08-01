<?php

ini_set( "session.gc_maxlifetime", "86400" );
ini_set( "session.cookie_lifetime", "0" );

if ( session_status( ) === PHP_SESSION_NONE ) {
    session_start( );
}

if ( !defined( "XOG_ROOT" ) ) {
    define( "XOG_ROOT", dirname( __DIR__ ) );
    require_once XOG_ROOT . "/../vendor/autoload.php";
}

$displayErrors = getenv( "XOG_DISPLAY_ERRORS" ) === "1";
ini_set( "display_errors", $displayErrors ? "1" : "0" );
error_reporting( E_ALL );
