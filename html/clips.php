<?php
    require_once __DIR__ . "/includes/session.php";

    $cssAssets = [ "fonts", "variables", "ui", "header", "nav", "clips", "compatibility" ];
    $jsAssets = [ "jquery", "ui", "brbClips" ];

    $overlayParam = strtolower( trim( (string) ( $_GET[ "overlay" ] ?? "" ) ) );
    $isObsOverlay = in_array( $overlayParam, [ "1", "true", "on", "yes", "brb", "clips", "brb_clips" ], true );
?>

<!DOCTYPE html>
<html>
    <head>
        <?php
            require XOG_ROOT . "/includes/partials/head.php";
            $assetManager->useCSS( $cssAssets );
            $assetManager->useJS( $jsAssets );
        ?>
    </head>
    <body<?= $isObsOverlay ? ' class="overlay-brb_clips"' : "" ?>>
        <?php
            require XOG_ROOT . "/includes/partials/header.php";
            require XOG_ROOT . "/includes/partials/nav.php";
        ?>
        <div id="uiPageBanner">
            <div class="uiPageTitle">Stream Clips</div>
        </div>

        <div id="uiSubBanner">
            <div class="uiTitlePill">
                <span>Highlights From Recent Adventures</span>
            </div>
        </div>

        <div class="uiSubDescription">
            Explore the latest and most-viewed Twitch clips from Xogoria.
            This is the same reel that plays on the BRB screen during stream breaks.
        </div>

        <div class="uiSectionHang clipsModeToggle">
            <div class="uiTitlePill">
                <span>Browse Clips</span>
            </div>
            <div class="uiSectionPillShelf" aria-label="Clip order">
                <button type="button" class="uiSectionPillHang modeBtn active" data-mode="recent" aria-pressed="true">Latest Clips</button>
                <button type="button" class="uiSectionPillHang modeBtn" data-mode="top" aria-pressed="false">Most Viewed</button>
            </div>
        </div>

        <main id="clipsContent" class="uiSectionBody">
            <div id="clipsRoot" class="clipsRoot">
                <div class="overlayPlayer">
                    <div class="overlayTitleBar"><span class="overlayTitle"></span></div>
                    <div class="overlayFallback">Loading clips&hellip;</div>
                </div>
                <div class="clipsGrid">
                    <!-- Cards injected by brb_clips.js -->
                </div>
            </div>
        </main>
    </body>
</html>
