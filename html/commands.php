<?php
    require_once __DIR__ . "/includes/session.php";

    $cssAssets = [ "ui", "header", "nav", "live", "commands" ];
    $jsAssets = [ "jquery", "ui", "commands" ];

    // COLLECT DATA
    $mySqlManager = $webController->getMySqlManager( );
    $commands = $mySqlManager->fetchData( "allCommands" );

    // COMPILE LOCAL COLLECTIONS
    $collectionManager = $webController->getCollectionManager( );
    $collectionManager->insertCommands( $commands );

    // ASSEMBLE HTML
    $commandHtml = $collectionManager->assembleCommands( );
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
            <div class="uiPageTitle">Xogoria's Commands</div>
        </div>

        <div id="uiSubBanner">
            <div class="uiSubTitle">What Can You Do?</div>
            <div class="uiHorizontalPlate ui25FromLeft">
                <span class="uiLeftBolt">
                    <span class="uiChain uiCenter uiLongChain" aria-hidden="true"></span>
                </span>
                <span class="uiRightBolt jsCommandsAnchor1A">
                    <span class="uiChain uiCenter jsChainLength1" aria-hidden="true"></span>
                </span>
            </div>
            <div class="uiHorizontalPlate ui25FromRight">
                <span class="uiLeftBolt jsCommandsAnchor1A">
                    <span class="uiChain uiCenter jsChainLength1" aria-hidden="true"></span>
                </span>
                <span class="uiRightBolt">
                    <span class="uiChain uiCenter uiLongChain" aria-hidden="true"></span>
                </span>
            </div>
        </div>
    </div>

    <div id="commandsFilters">
        <div class="filtersHeader">
            <div class="filtersHeaderBar jsCommandsAnchor1B">
                <span class="label left">Category</span>
                <span class="label right">Permissions</span>
            </div>
        </div>
        <div class="filtersGrid">
            <div class="filterCol left">
                <div class="cmdTabs" data-group="category">
                    <span class="cmdLink category active" data-value="all">All</span>
                    <span class="cmdLink category" data-value="utility">Utility</span>
                    <span class="cmdLink category" data-value="playful">Playful</span>
                    <span class="cmdLink category" data-value="supportive">Supportive</span>
                    <span class="cmdLink category" data-value="other">Other</span>
                </div>
            </div>

            <div class="filterCol right">
                <div class="cmdTabs" data-group="permission">
                    <span class="cmdLink permission active" data-value="everyone">Everyone</span>
                    <span class="cmdLink permission" data-value="vips">VIPs</span>
                    <span class="cmdLink permission" data-value="mods">Mods</span>
                </div>
            </div>
        </div>
    </div>

    <div class="uiSectionHang uiSetWidth51">
        <div class="uiSectionPill">
            <span class="title"><span>All</span> Commands for <span>Everyone</span></span>
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
                <span class="uiChain uiCenter uiVeryLongChain" aria-hidden="true"></span>
                <span class="uiChain uiCenter uiVeryLongChain" aria-hidden="true"></span>
            </span>
        </div>
    </div>

    <div class="uiSectionBody">
        <span><?php echo $commandHtml; ?></span>
        <div class="pagination uiHidden">
            <button class="pageButton" data-act="prev">Prev</button>
            <span class="pageInfo">Page 1 of 1</span>
            <button class="pageButton" data-act="next">Next</button>
        </div>
    </div>
</body>
</html>
