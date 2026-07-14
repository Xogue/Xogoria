<?php
    require_once __DIR__ . '/includes/session.php';

    $cssAssets = [
    'header',
    'nav',
    'live',
    'ui',
    'lore',
    ];

    $jsAssets = [
    'jquery',
    'ui',
    'lore'
    ];

    $mySqlManager = $webController->getMySqlManager();
    $loreManager = $webController->getLoreManager();

    $loreChapters = $mySqlManager->fetchData('allChapters');
    $loreAudio = $mySqlManager->fetchData('allAudio');
    $loreStreams = $mySqlManager->fetchData('allStreams');
    
    $loreManager->buildStory($loreChapters, $loreAudio, $loreStreams);
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
		<div class="uiPageTitle">Stories of Xogoria</div>
	</div>

    <div id="uiSubBanner">
        <div class="uiSubTitle">Why Stories?</div>
            <div class="uiHorizontalPlate ui25FromLeft">
                <span class="uiLeftBolt jsLoreAnchor1A">
                    <span class="uiChain uiCenter jsChainLength1" aria-hidden="true"></span>
                </span>
                <span class="uiRightBolt jsLoreAnchor2A">
                    <span class="uiChain uiCenter jsChainLength2" aria-hidden="true"></span>
                </span>
            </div>
            <div class="uiHorizontalPlate ui25FromRight">
                <span class="uiLeftBolt jsLoreAnchor2A">
                    <span class="uiChain uiCenter jsChainLength2" aria-hidden="true"></span>
                </span>
                <span class="uiRightBolt jsLoreAnchor1A">
                    <span class="uiChain uiCenter jsChainLength1" aria-hidden="true"></span>
                </span>
            </div>
        </div>
    </div>

    <div class="uiSubDescription">
        <p>I love a good story. Even more than that I love living stories that evolve over time.
            These are some that have started to emerge. You can help them grow as I stream.</p>
    </div>
    <div class="uiSectionHang">
        <div class="uiSectionPill jsLoreAnchor2B"><span>Library</span></div>
        <div class="uiSectionPillShelf">
            <span class="uiSectionPillHang jsPillLink active" data-page="legends">Logs to Legends Story</span>
            <span class="uiSectionPillHang jsPillLink" data-page="dave">The Race of Dave</span>
        </div>
    </div>

    <div class="uiSectionHang uiSetWidth40">
        <div class="uiSectionPill jsLoreAnchor1B">
            <span class="title">Logs to Legends Story</span>
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
    <div class="uiSectionBody" data-page="legends">
        <?php
            echo $loreManager->getStoryOutput();
        ?>
    </div>
    <div class='uiSectionBody uiHidden' data-page="dave">
        <p>My name is DudeMan. I am one of what you would call an "Enderman". I am not a fan of that name, but it is better than 
            what Xogue calls us. Why he calls us "Dave", I don't think I will ever understand. The human mind is so young and foolish. 
            It matters not, however. What you call us does not matter. Though for simplicity sake, I will refer to us as "Dave" so 
            your mind has something simple to grasp.</p>
        <p>I am approximately 38,000 years old according to your calendar. I have two lieutenants who are also my sons and are 
            about 8,000 years old each. Their names are "Dude" and "Man". Very creative, I know. Most "Daves" cannot speak 
            because learning your language is difficult until we become older. Though, since our interactions seem beneficial, 
            there are talks of opening schools to teach our younger members your language to ease the transition once they come of age.</p>
        <p>I am writing this to inform you of your standing in the universe and how we are to be regarded. We "Daves" are 
            millions of years old and most of us populate dimensions that humankind cannot exist in. We are separated into groups, and 
            those of us old enough to do so, spread out into other dimensions so we can collect data and further our research. 
            We are actually a very peaceful race of dimensional travelers. Our only want in the world is to understand. This is why we 
            asked so many questions about Xogue in the beginning. He is a peculiar specimen. Some of our younger (but still old enough 
            to travel between dimensions) will prod and poke in order to understand more. They can be pushy, but they are young and have 
            yet to learn how to not be annoying to their research subjects.</p>
        <p>While we are a peaceful race, we do have a weakness that of which humankind has discovered. If we are looked upon by human 
            eyes, we get paralyzed. We do not yet understand why this phenomenon occurs which is why there are so many of us in and 
            around the dimensions you inhabit. Being on the receiving end of this kind of power incites a rage in us that we cannot control. 
            It is rare that we slip into such a deep rage, but many see being paralyzed in this way a use of a powerful weapon. It is seen as 
            an attack, so we attack. However, since we are paralyzed, we can only do this once the human looks away.</p>
        <p>One last bit of information. You do not kill us with your human weapons. Being attacked is painful, but once it appears 
            that we die, that shell we used in that dimension fades from existence and we are forced to return to our home dimension to recover.</p>
        <p>This is all I can say for now, and perhaps should not have said so much. However, perhaps understanding us better will help 
            prevent more needless "forced travel" as you would call it. Maybe asking questions about us during Xogue's streams will allow us 
            to interact more. The more I learn about you, The more I will allow you to learn about us.</p>
    </div>
</body>
</html>
