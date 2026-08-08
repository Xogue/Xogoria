<?php
    require_once __DIR__ . "/includes/session.php";

    $cssAssets = [ "ui", "compatibility", "fonts", "variables", "header", "nav", "clips" ];
    $jsAssets = [ "jquery", "brbClips" ];
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
    <body>
        <?php
            require XOG_ROOT . "/includes/partials/header.php";
            require XOG_ROOT . "/includes/partials/nav.php";
        ?>
        <div id="clipsHero">
            <div class="banner">
                <span class="titleText">Stream Clips</span>
                <span class="subtitleText">Highlights from recent adventures</span>
            </div>
            <div class="plate left">
                <span class="bolt one"></span>
                <span class="bolt two"></span>
            </div>
            <div class="plate right">
                <span class="bolt one"></span>
                <span class="bolt two"></span>
            </div>
        </div>

        <div id="clipsContent">
            <div class="clipsIntro">
                <p>
                    Explore the latest and most-viewed Twitch clips from Xogoria.
                    This is the same reel that plays on the BRB screen during stream breaks.
                </p>
            </div>
            <div class="clipsModeToggle">
                <button class="modeBtn active" data-mode="recent">Latest Clips</button>
                <button class="modeBtn" data-mode="top">Most Viewed</button>
            </div>

            <div id="clipsRoot" class="clipsRoot">
                <div class="overlayPlayer">
                    <div class="overlayTitleBar"><span class="overlayTitle"></span></div>
                    <div class="overlayFallback">Loading clips&hellip;</div>
                </div>
                <div class="clipsGrid">
                    <!-- Cards injected by brb_clips.js -->
                </div>
            </div>
        </div>
    </body>
</html>
