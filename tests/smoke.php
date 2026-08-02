<?php

declare( strict_types=1 );
define( "XOG_ROOT", dirname( __DIR__ ) . "/html" );
require dirname( __DIR__ ) . "/vendor/autoload.php";

$services = new ServiceFactory( );
$web = new WebController( $services );
assert( $web->getActiveGame( ) instanceof Game );
assert( $web->getActiveProfile( ) instanceof Profile );
assert( $services->contextManager( ) === $services->contextManager( ) );
assert( $services->contextManager( )->getInputData( ) === $services->contextManager( )->getInputData( ) );
assert( isset( $services->adminResourceManager( )->definitions( )[ "users" ] ) );
assert( isset( $services->adminConfigManager( )->files( )[ "minecraft" ] ) );
assert( $services->clipDeletionRegistry( )->all( ) === [ ] );
$textPolicy = $services->userTextPolicy( );
assert( $textPolicy->isAllowed( "Flappy" ) );
assert( $textPolicy->isAllowed( "Classy Bat" ) );
assert( !$textPolicy->isAllowed( "f.u.c.k" ) );
assert( !$textPolicy->isAllowed( "sh1tbat" ) );
assert( !$textPolicy->isAllowed( "kill-yourself" ) );
$success = new WorkerResult( [ "item" => "value" ], "Completed." );
assert( $success->isSuccess( ) );
assert( json_decode( $success->toJson( ), true, flags: JSON_THROW_ON_ERROR )[ "success" ] === true );
$failure = WorkerResult::failure( "Bad request.", "bad_request", 422 );
assert( !$failure->isSuccess( ) );
assert( $failure->getHttpStatus( ) === 422 );

echo "Smoke checks passed\n";
