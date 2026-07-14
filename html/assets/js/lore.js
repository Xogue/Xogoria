$(function () {
    setChaninLengths();

    $('.chapterLink').on('click', function () {
        if ($(this).next('.chapterContent').hasClass('uiHidden')) {
            $('.chapterContent').addClass('uiHidden');
            $('.jsStateSymbol').text('>').css('font-family', 'britanic, sans-serif');
            $(this).next('.chapterContent').removeClass('uiHidden');
            $(this).find('.jsStateSymbol').text('v').css('font-family', 'fr-bold, sans-serif');
            $(this).closest('.chapter').get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            $(this).next('.chapterContent').addClass('uiHidden');
            $(this).find('.jsStateSymbol').text('>').css('font-family', 'britanic, sans-serif');
            $(".title").get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        setChaninLengths()
    });

    $('.subChapterLink').on('click', function () {
        if ($(this).next('.chapterContent').hasClass('uiHidden')) {
            $('.chapterContent').addClass('uiHidden');
            $('.jsStateSymbol').text('>').css('font-family', 'britanic, sans-serif');
            $(this).next('.chapterContent').removeClass('uiHidden');
            $(this).find('.jsStateSymbol').text('v').css('font-family', 'fr-bold, sans-serif');
            $(this).closest('.subChapter').get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            $(this).next('.chapterContent').addClass('uiHidden');
            $(this).find('.jsStateSymbol').text('>').css('font-family', 'britanic, sans-serif');
            $(".title").get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        setChaninLengths()
    });

    $('.readLore').on('click', function () {
        var button = $(this);
        var source = button.attr('data-src');
        var currentAudio = $(document).data('currentLoreAudio');
        var currentButton = $(document).data('currentLoreAudioButton');

        $('.readLore').each(function () {
            var readButton = $(this);
            if (!readButton.data('label')) {
                readButton.data('label', readButton.text());
            }
        });

        function resetButton(readButton) {
            readButton.data('playing', false).text(readButton.data('label'));
        }

        if (!source) {
            resetButton(button);
            return;
        }

        function stopAudio(audio) {
            if (!audio) {
                return;
            }

            audio.pause();
            audio.removeAttribute('src');
            audio.load();
        }

        if (currentAudio) {
            stopAudio(currentAudio);
            if (currentButton) {
                resetButton(currentButton);
            }
            $(document).removeData('currentLoreAudio currentLoreAudioButton');

            if (currentButton && currentButton.is(button)) {
                return;
            }
        }

        var audio = new Audio(source);
        audio.preload = 'auto';

        $('.readLore').each(function () {
            resetButton($(this));
        });

        audio.addEventListener('playing', function () {
            button.data('playing', true).text('Stop audio');
            $(document).data('currentLoreAudio', audio);
            $(document).data('currentLoreAudioButton', button);
        });

        audio.addEventListener('ended', function () {
            stopAudio(audio);
            resetButton(button);
            $(document).removeData('currentLoreAudio currentLoreAudioButton');
        });

        audio.addEventListener('error', function () {
            stopAudio(audio);
            resetButton(button);
            $(document).removeData('currentLoreAudio currentLoreAudioButton');
        });

        var player = audio.play();
        if (player && typeof player.then === 'function') {
            player.catch(function () {
                stopAudio(audio);
                resetButton(button);
                $(document).removeData('currentLoreAudio currentLoreAudioButton');
            });
        }
    });

    function setChaninLengths() {
        $loreboltCount = $('.jsLoreBolt').length;
        for (let i = 1; i <= $loreboltCount - 1; i++) {
            const elementA = $('.jsLoreBolt[data-boltNum="' + i + '.0"]')[0];
            const elementB = $('.jsLoreBolt[data-boltNum="' + (i + 1) + '.0"]')[0];
            const aYPos = elementA.getBoundingClientRect();
            const bYPos = elementB.getBoundingClientRect();
            const distance = (bYPos.top - aYPos.bottom) + 10;

            $('.jsLoreBolt[data-boltNum="' + i + '.0"]')[0].parentNode.style.setProperty('--jsBoltChainLength', distance + 'px');
        }
    }
});
