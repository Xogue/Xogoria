<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$services = new ServiceFactory();
$admin = $services->adminController();
$user = $admin->requireAdmin(true);
$manager = $services->clipReviewManager();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        ApiController::sendJson(['success' => true] + $manager->list());
    }

    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    $input = str_contains($contentType, 'application/json')
        ? json_decode((string) file_get_contents('php://input'), true)
        : $_POST;
    if (!is_array($input)) { ApiController::error('Invalid request body.'); }
    $admin->verifyCsrf((string) ($input['csrfToken'] ?? ''));

    $action = (string) ($input['action'] ?? '');
    $clipId = trim((string) ($input['clipId'] ?? ''));
    if ($clipId === '') { ApiController::error('A clip ID is required.', 422); }

    $result = match ($action) {
        'approve' => ['success' => $manager->approve($clipId, (array) ($input['clip'] ?? []))],
        'ignore' => ['success' => $manager->ignore($clipId)],
        'save' => ['success' => $manager->save($clipId, (array) ($input['data'] ?? []))],
        'requestDeletion' => ['success' => true, 'deletion' => $manager->requestDeletion($clipId, $user->getDisplayName() ?: $user->getLoginName())],
        default => throw new InvalidArgumentException('Unknown clip action'),
    };
    if (!$result['success']) { ApiController::error('The clip operation failed.', 500); }
    ApiController::sendJson($result);
} catch (InvalidArgumentException $error) {
    ApiController::error($error->getMessage(), 422);
} catch (Throwable $error) {
    $services->logger(Logger::CHANNEL_API)->exception($error, ['endpoint' => 'clipsAdmin']);
    ApiController::error('The clip operation could not be completed.', 500);
}
