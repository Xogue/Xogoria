<?php

    require_once dirname( __DIR__ ) . "/includes/session.php";

    $admin = $services->adminController( );
    $user = $admin->requireAdmin( );
    $csrfToken = $admin->csrfToken( );
    $resourceManager = $admin->resources( );
    $resourceDefinitions = $resourceManager->definitions( );
    $resourceRows = [ ];
    $loadErrors = [ ];
    $pendingCommandMatches = 0;

    try {
        $pendingCommandMatches = $admin->captures( )->pendingVerificationCount( );
    } catch ( Throwable $error ) {
        $services->logger( Logger::CHANNEL_WEB )->exception( $error, [ "section" => "command-match-count" ] );
    }

    foreach ( $resourceDefinitions as $resourceKey => $definition ) {
        try {
            $resourceRows[ $resourceKey ] = $resourceManager->list( $resourceKey );
        } catch ( Throwable $error ) {
            $resourceRows[ $resourceKey ] = [ ];
            $loadErrors[ $resourceKey ] = "This resource could not be loaded.";
            $services->logger( Logger::CHANNEL_WEB )->exception( $error, [ "resource" => $resourceKey ] );
        }
    }

    try {
        $configFiles = $admin->configs( )->files( );
    } catch ( Throwable $error ) {
        $configFiles = [ ];
        $loadErrors[ "config" ] = "Configuration files could not be loaded.";
        $services->logger( Logger::CHANNEL_WEB )->exception( $error, [ "section" => "config" ] );
    }

    try {
        $communityManager = $admin->community( );
        $communitySource = $communityManager->source( );
        $communityRevision = $communityManager->revision( $communitySource );
    } catch ( Throwable $error ) {
        $communitySource = "";
        $communityRevision = "";
        $loadErrors[ "community" ] = "Community page content could not be loaded.";
        $services->logger( Logger::CHANNEL_WEB )->exception( $error, [ "section" => "community" ] );
    }

    $escape = static fn( mixed $value ): string => htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" );
    $groups = [
    "People" => [ "users" ],
    "Content" => [ "commands", "quotes" ],
    ];
?>
<!doctype html>
<html lang="en" class="adminDocument">
    <head>
        <?php require XOG_ROOT . "/includes/partials/head.php"; ?>
        <title>Xogoria Admin</title>
        <meta name="admin-csrf-token" content="<?= $escape( $csrfToken ) ?>">
        <?php $assetManager->useCSS( [ "fonts", "variables", "ui", "community", "manageXogoria", "clipsManager", "compatibility" ] ); ?>
    </head>
    <body class="adminBody">
        <div class="adminApp" id="adminApp">
            <header class="adminHeader">
                <div class="adminHeaderBrand">
                    <a class="adminBrand" href="/about.php">Xogoria</a>
                    <span class="adminSubtitle">Control Center</span>
                </div>
                <div class="adminHeaderContext" aria-live="polite">
                    <strong id="adminContextTitle">Admin</strong>
                    <span id="adminContextSubtitle">Choose a section to manage.</span>
                </div>
                <div class="adminIdentity">
                    <span><?= $escape( $user->getDisplayName( ) ?: $user->getLoginName( ) ) ?></span>
                    <small><?= $escape( $user->getRole( ) ) ?></small>
                    <a class="adminButton adminButtonDanger" href="/admin/adminLogout.php">Log out</a>
                </div>
            </header>

            <aside class="adminSidebar" aria-label="Admin sections">
                <?php foreach ( $groups as $groupLabel => $resources ): ?>
                    <div class="adminNavGroup">
                        <div class="adminNavLabel"><?= $escape( $groupLabel ) ?></div>
                        <?php foreach ( $resources as $resourceKey ): ?>
                            <button class="adminNavItem" type="button" data-admin-target="<?= $escape( $resourceKey ) ?>">
                                <?= $escape( $resourceDefinitions[ $resourceKey ][ "label" ] ) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <div class="adminNavGroup">
                    <div class="adminNavLabel">Content control</div>
                    <button class="adminNavItem" type="button" data-admin-target="community">Community page</button>
                    <button class="adminNavItem" type="button" data-admin-target="clips">Clip review</button>
                    <button class="adminNavItem" type="button" data-admin-target="config">Configuration</button>
                    <a class="adminNavLink commandVerificationNav" href="/admin/commandCaptures.php">
                        <span>Streamer.bot captures</span>
                        <?php if ( $pendingCommandMatches > 0 ): ?>
                            <strong title="Command/action name matches awaiting review"><?= $escape( $pendingCommandMatches ) ?> name matches</strong>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="adminNavGroup">
                    <div class="adminNavLabel">Open site</div>
                    <a class="adminNavLink" href="/interact.php" target="_blank">Interaction panel</a>
                    <a class="adminNavLink" href="/clips.php" target="_blank">Clip collection</a>
                    <a class="adminNavLink" href="/community.php" target="_blank">Community page</a>
                    <a class="adminNavLink" href="/quotes.php" target="_blank">Quotes</a>
                </div>
            </aside>

            <main class="adminMain">
                <div class="adminNotice" id="adminNotice" role="status" aria-live="polite" hidden></div>

                <?php foreach ( $resourceDefinitions as $resourceKey => $definition ): ?>
                    <?php
                        $visibleFields = array_filter(
                            $definition[ "fields" ],
                            static fn( array $field ): bool => empty( $field[ "hidden" ] )
                        );
                    ?>
                    <section class="adminSection" data-admin-section="<?= $escape( $resourceKey ) ?>" hidden>
                        <div class="adminSectionHeader">
                            <div>
                                <h1><?= $escape( $definition[ "label" ] ) ?></h1>
                                <p><?= $escape( $definition[ "description" ] ?? "" ) ?></p>
                            </div>
                            <div class="adminTableTools">
                                <label class="adminTableSearch">
                                    <span class="adminVisuallyHidden">Search <?= $escape( $definition[ "label" ] ) ?></span>
                                    <input type="search" placeholder="Search <?= $escape( strtolower( $definition[ "label" ] ) ) ?>" data-resource-search="<?= $escape( $resourceKey ) ?>">
                                </label>
                                <?php if ( ( $definition[ "allowAdd" ] ?? true ) !== false ): ?>
                                    <button type="button" class="adminButton" data-add-resource="<?= $escape( $resourceKey ) ?>">Add new</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ( isset( $loadErrors[ $resourceKey ] ) ): ?>
                            <div class="adminInlineError"><?= $escape( $loadErrors[ $resourceKey ] ) ?></div>
                        <?php endif; ?>
                        <?php
                            $hasLongField = array_filter(
                                $visibleFields,
                                static fn( array $field ): bool => ( $field[ "type" ] ?? "text" ) === "textarea"
                            ) !== [ ];
                        ?>
                        <div class="adminTableWrap">
                            <table class="adminDataTable<?= $hasLongField ? " adminDataTable--hasLongField" : "" ?>" data-resource-table="<?= $escape( $resourceKey ) ?>">
                                <thead><tr>
                                    <?php foreach ( $visibleFields as $fieldName => $field ):
                                        $fieldType = $field[ "type" ] ?? "text";
                                        $fieldClass = $fieldType === "textarea" ? "long" : preg_replace( '/[^a-z0-9_-]/i', '', $fieldType ); ?>
                                        <th class="adminField adminField--<?= $escape( $fieldClass ) ?>" data-column="<?= $escape( $fieldName ) ?>" aria-sort="none">
                                            <button type="button" class="adminSortButton" data-sort-column="<?= $escape( $fieldName ) ?>">
                                                <span><?= $escape( $field[ "label" ] ) ?></span><span class="adminSortIndicator" aria-hidden="true"></span>
                                            </button>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="adminActionsColumn">Actions</th>
                                </tr></thead>
                                <tbody>
                                    <?php foreach ( $resourceRows[ $resourceKey ] as $row ): ?>
                                        <tr data-resource-row data-original-key="<?= $escape( $row[ $definition[ "primaryKey" ] ] ?? "" ) ?>">
                                            <?php
                                                foreach ( $visibleFields as $fieldName => $field ):
                                                    $value = $row[ $fieldName ] ?? "";
                                                    $type = $field[ "type" ] ?? "text";
                                                    $fieldClass = $type === "textarea" ? "long" : preg_replace( '/[^a-z0-9_-]/i', '', $type ); ?>
                                                    <td class="adminField adminField--<?= $escape( $fieldClass ) ?>" data-field="<?= $escape( $fieldName ) ?>">
                                                        <?php if ( $type === "textarea" ): ?>
                                                            <textarea disabled><?= $escape( $value ) ?></textarea>
                                                        <?php elseif ( $type === "boolean" ): ?>
                                                            <label class="adminBooleanControl<?= !empty( $value ) ? " is-on" : "" ?>" data-boolean-control>
                                                                <input type="checkbox" aria-label="<?= $escape( $field[ "label" ] ) ?>" <?= !empty( $value ) ? "checked" : "" ?> disabled>
                                                                <span class="adminBooleanSwitch" aria-hidden="true"></span>
                                                                <span class="adminBooleanValue" data-boolean-value><?= !empty( $value ) ? "Yes" : "No" ?></span>
                                                            </label>
                                                        <?php elseif ( $type === "select" ): ?>
                                                            <select disabled><?php foreach ( $field[ "options" ] ?? [ ] as $option ): ?>
                                                                <option value="<?= $escape( $option ) ?>" <?= strcasecmp( (string) $value, (string) $option ) === 0 ? "selected" : "" ?>><?= $escape( $option ) ?></option>
                                                                <?php endforeach; ?></select>
                                                        <?php else: ?>
                                                            <input type="<?= $escape( $type ) ?>" value="<?= $escape( $value ) ?>" <?= !empty( $field[ "immutable" ] ) ? "data-immutable" : "" ?> disabled>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            <td class="adminRowActions">
                                                <button type="button" class="adminIconButton" data-edit-row>Edit</button>
                                                <button type="button" class="adminIconButton" data-cancel-edit hidden>Cancel edit</button>
                                                <button type="button" class="adminIconButton adminDangerText" data-delete-row>Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="adminEmptyRow" data-empty-row hidden>
                                        <td colspan="<?= count( $visibleFields ) + 1 ?>">No matching records.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endforeach; ?>

                <section class="adminSection" data-admin-section="clips" hidden>
                    <div class="adminSectionHeader adminSectionHeaderTitleOnly"><div><h1>Clip review</h1><p>Approve clips for stream screens, ignore them, or record deletion requests.</p></div></div>
                    <?php require __DIR__ . "/sections/clips.php"; ?>
                </section>

                <section class="adminSection" data-admin-section="community" hidden>
                    <div class="adminSectionHeader">
                        <div>
                            <h1>Community page</h1>
                            <p>Write formatted rules, resources, join instructions, and server details. Preview before publishing.</p>
                        </div>
                        <a class="adminButton" href="/community.php" target="_blank">Open live page</a>
                    </div>
                    <?php if ( isset( $loadErrors[ "community" ] ) ): ?><div class="adminInlineError"><?= $escape( $loadErrors[ "community" ] ) ?></div><?php endif; ?>
                    <div class="communityEditorShell">
                        <div class="communityEditorToolbar" role="toolbar" aria-label="Formatting shortcuts">
                            <button type="button" data-format="heading1" title="Large heading">H1</button>
                            <button type="button" data-format="heading2" title="Section heading">H2</button>
                            <button type="button" data-format="heading3" title="Small heading">H3</button>
                            <button type="button" data-format="toc" title="Description shown only in the table of contents">TOC Text</button>
                            <span class="toolbarDivider"></span>
                            <button type="button" data-format="bold" title="Bold"><strong>B</strong></button>
                            <button type="button" data-format="italic" title="Italic"><em>I</em></button>
                            <button type="button" data-format="strike" title="Strikethrough"><s>S</s></button>
                            <button type="button" data-format="link" title="Link">Link</button>
                            <button type="button" data-format="button" title="Call-to-action button">Button</button>
                            <span class="toolbarDivider"></span>
                            <button type="button" data-format="bullets" title="Bulleted list">• List</button>
                            <button type="button" data-format="numbers" title="Numbered list">1. List</button>
                            <button type="button" data-format="quote" title="Quote">Quote</button>
                            <button type="button" data-format="code" title="Code block">Code</button>
                            <button type="button" data-format="table" title="Table">Table</button>
                            <button type="button" data-format="divider" title="Divider">Divider</button>
                            <span class="toolbarDivider"></span>
                            <button type="button" data-format="note">Note</button>
                            <button type="button" data-format="tip">Tip</button>
                            <button type="button" data-format="warning">Warning</button>
                            <button type="button" data-format="cards">Cards</button>
                        </div>
                        <textarea id="communityEditor" class="communityMarkdownEditor" data-revision="<?= $escape( $communityRevision ) ?>" spellcheck="true" aria-label="Community page content"><?= $escape( $communitySource ) ?></textarea>
                        <div class="communityEditorFooter">
                            <span id="communityEditorStatus">Markdown with Xogoria layout blocks</span>
                            <div>
                                <button type="button" class="adminButton communityPreviewButton" data-preview-community>Preview</button>
                                <button type="button" class="adminButton" data-save-community>Save &amp; publish</button>
                            </div>
                        </div>
                    </div>
                    <div class="communitySyntaxHelp">
                        <details>
                            <summary>Formatting guide</summary>
                            <p>Use <code>#</code> through <code>####</code> for headings, <code>**bold**</code>, <code>*italic*</code>, links, lists, quotes, code fences, dividers, and Markdown tables.</p>
                            <p>Put <code>{toc: A short description}</code> directly below a heading to show that description in the table of contents without displaying it in the article.</p>
                            <p>The Note, Tip, Warning, and Cards controls insert Xogoria layout blocks. Separate cards with <code>+++</code>. Cards preserve single line breaks and can contain Note, Tip, Warning, or Danger blocks. All output is sanitized before display.</p>
                        </details>
                    </div>
                    <section class="communityPreviewPanel" id="communityPreviewPanel" hidden>
                        <div class="communityPreviewHeader"><h2>Preview</h2><span>Not published until you save</span></div>
                        <div class="uiPanel communityPanel communityPanelStandalone">
                            <article class="uiPanelBody communityContent" id="communityPreview"></article>
                        </div>
                    </section>
                </section>

                <section class="adminSection" data-admin-section="config" hidden>
                    <div class="adminSectionHeader adminSectionHeaderTitleOnly"><div><h1>Configuration</h1><p>Validated JSON editors for active application configuration. Every save creates a timestamped backup.</p></div></div>
                    <?php if ( isset( $loadErrors[ "config" ] ) ): ?><div class="adminInlineError"><?= $escape( $loadErrors[ "config" ] ) ?></div><?php endif; ?>
                    <?php if ( $configFiles !== [ ] ): ?>
                        <div class="adminConfigWorkspace">
                            <div class="adminConfigPicker">
                                <label for="adminConfigSelect">
                                    <span>Configuration file</span>
                                    <select id="adminConfigSelect">
                                        <?php foreach ( $configFiles as $configKey => $config ): ?>
                                            <option value="<?= $escape( $configKey ) ?>"><?= $escape( $config[ "label" ] ) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <p>Choose a file, then edit and save it in the workspace below.</p>
                            </div>
                            <div class="adminConfigGrid">
                                <?php $firstConfig = true; ?>
                                <?php foreach ( $configFiles as $configKey => $config ): ?>
                                    <article class="adminConfigCard" data-config-card="<?= $escape( $configKey ) ?>"<?= $firstConfig ? "" : " hidden" ?>>
                                        <div class="adminConfigHeader"><h2><?= $escape( $config[ "label" ] ) ?></h2><button type="button" class="adminButton" data-save-config>Save</button></div>
                                        <textarea class="adminJsonEditor" spellcheck="false" aria-label="<?= $escape( $config[ "label" ] ) ?> JSON editor"><?= $escape( json_encode( $config[ "data" ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) ?></textarea>
                                    </article>
                                    <?php $firstConfig = false; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>

        <script>window.XOG_ADMIN_RESOURCES = <?= json_encode( $resourceDefinitions, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) ?>;</script>
        <?php $assetManager->useJS( [ "manageXogoria", "clipsManager" ] ); ?>
    </body>
</html>
