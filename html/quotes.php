<?php
    require_once __DIR__ . "/includes/session.php";

    $cssAssets = [ "fonts", "variables", "ui", "header", "nav", "live", "quotes", "compatibility" ];
    $jsAssets = [ "jquery", "ui", "quotes" ];

    // COLLECT DATA
    $mySqlManager = $webController->getMySqlManager( );
    $quotes = $mySqlManager->fetchData( "allQuotes" );

    // COMPILE QUOTES
    $collectionManager = $webController->getCollectionManager( );
    $collectionManager->insertQuotes( $quotes );

    // ASSEMBLE HTML
    $quoteHtml = $collectionManager->assembleQuotes( );
?>

<!DOCTYPE html>
<html>
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
            <div class="uiPageTitle">Quotes</div>
        </div>

        <div id="uiSubBanner">
            <div class="uiTitlePill">
                <span>One derp at a time.</span>
            </div>
        </div>

        <!-- Sections -->
        <div class="uiSectionBody" data-page="quotes">
            <?php echo $quoteHtml; ?>
            <div class="pagination uiHidden">
                <button class="pageButton" data-act="prev">Prev</button>
                <span class="pageInfo">Page 1 of 1</span>
                <button class="pageButton" data-act="next">Next</button>
            </div>
        </div>
    </body>
</html>
