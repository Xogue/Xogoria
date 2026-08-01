<?php
require_once __DIR__ . "/bootstrap.php";

// INITIALIZE CORE SERVICES AND CONTROLLERS
$services = new ServiceFactory( );
$webController = new WebController( $services );
$assetManager = $webController->getAssetManager( );

// INITIALIZE FILE PATHS FOR CORE HTML PARTIALS
$fileMap = new FileMap( __DIR__ );
$headFilepath = $fileMap->findFullFilepath( "head.php" );
$headerFilepath = $fileMap->findFullFilepath( "header.php" );
$navFilepath = $fileMap->findFullFilepath( "nav.php" );

// SET CONTENT TYPE FOR HTML OUTPUT
header( "Content-Type: text/html; charset=utf-8" );
