<?php
    require_once dirname( __DIR__ ) . "/includes/session.php";

    header( "Cache-Control: no-store, no-cache, must-revalidate" );
    $admin = $services->adminController( );
    $admin->requireAdmin( );
    $captureManager = $admin->captures( );
    $files = $captureManager->files( );
    $attempts = $captureManager->recentAttempts( );
    $selectedId = basename( (string) ( $_GET[ "file" ] ?? ( $files[ 0 ][ "id" ] ?? "" ) ) );
    $viewMode = ( $_GET[ "view" ] ?? "pretty" ) === "raw" ? "raw" : "pretty";
    $capture = null;
    $displaySource = "";
    $errorMessage = "";

    if ( $selectedId !== "" ) {
        try {
            $capture = $captureManager->read( $selectedId );
            $displaySource = $capture[ "raw" ];
            if ( $viewMode === "pretty" && $capture[ "validJson" ] ) {
                $decoded = json_decode( $capture[ "raw" ], true, 512, JSON_THROW_ON_ERROR );
                $displaySource = json_encode(
                    $decoded,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ) ?: $capture[ "raw" ];
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
?>
<!doctype html>
<html lang="en" class="adminDocument">
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
                            <a class="adminButton<?= $viewMode === "pretty" ? " active" : "" ?>" href="?file=<?= rawurlencode( $selectedId ) ?>&amp;view=pretty">Pretty JSON</a>
                            <a class="adminButton<?= $viewMode === "raw" ? " active" : "" ?>" href="?file=<?= rawurlencode( $selectedId ) ?>&amp;view=raw">Raw body</a>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if ( $capture !== null ): ?>
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
