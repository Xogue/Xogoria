<?php
    require_once __DIR__ . '/includes/session.php';

    $cssAssets = [
    'ui',
    'header',
    'nav',
    'live',
    'features',
    ];

    $jsAssets = [
    'jquery',
    'ui',
    'features',
    ];

    $gameManager = $webController->getGameManager();
    $games       = $gameManager->getAllGames();
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
        <div class="uiPageTitle">Xogoria's Features</div>
    </div>

    <div id="uiSubBanner">
        <div class="uiSubTitle">How do you have features?</div>
        <div class="uiHorizontalPlate ui25FromLeft">
            <span class="uiLeftBolt jsFeaturesAnchor1A">
                <span class="uiChain uiCenter jsChainLength1" aria-hidden="true"></span>
            </span>
            <span class="uiRightBolt jsFeaturesAnchor2A">
                <span class="uiChain uiCenter jsChainLength2" aria-hidden="true"></span>
            </span>
        </div>
        <div class="uiHorizontalPlate ui25FromRight">
            <span class="uiLeftBolt jsFeaturesAnchor2A">
                <span class="uiChain uiCenter jsChainLength2" aria-hidden="true"></span>
            </span>
            <span class="uiRightBolt jsFeaturesAnchor1A">
                <span class="uiChain uiCenter jsChainLength1" aria-hidden="true"></span>
            </span>
        </div>
    </div>

    <div class="uiSubDescription">
        <p>Every channel has its quirks — this is mine! I tinker with code for fun, which means I've built
            weird little systems for my channel that viewers can poke, prod, and inevitably break.
            It's all part of the chaos. For science! </p>

        <p>Interacting with my stream requires a new currency that I call Airo Gems. You can do a lot of
            things including spawn mobs, give potion effects, play sounds and run custom scripts I label as pranks.</p>
    </div>

    <div class="uiSectionHang">
        <div class="uiSectionPill jsFeaturesAnchor2B"><span>Stream Interaction</span></div>
        <div class="uiSectionPillShelf">
            <span class="uiSectionPillHang jsPillLink active" data-page="gems">Gain Airo Gems</span>
            <span class="uiSectionPillHang jsPillLink" data-page="interacting">Interacting</span>
        </div>
    </div>

    <div class="uiSectionHang uiSetWidth51">
        <div class="uiSectionPill jsFeaturesAnchor1B">
            <span class="title">Gaining Airo Gems</span>
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
        <div class="uiSectionPillShelf jsGameShelf uiHidden">
            <?php foreach ( $games as $gameName => $game ): ?>
            <span class="uiSectionPillHang jsGameLink"
                data-page="<?php echo $gameName; ?>"><?php echo ucfirst( $game->getName() ); ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="uiSectionBody" data-page="gems">
        <table class="uiTable">
            <thead>
                <tr>
                    <th>Action Taken</th>
                    <th>Amount Awarded</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span>Following</span></td>
                    <td><span>10 Airo Gems</span></td>
                </tr>
                <tr>
                    <td><span>Chatting</span></td>
                    <td><span>1 Airo Gem per 1 valid* line</span></td>
                </tr>
                <tr>
                    <td><span>Donating</span></td>
                    <td><span>1 Airo Gem per 10 cents</span></td>
                </tr>
                <tr>
                    <td><span>Subscribing</span></td>
                    <td><span>25 Airo Gems per tier</span></td>
                </tr>
            </tbody>
        </table>
        <p class="note">*Valid lines: lines that contribute to meaningful chat interaction
            and are not spam or repeated messages. Xogue uses a custom filter to determine
            validity. Just participate normally and they will count.</p>
    </div>

    <?php foreach ( $games as $gameName => $game ): ?>
    <div class="uiSectionBody uiHidden" data-page="<?php echo $gameName; ?>">
        <div class="uiSectionFloatShelf">
            <?php foreach ( $game->getSimpleTypes() as $typeName => $type ): ?>
            <span class="uiSectionPillFloat jsFloatLink <?php echo $typeName === 'effect' ? 'active' : ''; ?>"
                data-page="<?php echo $typeName; ?>"><?php echo ucfirst( $typeName ); ?></span>
            <?php endforeach; ?>
        </div>
        <?php foreach ( $game->getSimpleTypes() as $typeName => $type ): ?>
        <div class="uiSubSectionBody uiHidden" data-page="<?php echo $typeName; ?>">
            <?php echo $type->getHtml(); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <section id="section-chaos" class="featuresSection" data-section="chaos" hidden>
        <p class="sectionBlurb">Chaos Crucible is my playground of scripted mayhem. It bends the rules in silly ways —
            expect oddities, surprises, and plenty of science.</p>
        <div class="panel">
            <div class="panelBody">More details coming soon...</div>
        </div>
    </section>
</body>
</html>
