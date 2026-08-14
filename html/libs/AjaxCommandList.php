<?php
require_once dirname( __DIR__ ) . "/includes/session.php";

$filters = [ ];
$category = $_GET[ "category" ];
$perms = $_GET[ "perms" ];

if ( $category != "all" ) {
    $filters[ "category" ] = $category;
}

if ( $perms != "everyone" ) {
    $filters[ "perms" ] = $perms;
}

// COLLECT DATA
$mySqlManager = $webController->getMySqlManager( );
$commands = $mySqlManager->fetchData( "allCommands" );

// COMPILE COMMANDS
$collectionManager = $webController->getCollectionManager( );
$collectionManager->insertCommands( $commands );

// ASSEMBLE HTML
$commandHtml = $collectionManager->assembleCommands( $filters );

echo $commandHtml;
?>
