<?php
require_once dirname(__DIR__) . '/includes/session.php';


$filters = [];
$category = $_GET["category"];
$perms = $_GET["perms"];

if ($category != "all") {
    $filters['category'] = $category;
}
if ($perms != "everyone") {
    $filters['perms'] = $perms;
}

$mySqlManager = $webController->getMySqlManager();
$collectionManager = $webController->getCollectionManager();

$commands = $mySqlManager->fetchData('allCommands');
$collectionManager->insertCommands($commands);
$commandHtml = $collectionManager->assembleCommands($filters);

echo $commandHtml;
?>
