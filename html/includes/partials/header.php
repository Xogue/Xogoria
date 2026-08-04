<?php $current = basename( $_SERVER[ "PHP_SELF" ] ); ?>
<div id="header">
    <div id="circleLogo">
        <a href="http://twitch.tv/xogue29" target="_blank" class="socialLink platform">
            <div id="twitch"></div>Twitch
        </a>
        <a href="http://youtube.com/TheRealXogue" target="_blank" class="socialLink platform">
            <div id="youtube"></div>YouTube
        </a>
        <a href="http://patreon.com/Xogue" target="_blank" class="socialLink extra">
            Patreon<div id="patreon"></div>
        </a>
        <a href="http://discord.gg/FaXBje6" target="_blank" class="socialLink extra">
            Discord<div id="discord"></div>
        </a>
        <div class="headerFeatureLinks" aria-label="Featured pages">
            <a href="/community.php" class="headerFeatureLink<?php echo $current === "community.php" ? " active" : ""; ?>">
                Community Info
            </a>
            <a href="/clips.php" class="headerFeatureLink<?php echo $current === "clips.php" ? " active" : ""; ?>">
                Stream Clips
            </a>
        </div>
    </div>
</div>
