<?php
    require_once __DIR__ . "/includes/session.php";

    $cssAssets = [ "ui", "header", "nav", "live", "about" ];
    $jsAssets = [ "jquery", "ui", "about" ];
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
            <div class="uiPageTitle">Welcome To Xogoria</div>
        </div>

        <div id="uiSubBanner">
            <div class="uiTitlePill">Schedule</div>
        </div>

        <div class="uiSubDescription">
            <span class="amp">&</span>
            <div class="scheduleLines">
                <div class="days">
                    <span class="day">Mondays</span>
                </div>
                <div class="times">
                    <span class="time">2:00 PM <span class="tz">CDT</span></span>
                </div>
            </div>
            <div class="discordLine">Join the discord to hear about extra unplanned streams!</div>
        </div>

        <div class="uiSectionHang">
            <div class="uiTitlePill"><span>Stream Content</span></div>
            <p class="uiSectionDescription">
                I will mostly be playing Minecraft, but sometimes another game will catch my interest, and it will make an appearance on stream.
            </p>
        </div>

        <div class="uiSectionHang">
            <div class="uiTitlePill">
                <span class="title">Who is Xogue?</span>
            </div>
            <div class="uiSectionPillShelf">
                <span class="uiSectionPillHang jsPillLink active" data-page="quick">Quick Version</span>
                <span class="uiSectionPillHang jsPillLink" data-page="long">Long Version</span>
            </div>
        </div>

        <div class="uiSectionHang">
            <div class="uiTitlePill">
                <span class="title">Quick Version</span>
            </div>
        </div>
        <div class="uiSectionBody" data-page="quick">
            <p>Xogoria is a place for those who long to belong—where being different is celebrated, not criticized.</p>
            <p>I grew up judged, and I understand how powerful it is to have just one space where you’re welcomed unconditionally. That’s why I created this corner of the internet—to be a place of comfort and acceptance for anyone seeking it.</p>
            <p>I don’t know your story, but I know one thing: you’re welcome here. Whether you seek acceptance or offer it, you fit in here.</p>
        </div>
        <div class="uiSectionBody uiHidden" data-page="long">
            <p>This world is full of people who are all different in their own ways—and that's a good thing. But too often, people get treated badly just because they don't fit into some idea of "normal." I want my corner of the internet to be a place where anyone can feel welcome, included, and entertained.</p>
            <p>For me, streaming isn't just about playing games or filling time. My goal is simple: if I can make you laugh, even for a moment, then maybe you're not thinking about whatever else might be weighing on you. In those moments of pure humor, the problems of the world can take a backseat.</p>
            <p>I know laughter doesn't fix everything—it's often short-lived—but I believe those little breaks are worth something. They give us a chance to breathe. That's why I stream with as much energy, weirdness, and heart as I can muster: to share as many of those moments as possible with you.</p>
            <p>Streaming might not seem that serious to some, but it matters a lot to me.</p>
        </div>
    </body>
</html>
