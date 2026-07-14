<?php
require_once dirname(__DIR__) . '/../includes/bootstrap.php';

$services = new ServiceFactory();
$apiKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
$expectedKey = $services->contextManager()->getPrivateApi()->getWebApiKey();

if ( $apiKey === '' || !is_string( $expectedKey ) || $expectedKey === '' || !hash_equals( $expectedKey, $apiKey ) ) {
    ApiController::authFailed('Invalid API key');
}

// Timeout in seconds (long-poll)
$timeout = $_GET['timeout'] ?? '0';
$timeout = is_string($timeout) ? (int)$timeout : 0;
if ($timeout < 0) { $timeout = 0; }
if ($timeout > 300) { $timeout = 300; } // safety cap

$queue = $services->soundQueueManager();
$item = $queue->pull($timeout);

ApiController::sendJson([
    'ok' => true,
    'item' => $item, // null if none (timeout)
    'count' => $queue->count(),
    'message' => 'Sound dequeued successfully',
]);
