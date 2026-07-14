<?php
require_once __DIR__ . '/bootstrap.php';

$services = new ServiceFactory();
$webController = new WebController($services);
$assetManager = $webController->getAssetManager();

$fileMap = new FileMap(__DIR__);
$headFilepath = $fileMap->findFullFilepath('head.php');
$headerFilepath = $fileMap->findFullFilepath('header.php');
$navFilepath = $fileMap->findFullFilepath('nav.php');

header( 'Content-Type: text/html; charset=utf-8' );
