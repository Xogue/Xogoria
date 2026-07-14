<?php

require_once dirname(__DIR__) . '/includes/session.php';

$admin = $services->adminController();
$user = $admin->requireAdmin();
$csrfToken = $admin->csrfToken();
$resourceManager = $admin->resources();
$resourceDefinitions = $resourceManager->definitions();
$resourceRows = [];
$loadErrors = [];

foreach ($resourceDefinitions as $resourceKey => $definition) {
    try {
        $resourceRows[$resourceKey] = $resourceManager->list($resourceKey);
    } catch (Throwable $error) {
        $resourceRows[$resourceKey] = [];
        $loadErrors[$resourceKey] = 'This resource could not be loaded.';
        $services->logger(Logger::CHANNEL_WEB)->exception($error, ['resource' => $resourceKey]);
    }
}

try {
    $configFiles = $admin->configs()->files();
} catch (Throwable $error) {
    $configFiles = [];
    $loadErrors['config'] = 'Configuration files could not be loaded.';
    $services->logger(Logger::CHANNEL_WEB)->exception($error, ['section' => 'config']);
}

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$groups = [
    'People' => ['users'],
    'Collections' => ['commands', 'quotes', 'objectives', 'monsters'],
    'Lore' => ['chapters', 'audio', 'streams'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <?php require XOG_ROOT . '/includes/partials/head.php'; ?>
    <title>Xogoria Admin</title>
    <meta name="admin-csrf-token" content="<?= $escape($csrfToken) ?>">
    <link rel="stylesheet" href="/assets/css/common/ui.css">
    <link rel="stylesheet" href="/assets/css/admin/manageXogoria.css">
    <link rel="stylesheet" href="/assets/css/admin/clipsManager.css">
</head>
<body class="adminBody">
<div class="adminApp" id="adminApp">
    <header class="adminHeader">
        <div>
            <a class="adminBrand" href="/about.php">Xogoria</a>
            <span class="adminSubtitle">Control Center</span>
        </div>
        <div class="adminIdentity">
            <span><?= $escape($user->getDisplayName() ?: $user->getLoginName()) ?></span>
            <small><?= $escape($user->getRole()) ?></small>
            <a class="adminButton adminButtonDanger" href="/admin/adminLogout.php">Log out</a>
        </div>
    </header>

    <aside class="adminSidebar" aria-label="Admin sections">
        <?php foreach ($groups as $groupLabel => $resources): ?>
            <div class="adminNavGroup">
                <div class="adminNavLabel"><?= $escape($groupLabel) ?></div>
                <?php foreach ($resources as $resourceKey): ?>
                    <button class="adminNavItem" type="button" data-admin-target="<?= $escape($resourceKey) ?>">
                        <?= $escape($resourceDefinitions[$resourceKey]['label']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <div class="adminNavGroup">
            <div class="adminNavLabel">Content control</div>
            <button class="adminNavItem" type="button" data-admin-target="clips">Clip review</button>
            <button class="adminNavItem" type="button" data-admin-target="config">Configuration</button>
        </div>
        <div class="adminNavGroup">
            <div class="adminNavLabel">Open site</div>
            <a class="adminNavLink" href="/interact.php" target="_blank">Interaction panel</a>
            <a class="adminNavLink" href="/clips.php" target="_blank">Clip collection</a>
            <a class="adminNavLink" href="/collections.php" target="_blank">Collections</a>
        </div>
    </aside>

    <main class="adminMain">
        <div class="adminNotice" id="adminNotice" role="status" aria-live="polite" hidden></div>

        <?php foreach ($resourceDefinitions as $resourceKey => $definition): ?>
            <section class="adminSection" data-admin-section="<?= $escape($resourceKey) ?>" hidden>
                <div class="adminSectionHeader">
                    <div>
                        <h1><?= $escape($definition['label']) ?></h1>
                        <p><?= $escape($definition['description'] ?? '') ?></p>
                    </div>
                    <button type="button" class="adminButton" data-add-resource="<?= $escape($resourceKey) ?>">Add new</button>
                </div>
                <?php if (isset($loadErrors[$resourceKey])): ?>
                    <div class="adminInlineError"><?= $escape($loadErrors[$resourceKey]) ?></div>
                <?php endif; ?>
                <div class="adminTableWrap">
                    <table class="adminDataTable" data-resource-table="<?= $escape($resourceKey) ?>">
                        <thead><tr>
                            <?php foreach ($definition['fields'] as $field): ?><th><?= $escape($field['label']) ?></th><?php endforeach; ?>
                            <th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($resourceRows[$resourceKey] as $row): ?>
                            <tr data-resource-row data-original-key="<?= $escape($row[$definition['primaryKey']] ?? '') ?>">
                                <?php foreach ($definition['fields'] as $fieldName => $field):
                                    $value = $row[$fieldName] ?? '';
                                    $type = $field['type'] ?? 'text'; ?>
                                    <td data-field="<?= $escape($fieldName) ?>">
                                        <?php if ($type === 'textarea'): ?>
                                            <textarea disabled><?= $escape($value) ?></textarea>
                                        <?php elseif ($type === 'boolean'): ?>
                                            <input type="checkbox" <?= !empty($value) ? 'checked' : '' ?> disabled>
                                        <?php elseif ($type === 'select'): ?>
                                            <select disabled><?php foreach (($field['options'] ?? []) as $option): ?>
                                                <option value="<?= $escape($option) ?>" <?= (string) $value === (string) $option ? 'selected' : '' ?>><?= $escape($option) ?></option>
                                            <?php endforeach; ?></select>
                                        <?php else: ?>
                                            <input type="<?= $escape($type) ?>" value="<?= $escape($value) ?>" <?= !empty($field['immutable']) ? 'data-immutable' : '' ?> disabled>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="adminRowActions">
                                    <button type="button" class="adminIconButton" data-edit-row>Edit</button>
                                    <button type="button" class="adminIconButton adminDangerText" data-delete-row>Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>

        <section class="adminSection" data-admin-section="clips" hidden>
            <div class="adminSectionHeader"><div><h1>Clip review</h1><p>Approve clips for stream screens, ignore them, or record deletion requests.</p></div></div>
            <?php require __DIR__ . '/sections/clips.php'; ?>
        </section>

        <section class="adminSection" data-admin-section="config" hidden>
            <div class="adminSectionHeader"><div><h1>Configuration</h1><p>Validated JSON editors for active application configuration. Every save creates a timestamped backup.</p></div></div>
            <?php if (isset($loadErrors['config'])): ?><div class="adminInlineError"><?= $escape($loadErrors['config']) ?></div><?php endif; ?>
            <div class="adminConfigGrid">
                <?php foreach ($configFiles as $configKey => $config): ?>
                    <article class="adminConfigCard" data-config-card="<?= $escape($configKey) ?>">
                        <div class="adminConfigHeader"><h2><?= $escape($config['label']) ?></h2><button type="button" class="adminButton" data-save-config>Save</button></div>
                        <textarea class="adminJsonEditor" spellcheck="false"><?= $escape(json_encode($config['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<script>window.XOG_ADMIN_RESOURCES = <?= json_encode($resourceDefinitions, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="/assets/js/admin/manageXogoria.js"></script>
<script src="/assets/js/admin/clipsManager.js"></script>
</body>
</html>
