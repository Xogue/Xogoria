<?php
require_once dirname(__DIR__) . '/includes/session.php';

?>
<!DOCTYPE html>
<html>
<head>
    <?php require 'common/includes.php'; ?>
    <title>Xogoria Stream Clips - BRB Overlay</title>
</head>
<body class="overlay-brb_clips">
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

    <div id="clipsContent" data-overlay="1">
        <div id="clipsRoot" class="clipsRoot">
            <div class="overlayPlayer">
                <div class="overlayTitleBar"><span class="overlayTitle"></span></div>
                <div class="overlayFallback">Loading clips&hellip;</div>
            </div>
            <div class="clipsGrid">
                <!-- Cards injected by brb_clips.js -->
            </div>
        </div>
        <div class="overlayControls">
            <button type="button" class="overlayStopBtn">Stop Clips</button>
        </div>
    </div>
</body>
</html>
