<?php
    require_once __DIR__ . "/includes/session.php";

    $cssAssets = [ "fonts", "variables", "ui", "interactPanel", "compatibility" ];
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
    $powerSpawn = $activeGame->getSpecialType( "powerSpawn" );
    $batClaim = $activeGame->getSpecialType( "batClaim" );
    $restrictedWords = $webController->getRestrictedWords( );

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
                                            <option value="<?php echo $optionValue; ?>" data-game="<?php echo $gameName; ?>"
                                                data-profile="<?php echo $profileName; ?>"><?php echo $optionLabel; ?>
                                            </option>
                                        <?php } ?>
                                    <?php endforeach; ?>
                            </select>
                            <button class="setGame" type="button">Set & Refresh</button>
                            <div class="gameSelectLabel adminToolLabel">Interact Page Tools</div>
                            <button class="debugToggle" type="button" aria-pressed="false" aria-controls="interactDebugPanel">Enable Debug Mode</button>
                        </div>
                    </div>
                    <section id="interactDebugPanel" class="debugPanel uiHidden" aria-label="Interact page debug log">
                        <div class="debugHeader">
                            <span>Session Debug Log</span>
                            <div class="debugHeaderControls">
                                <button class="sBtn debugCopy" type="button" title="Copy debug log">Copy</button>
                                <button class="sBtn debugClear" type="button" title="Clear debug log">Clear</button>
                            </div>
                        </div>
                        <div class="debugLog" role="log" aria-live="polite" aria-relevant="additions" tabindex="0"></div>
                    </section>
                <?php endif; ?>


                <div class="typeShelf">
                    <?php
                        $first = true;
                        foreach ( $simpleTypes as $typeName => $type ) {
                            $categoryTitle = ucfirst( $typeName );
                            echo '<button class="typeButton' . ( $first ? " active" : "" ) . '" data-tab="' . $typeName . '">' . $categoryTitle . "</button>";
                            $first = false;
                        }
                        if ( $powerSpawn instanceof PowerSpawn && !empty( $specialTypes[ "powerSpawn" ] ) ) {
                            echo '<button class="typeButton' . ( $first ? " active" : "" ) . '" data-tab="powerSpawn">Custom Spawn</button>';
                            $first = false;
                        }
                        if ( $batClaim instanceof BatClaim && $batClaim->isEnabled( ) && in_array( "batClaim", $specialTypes[ "special" ] ?? [ ], true ) ) {
                            echo '<button class="typeButton' . ( $first ? " active" : "" ) . '" data-tab="special">Bat Claim</button>';
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

                <?php if ( $powerSpawn instanceof PowerSpawn && !empty( $specialTypes[ "powerSpawn" ] ) ): ?>
                <section class="interactions uiHidden" data-tab="powerSpawn">
                <div class="spawnWrap" data-cooldown-min="<?php echo $powerSpawn->getCooldownMin( ); ?>" data-cooldown-max="<?php echo $powerSpawn->getCooldownMax( ); ?>">
                    <div class="cardLabel">Build a custom mob spawn</div>
                    <div class="spawnRows">
                        <?php
                            $mobs = $powerSpawn->getMobs( );
                            $replaceKeys = [ "{KEY}", "{LABEL}", "{COST}", "{COOLDOWN}" ];
                            foreach ( $mobs as $mobKey => $mob ) {
                                if ( $mob->isEnabled( ) && in_array( $mobKey, $specialTypes[ "powerSpawn" ], true ) ) {
                                    $replaceValues = [ $mobKey, $mob->getLabel( ), $mob->getCost( ), $mob->getCooldown( ) ];
                                    echo str_replace( $replaceKeys, $replaceValues, $webController->getTemplatePart( "panelSpawnController" ) );
                                }
                            }
                        ?>
                    </div>
                    <div class="spawnSummary">
                        <div class="sumLine">Total Mobs: <span id="sum_count">0</span></div>
                        <div class="sumLine">Total Cost: <span id="sum_cost">0</span> AGs</div>
                        <div class="sumLine">Cooldown: <span id="sum_cd">0s</span></div>
                        <button id="spawnBtn" class="cardButton spawnButton" type="button" disabled>Spawn Them</button>
                    </div>
                </div>
                </section>
                <?php endif; ?>

                <?php if ( $batClaim instanceof BatClaim && $batClaim->isEnabled( ) && in_array( "batClaim", $specialTypes[ "special" ] ?? [ ], true ) ): ?>
                <section class="interactions uiHidden" data-tab="special">
                    <div class="cardList">
                        <?php
                            $replaceKeys = [ "{COOLDOWN}", "{BLOCKED}", "{LABEL}", "{DESCRIPTION}" ];
                            $replaceValues = [
                                $batClaim->getCooldown( ),
                                htmlspecialchars( json_encode( $restrictedWords, JSON_HEX_APOS | JSON_HEX_QUOT ) ?: "[]", ENT_QUOTES, "UTF-8" ),
                                htmlspecialchars( $batClaim->getLabel( ), ENT_QUOTES, "UTF-8" ),
                                htmlspecialchars( $batClaim->getDescription( ), ENT_QUOTES, "UTF-8" ),
                            ];
                            echo str_replace( $replaceKeys, $replaceValues, $webController->getTemplatePart( "panelSpecial" ) );
                        ?>
                    </div>
                </section>
                <?php endif; ?>
            <?php else: ?>
                <a class="navAuthLink" href="<?php echo $twitchAuthStart . urlencode( $requestUri ); ?>">Login with Twitch</a>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
