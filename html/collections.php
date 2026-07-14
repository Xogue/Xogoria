<?php
    require_once __DIR__ . '/includes/session.php';

    $cssAssets = [
    'ui',
	'header',
	'nav',
	'live',
	'collections',
    ];

    $jsAssets = [
    'jquery',
	'ui',
    ];

    $mySqlManager = $webController->getMySqlManager();
    $collectionManager = $webController->getCollectionManager();

    $monsters = $mySqlManager->fetchData('allMonsters');
    $objectives = $mySqlManager->fetchData('allObjectives');
    $quotes = $mySqlManager->fetchData('allQuotes');

    $collectionManager->insertMonsters($monsters);
    $collectionManager->insertObjectives($objectives);
    $collectionManager->insertQuotes($quotes);

    $monsterHtml = $collectionManager->assembleMonsters();
    $objectiveHtml = $collectionManager->assembleObjectives();
    $quoteHtml = $collectionManager->assembleQuotes();
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
	<?php require $headerFilepath; ?>
	<?php require $navFilepath; ?>
    <div id="uiPageBanner">
        <div class="uiPageTitle">Xogoria's Collections</div>
    </div>

    <div id="uiSubBanner">
        <div class="uiSubTitle">
            <span>What Are Collections?</span>
            <div class="uiHorizontalPlate ui25FromLeft">
                <span class="uiLeftBolt jsCollectionsAnchor1A">
                    <span class="uiChain uiCenter jsChainLength1" aria-hidden="true"></span>
                </span>
                <span class="uiRightBolt jsCollectionsAnchor2A">
                    <span class="uiChain uiCenter jsChainLength2" aria-hidden="true"></span>
                </span>
            </div>
            <div class="uiHorizontalPlate ui25FromRight">
                <span class="uiLeftBolt jsCollectionsAnchor2A">
                    <span class="uiChain uiCenter jsChainLength2" aria-hidden="true"></span>
                </span>
                <span class="uiRightBolt jsCollectionsAnchor1A">
                    <span class="uiChain uiCenter jsChainLength1" aria-hidden="true"></span>
                </span>
            </div>
        </div>
	</div>

    <div class="uiSubDescription">
        Quotes are the collections everyone has and yea, we've got quotes. But we didn't stop there!<br><br>
        We Liked it, so we collected more!
    </div>
    <div class="uiSectionHang">
        <div class="uiSectionPill jsCollectionsAnchor2B"><span>What We Collect</span></div>
        <div class="uiSectionPillShelf">
            <span class="uiSectionPillHang jsPillLink active" data-page="quotes">Quotes</span>
            <span class="uiSectionPillHang jsPillLink" data-page="objectives">Objectives</span>
            <span class="uiSectionPillHang jsPillLink" data-page="monsterNames">Monster Names</span>
        </div>
    </div>

    <div class="uiSectionHang uiSetWidth48">
        <div class="uiSectionPill jsCollectionsAnchor1B">
            <span class="title">Quotes</span>
        </div>
        <div class="uiVerticalPlate ui0FromLeft">
            <span class="uiTopBolt"></span>
            <span class="uiBottomBolt">
                <span class="uiChain uiCenter uiLongChain" aria-hidden="true"></span>
            </span>
        </div>
        <div class="uiVerticalPlate ui0FromRight">
            <span class="uiTopBolt"></span>
            <span class="uiBottomBolt">
                <span class="uiChain uiCenter uiLongChain" aria-hidden="true"></span>
            </span>
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
