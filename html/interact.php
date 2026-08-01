<?php
    require_once __DIR__ . "/includes/session.php";

    $cssAssets = [ "ui", "interactPanel" ];
    $jsAssets = [ "jquery", "ui", "InteractCard", "interactPanel" ];

    // GET UTILITY DATA
    $userContext = $webController->getUserContext( );
    $twitchContext = $webController->getTwitchAppContext( );
    $gameManager = $webController->getGameManager( );
    $twitchAuthStart = $twitchContext->getAuthStart( );

    // GET ACTIVE GAME TYPES
    $activeGame = $webController->getActiveGame( );
    $interactTypes = $activeGame->getSimpleTypes( );

    // GET PROFILE DATA
    $activeProfile = $webController->getActiveProfile( );
    $panelTitle = $activeProfile->getLabel( );
    $panelTagLine = $activeProfile->getTagLine( );
    $simpleTypes = $activeProfile->getAllowedSimpleTypes( );
    $specialTypes = $activeProfile->getAllowedSpecialTypes( );

    $requestUri = $_SERVER[ "REQUEST_URI" ];
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
        <div id="interactPanel">
            <div class="title"><?php echo $panelTitle; ?></div>
            <div class="tagLine"><?php echo $panelTagLine; ?></div>
            <?php if ( $userContext->userLoggedIn( ) ): ?>
                <div class="userInfo">
                    <div class="loginBanner">
                        <strong><?php echo $userContext->getDisplayName( ) ?: $userContext->getLoginName( ); ?></strong>
                        <div>USER</div>
                    </div>
                    <div class="gemInfo">
                        <strong><?php echo $userContext->getGemBalance( ); ?></strong>
                        <div>AIRO GEMS</div>
                    </div>
                </div>
                <?php if ( $userContext->isAdmin( ) ): ?>
                    <div class="adminPanel">
                        <span class="adminPanelTitle">Open Admin Controls</span>
                        <div class="gameSelect">
                            <div class="gameSelectLabel">Select Game & Profile</div>
                            <select name="gameSelector">
                                <?php
                                    foreach ( $gameManager->getAllGames( ) as $gameName => $game ):
                                        foreach ( $game->getProfiles( ) as $profileName => $profile ) {

                                        $optionValue = $gameName . ";" . $profileName;
                                        $optionLabel = ucfirst( $gameName ) . " - " . ucfirst( $profile->getLabel( ) ); ?>
                                        <option value="<?php echo $optionValue; ?>" data-game="<?php echo $gameName; ?>" data-profile="<?php echo $profileName; ?>"><?php echo $optionLabel; ?></option>
                                        <?php
                                            }
                                            endforeach; ?>
                                    </select>
                                    <button class="setGame" type="button">Set & Refresh</button>
                                </div>
                            </div>
                                    <?php endif; ?>
                <?php else: ?>
                    <a class="navAuthLink" href="<?php echo $twitchAuthStart . urlencode( $requestUri ); ?>">Login with Twitch</a>
                <?php endif; ?>
            </div>

            <div class="typeShelf">
                <?php
                    $first = true;
                    foreach ( $simpleTypes as $typeName => $type ) {
                        $categoryTitle = ucfirst( $typeName );
                        echo '<button class="typeButton' . ( $first ? " active" : "" ) . '" data-tab="' . $typeName . '">' . $categoryTitle . "</button>";
                        $first = false;
                    }
                ?>
            </div>

            <?php foreach ( $interactTypes as $typeName => $type ): ?>
                <section class="interactions uiHidden" data-tab="<?php echo $typeName; ?>">
                    <div class="cardList">
                        <?php foreach ( $type->getInteractions( ) as $key => $interaction ) {
                            if ( $interaction->isEnabled( ) ) {
                                echo $interaction->getPanelHtml( );
                            }
                        } ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <div class="spawnWrap uiHidden">
                <div class="spawnRows">
                    <?php if ( isset( $profileInteractions[ "spawn" ] ) ) {
                        $mobs = $profileInteractions[ "spawn" ][ "mobs" ]->getAllChildren( );
                        $replaceKeys = [ "{KEY}", "{LABEL}", "{COST}" ];
                        foreach ( $mobs as $mobKey => $mob ) {
                            if ( $mob->isEnabled( ) ) {
                                $label = isset( $mob[ "label" ] ) ? $mob[ "label" ] : ucfirst( $mobKey );
                                $replaceValues = [ $mobKey, $label, $mob[ "cost" ] ];
                                echo str_replace( $replaceKeys, $replaceValues, $webController->getTemplatePart( "panelSpawnController" ) );
                            }
                        }
                    } ?>
                </div>
                <div class="spawnSummary">
                    <div class="sumLine">Total Mobs: <span id="sum_count">0</span></div>
                    <div class="sumLine">Total Cost: <span id="sum_cost">0</span> AGs</div>
                    <div class="sumLine">Cooldown: <span id="sum_cd">0s</span></div>
                    <button id="spawnBtn" class="cBtn primary" type="button">Spawn Them</button>
                </div>
            </div>


            <section class="intPanel" data-tab="special" hidden>
                <div class="cardList mb-10">
                    <?php if ( isset( $profileInteractions[ "special" ] ) ) {
                        $batClaim = $profileInteractions[ "special" ][ "batClaim" ];
                        $replaceKeys = [ "{COOLDOWN}", "{BLOCKED}", "{LABEL}", "{DESCRIPTION}" ];
                        $replaceValues = [ $batClaim->getCooldown( ), implode( ", ", getBlockedWords( ) ), $batClaim->getLabel( ), $batClaim->getDescription( ) ];
                        echo str_replace( $replaceKeys, $replaceValues, $webController->getTemplatePart( "panelSpecial" ) );
                    } ?>
                </div>
            </section>
        </div>
    </body>

</html>
