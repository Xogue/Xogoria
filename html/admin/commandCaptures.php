<?php
    require_once dirname( __DIR__ ) . "/includes/session.php";

    header( "Cache-Control: no-store, no-cache, must-revalidate" );
    $admin = $services->adminController( );
    $admin->requireAdmin( );
    $csrfToken = $admin->csrfToken( );
    $captureManager = $admin->captures( );
    $files = $captureManager->files( );
    $attempts = $captureManager->recentAttempts( );
    $selectedId = basename( (string) ( $_GET[ "file" ] ?? ( $files[ 0 ][ "id" ] ?? "" ) ) );
    $requestedView = (string) ( $_GET[ "view" ] ?? "preview" );
    $viewMode = in_array( $requestedView, [ "preview", "pretty", "raw" ], true ) ? $requestedView : "preview";
    $errorMessage = "";

    if ( ( $_SERVER[ "REQUEST_METHOD" ] ?? "GET" ) === "POST" ) {
        try {
            $admin->verifyCsrf( (string) ( $_POST[ "csrfToken" ] ?? "" ) );
            $decision = (string) ( $_POST[ "decision" ] ?? "" );
            if ( !in_array( $decision, [ "verified", "rejected" ], true ) ) {
                throw new InvalidArgumentException( "Unknown verification decision." );
            }
            $captureManager->recordVerification(
                (string) ( $_POST[ "commandId" ] ?? "" ),
                (string) ( $_POST[ "actionId" ] ?? "" ),
                $decision === "verified",
            );
            header(
                "Location: /admin/commandCaptures.php?file=" . rawurlencode( $selectedId ) . "&view=preview",
                true,
                303,
            );
            exit( );
        } catch ( Throwable $verificationError ) {
            $errorMessage = $verificationError->getMessage( );
        }
    }

    $capture = null;
    $preview = null;
    $displaySource = "";
    $previewMessage = "";
    $comparisonAvailable = true;
    $syncResult = null;
    $syncMessage = "";

    if ( $selectedId !== "" ) {
        try {
            $capture = $captureManager->read( $selectedId );
            $displaySource = $capture[ "raw" ];
            if ( $capture[ "validJson" ] ) {
                $decoded = json_decode( $capture[ "raw" ], true, 512, JSON_THROW_ON_ERROR );
                if ( $selectedId === ( $files[ 0 ][ "id" ] ?? "" ) ) {
                    try {
                        $syncResult = $captureManager->syncLatestCommands( $admin->resources( ) );
                    } catch ( Throwable $syncError ) {
                        $syncMessage = "The capture was loaded, but automatic command synchronization failed.";
                        $services->logger( Logger::CHANNEL_WEB )->exception( $syncError, [
                            "section" => "streamerbot-command-sync",
                            "capture" => $selectedId,
                        ] );
                    }
                }
                if ( $viewMode === "pretty" ) {
                    $displaySource = json_encode(
                        $decoded,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    ) ?: $capture[ "raw" ];
                }
                if ( $viewMode === "preview" ) {
                    try {
                        $websiteCommands = $admin->resources( )->list( "commands" );
                    } catch ( Throwable $databaseError ) {
                        $websiteCommands = [ ];
                        $comparisonAvailable = false;
                        $previewMessage = "The website command table could not be loaded, so this preview only summarizes the capture.";
                        $services->logger( Logger::CHANNEL_WEB )->exception( $databaseError, [
                            "section" => "streamerbot-sync-preview",
                        ] );
                    }
                    $preview = $captureManager->preview( $decoded, $websiteCommands );
                }
            }
        } catch ( Throwable $error ) {
            $errorMessage = $error->getMessage( );
        }
    }

    $escape = static fn( mixed $value ): string => htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8",
    );
    $formatBytes = static function ( int $bytes ): string {
        if ( $bytes >= 1048576 ) return number_format( $bytes / 1048576, 2 ) . " MB";
        if ( $bytes >= 1024 ) return number_format( $bytes / 1024, 1 ) . " KB";
        return $bytes . " bytes";
    };
    $aliases = static fn( array $command ): string => implode(
        ", ",
        array_map( "strval", is_array( $command[ "commands" ] ?? null ) ? $command[ "commands" ] : [ ] ),
    );
    $actionNames = static fn( array $actions ): string => implode(
        ", ",
        array_map( static fn( array $action ): string => (string) ( $action[ "name" ] ?? "Unknown action" ), $actions ),
    );
?>
<!doctype html>
<html lang="en" class="adminDocument commandCaptureDocument">
    <head>
        <?php require XOG_ROOT . "/includes/partials/head.php"; ?>
        <title>Streamer.bot Captures · Xogoria Admin</title>
        <?php $assetManager->useCSS( [ "fonts", "variables", "ui", "manageXogoria", "compatibility" ] ); ?>
    </head>
    <body class="adminBody commandCaptureBody">
        <main class="commandCapturePage">
            <header class="commandCaptureHeader">
                <div>
                    <h1>Streamer.bot captures</h1>
                    <p>Combined command, action, and mapping snapshots captured for inspection before synchronization.</p>
                </div>
                <a class="adminButton" href="/admin/manageXogoria.php">Back to admin</a>
            </header>

            <?php if ( $errorMessage !== "" ): ?>
                <div class="adminInlineError"><?= $escape( $errorMessage ) ?></div>
            <?php endif; ?>

            <details class="commandCaptureDiagnostics"<?= $files === [ ] ? " open" : "" ?>>
                <summary>Recent request diagnostics <span><?= count( $attempts ) ?></span></summary>
                <?php if ( $attempts === [ ] ): ?>
                    <p>No traced Streamer.bot capture requests have reached this server yet.</p>
                <?php else: ?>
                    <div class="commandCaptureAttempts">
                        <?php foreach ( $attempts as $attempt ): ?>
                            <article>
                                <time><?= $escape( $attempt[ "timestamp" ] ?? "Unknown time" ) ?></time>
                                <strong><?= $escape( $attempt[ "message" ] ?? "Capture event" ) ?></strong>
                                <?php if ( !empty( $attempt[ "context" ] ) ): ?>
                                    <code><?= $escape( json_encode( $attempt[ "context" ], JSON_UNESCAPED_SLASHES ) ) ?></code>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </details>

            <?php if ( $files === [ ] ): ?>
                <section class="adminConfigCard commandCaptureEmpty">
                    <h2>No captures yet</h2>
                    <p>Send a Streamer.bot snapshot to the capture URL, then refresh this page.</p>
                </section>
            <?php else: ?>
                <section class="commandCaptureToolbar">
                    <form method="get">
                        <label for="commandCaptureSelect">Capture</label>
                        <select id="commandCaptureSelect" name="file" onchange="this.form.submit()">
                            <?php foreach ( $files as $file ): ?>
                                <option value="<?= $escape( $file[ "id" ] ) ?>"<?= $file[ "id" ] === $selectedId ? " selected" : "" ?>>
                                    <?= $escape( date( "M j, Y g:i:s A", $file[ "capturedAt" ] ) ) ?> · <?= $escape( $formatBytes( $file[ "bytes" ] ) ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php if ( $capture !== null ): ?>
                        <div class="commandCaptureViewButtons">
                            <a class="adminButton<?= $viewMode === "preview" ? " active" : "" ?>" href="?file=<?= rawurlencode( $selectedId ) ?>&amp;view=preview">Sync preview</a>
                            <a class="adminButton<?= $viewMode === "pretty" ? " active" : "" ?>" href="?file=<?= rawurlencode( $selectedId ) ?>&amp;view=pretty">Pretty JSON</a>
                            <a class="adminButton<?= $viewMode === "raw" ? " active" : "" ?>" href="?file=<?= rawurlencode( $selectedId ) ?>&amp;view=raw">Raw body</a>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if ( $capture !== null && $viewMode === "preview" && $preview !== null ): ?>
                    <?php if ( $syncResult !== null ): ?>
                        <div class="commandSyncResult">
                            Database synchronized:
                            <strong><?= $escape( $syncResult[ "added" ] ) ?> added</strong>,
                            <strong><?= $escape( $syncResult[ "updated" ] ) ?> updated</strong>,
                            <?= $escape( $syncResult[ "unchanged" ] ) ?> unchanged.
                        </div>
                    <?php elseif ( $syncMessage !== "" ): ?>
                        <div class="adminInlineError"><?= $escape( $syncMessage ) ?></div>
                    <?php endif; ?>
                    <?php if ( $previewMessage !== "" ): ?>
                        <div class="adminInlineError"><?= $escape( $previewMessage ) ?></div>
                    <?php endif; ?>

                    <section class="commandSyncSummary" aria-label="Capture summary">
                        <article><strong><?= $escape( $preview[ "commandCount" ] ) ?></strong><span>Commands</span></article>
                        <article><strong><?= $escape( $preview[ "actionCount" ] ) ?></strong><span>Actions</span></article>
                        <?php if ( $comparisonAvailable ): ?>
                            <article><strong><?= $escape( count( $preview[ "matched" ] ) ) ?></strong><span>Website matches</span></article>
                            <article><strong><?= $escape( count( $preview[ "streamerbotOnly" ] ) ) ?></strong><span>New candidates</span></article>
                        <?php else: ?>
                            <article><strong><?= $escape( $preview[ "singleActionCount" ] ) ?></strong><span>Single-action candidates</span></article>
                            <article><strong><?= $escape( $preview[ "unmappedActionCount" ] ) ?></strong><span>Unmapped actions</span></article>
                        <?php endif; ?>
                        <article><strong><?= $escape( $preview[ "unresolvedCount" ] ) ?></strong><span>Unresolved actions</span></article>
                    </section>

                    <?php if ( $preview[ "pendingVerifications" ] !== [ ] ): ?>
                        <section class="commandVerificationPanel">
                            <header>
                                <div>
                                    <h2>Command/action matches awaiting review</h2>
                                    <p>These are name-based guesses. Your decision is retained for future captures.</p>
                                </div>
                                <span><?= count( $preview[ "pendingVerifications" ] ) ?> pending</span>
                            </header>
                            <div class="commandVerificationList">
                                <?php foreach ( $preview[ "pendingVerifications" ] as $item ): ?>
                                    <article>
                                        <div class="commandVerificationNames">
                                            <div><small>Command</small><strong><?= $escape( $item[ "command" ][ "name" ] ?? "" ) ?></strong></div>
                                            <span aria-hidden="true">&rarr;</span>
                                            <div><small>Action</small><strong><?= $escape( $item[ "action" ][ "name" ] ?? "" ) ?></strong></div>
                                        </div>
                                        <div class="commandVerificationReason">
                                            <?= $escape( str_replace( "_", " ", $item[ "method" ] ) ) ?>
                                            &middot; <?= $escape( number_format( $item[ "score" ] * 100, 0 ) ) ?>% similarity
                                        </div>
                                        <form method="post" action="?file=<?= rawurlencode( $selectedId ) ?>&amp;view=preview">
                                            <input type="hidden" name="csrfToken" value="<?= $escape( $csrfToken ) ?>">
                                            <input type="hidden" name="commandId" value="<?= $escape( $item[ "command" ][ "id" ] ?? "" ) ?>">
                                            <input type="hidden" name="actionId" value="<?= $escape( $item[ "action" ][ "id" ] ?? "" ) ?>">
                                            <button class="adminButton" type="submit" name="decision" value="verified">Verify match</button>
                                            <button class="adminButton adminButtonDanger" type="submit" name="decision" value="rejected">Wrong match</button>
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ( $comparisonAvailable ): ?>
                    <section class="commandSyncNotice">
                        <strong>Automatic command sync</strong>
                        <p>New commands and enabled states are synchronized independently of pending name verification. Existing descriptions, categories, and permissions remain website-managed.</p>
                    </section>

                    <details class="commandSyncGroup" open>
                        <summary>Matched website commands <span><?= count( $preview[ "matched" ] ) ?></span></summary>
                        <?php if ( $preview[ "matched" ] === [ ] ): ?>
                            <p class="commandSyncEmpty">No website rows matched a Streamer.bot alias.</p>
                        <?php else: ?>
                            <div class="commandSyncTableWrap"><table class="commandSyncTable">
                                <thead><tr><th>Website</th><th>Streamer.bot</th><th>Aliases</th><th>Enabled</th><th>Action candidate</th></tr></thead>
                                <tbody><?php foreach ( $preview[ "matched" ] as $item ): ?>
                                    <tr>
                                        <td><?= $escape( $item[ "website" ][ "name" ] ?? "" ) ?></td>
                                        <td><?= $escape( $item[ "streamerbot" ][ "name" ] ?? "" ) ?></td>
                                        <td><?= $escape( $aliases( $item[ "streamerbot" ] ) ) ?></td>
                                        <td><span class="commandSyncState <?= !empty( $item[ "streamerbot" ][ "enabled" ] ) ? "yes" : "no" ?>"><?= !empty( $item[ "streamerbot" ][ "enabled" ] ) ? "Yes" : "No" ?></span><?= $item[ "enabledChanged" ] ? " (change)" : "" ?></td>
                                        <td><?= $escape( $actionNames( $item[ "actions" ] ) ?: ucfirst( $item[ "mappingStatus" ] ) ) ?></td>
                                    </tr>
                                <?php endforeach; ?></tbody>
                            </table></div>
                        <?php endif; ?>
                    </details>

                    <details class="commandSyncGroup" open>
                        <summary>New Streamer.bot candidates <span><?= count( $preview[ "streamerbotOnly" ] ) ?></span></summary>
                        <p class="commandSyncHelp">These do not match an existing website command alias. They need review before any are published.</p>
                        <div class="commandSyncTableWrap"><table class="commandSyncTable">
                            <thead><tr><th>Streamer.bot name</th><th>Aliases</th><th>Group</th><th>Enabled</th><th>Action candidate</th></tr></thead>
                            <tbody><?php foreach ( $preview[ "streamerbotOnly" ] as $item ): ?>
                                <tr>
                                    <td><?= $escape( $item[ "streamerbot" ][ "name" ] ?? "" ) ?></td>
                                    <td><?= $escape( $aliases( $item[ "streamerbot" ] ) ) ?></td>
                                    <td><?= $escape( $item[ "streamerbot" ][ "group" ] ?? "" ) ?></td>
                                    <td><span class="commandSyncState <?= !empty( $item[ "streamerbot" ][ "enabled" ] ) ? "yes" : "no" ?>"><?= !empty( $item[ "streamerbot" ][ "enabled" ] ) ? "Yes" : "No" ?></span></td>
                                    <td><?= $escape( $actionNames( $item[ "actions" ] ) ?: ucfirst( $item[ "mappingStatus" ] ) ) ?></td>
                                </tr>
                            <?php endforeach; ?></tbody>
                        </table></div>
                    </details>

                    <details class="commandSyncGroup">
                        <summary>Website-only commands <span><?= count( $preview[ "websiteOnly" ] ) ?></span></summary>
                        <p class="commandSyncHelp">These website rows do not match any alias in this capture. They will not be deleted or disabled automatically.</p>
                        <?php if ( $preview[ "websiteOnly" ] !== [ ] ): ?>
                            <div class="commandSyncNames"><?php foreach ( $preview[ "websiteOnly" ] as $row ): ?><code><?= $escape( $row[ "name" ] ?? "" ) ?></code><?php endforeach; ?></div>
                        <?php endif; ?>
                    </details>

                    <?php if ( $preview[ "ambiguous" ] !== [ ] ): ?>
                        <details class="commandSyncGroup warning" open>
                            <summary>Ambiguous aliases <span><?= count( $preview[ "ambiguous" ] ) ?></span></summary>
                            <p class="commandSyncHelp">More than one Streamer.bot command uses the same alias. These require manual resolution.</p>
                        </details>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php elseif ( $capture !== null ): ?>
                    <section class="commandCaptureViewer">
                        <div class="commandCaptureMeta">
                            <strong><?= $escape( $capture[ "id" ] ) ?></strong>
                            <span><?= $escape( $formatBytes( $capture[ "bytes" ] ) ) ?></span>
                            <span class="commandCaptureValidity <?= $capture[ "validJson" ] ? "valid" : "invalid" ?>">
                                <?= $capture[ "validJson" ] ? "Valid JSON" : "Invalid JSON" ?>
                            </span>
                        </div>
                        <pre class="commandCaptureSource"><code><?= $escape( $displaySource ) ?></code></pre>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </body>
</html>
