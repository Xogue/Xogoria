<?php
    require_once __DIR__ . "/includes/session.php";

    $cssAssets = [ "ui", "compatibility", "fonts", "variables", "header", "nav", "live", "community" ];
    $jsAssets = [ "jquery", "ui", "community" ];
    $communityHtml = $services->communityContentManager( )->render( );
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php
            require $headFilepath;
            $assetManager->useCSS( $cssAssets );
            $assetManager->useJS( $jsAssets );
        ?>
    </head>
    <body>
        <?php
            require $headerFilepath;
            require $navFilepath;
        ?>
        <div id="uiPageBanner">
            <div class="uiPageTitle">Community Information</div>
        </div>
        <div class="communityDocument">
            <nav class="communityToc" aria-labelledby="communityTocTitle">
                <div class="communityTocHeader">
                    <span id="communityTocTitle">On this page</span>
                    <div class="communitySearchDock" id="communitySearchDock">
                        <div class="communitySearchBar" id="communitySearchPanel" inert>
                            <div class="communitySearchControls">
                                <input id="communitySearchInput" type="search" aria-label="Search this page" placeholder="Search this page&hellip;" autocomplete="off">
                                <button type="button" class="uiButton uiButtonSmall" id="communitySearchPrevious" aria-label="Previous result" disabled>&uarr;</button>
                                <button type="button" class="uiButton uiButtonSmall" id="communitySearchNext" aria-label="Next result" disabled>&darr;</button>
                                <button type="button" class="uiButton uiButtonSmall" id="communitySearchClear" disabled>Clear</button>
                            </div>
                            <span class="communitySearchStatus" id="communitySearchStatus" role="status" aria-live="polite">0 matches</span>
                        </div>
                        <button type="button" class="uiButton uiButtonSmall communitySearchToggle" id="communitySearchToggle" aria-expanded="false" aria-controls="communitySearchPanel">Search</button>
                    </div>
                </div>
                <ol id="communityTocList"></ol>
            </nav>
            <section class="uiPanel communityPanel">
                <article class="uiPanelBody communityContent">
                    <?= $communityHtml ?>
                </article>
            </section>
        </div>
    </body>
</html>
