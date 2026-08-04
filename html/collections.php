<?php
    require_once __DIR__ . "/includes/session.php";

    $cssAssets = [ "ui", "header", "nav", "live", "collections" ];
    $jsAssets = [ "jquery", "ui" ];

    // COLLECT DATA
    $mySqlManager = $webController->getMySqlManager( );
    $monsters = $mySqlManager->fetchData( "allMonsters" );
    $objectives = $mySqlManager->fetchData( "allObjectives" );
    $quotes = $mySqlManager->fetchData( "allQuotes" );

    // COMPILE LOCAL COLLECTIONS
    $collectionManager = $webController->getCollectionManager( );
    $collectionManager->insertMonsters( $monsters );
    $collectionManager->insertObjectives( $objectives );
    $collectionManager->insertQuotes( $quotes );

    // ASSEMBLE HTML
    $monsterHtml = $collectionManager->assembleMonsters( );
    $objectiveHtml = $collectionManager->assembleObjectives( );
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
            <div class="uiPageTitle">Xogoria's Collections</div>
        </div>

        <div id="uiSubBanner">
            <div class="uiTitlePill">
                <span>What Are Collections?</span>
            </div>
        </div>

        <div class="uiSubDescription">
            Quotes are the collections everyone has and yea, we've got quotes. But we didn't stop there!<br><br>
            We Liked it, so we collected more!
        </div>
        <div class="uiSectionHang">
            <div class="uiTitlePill"><span>What We Collect</span></div>
            <div class="uiSectionPillShelf">
                <span class="uiSectionPillHang jsPillLink active" data-page="quotes">Quotes</span>
                <span class="uiSectionPillHang jsPillLink" data-page="objectives">Objectives</span>
                <span class="uiSectionPillHang jsPillLink" data-page="monsterNames">Monster Names</span>
            </div>
        </div>

        <div class="uiSectionHang">
            <div class="uiTitlePill">
                <span class="title">Quotes</span>
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
        <div class="uiSectionBody uiHidden" data-page="objectives">
            <?php echo $objectiveHtml; ?>
            <div class="pagination uiHidden">
                <button class="pageButton" data-act="prev">Prev</button>
                <span class="pageInfo">Page 1 of 1</span>
                <button class="pageButton" data-act="next">Next</button>
            </div>
        </div>
        <div class="uiSectionBody uiHidden" data-page="monsterNames">
            <?php echo $monsterHtml; ?>
            <div class="pagination uiHidden">
                <button class="pageButton" data-act="prev">Prev</button>
                <span class="pageInfo">Page 1 of 1</span>
                <button class="pageButton" data-act="next">Next</button>
            </div>
        </div>
    </body>
</html>
