// Stream clips page + BRB overlay logic
// Stream clips page + BRB overlay logic
// Uses /api/clips/clipsApi.php to load approved clips.

$(function () {
  var $body = $('body');
  var isOverlay = $body.hasClass('overlay-brb_clips');
  var $root = $('#clipsRoot');
  if (!$root.length) return;

  var $player = $root.find('.overlayPlayer');
  var $titleBar = $player.find('.overlayTitle');
  var $grid = $root.find('.clipsGrid');
  var clips = [];
  var rotateHandle = null;
  var rotateDeadline = 0;
  var rotateRemainingMs = null;
  var rotateNext = null;
  var rotationTimerPaused = false;
  var rotationStopped = false;
  var currentClipId = null;

  function scheduleRotation(delayMs) {
    rotateRemainingMs = Math.max(0, delayMs);
    if (rotationStopped || rotationTimerPaused || !rotateNext) return;

    rotateDeadline = Date.now() + rotateRemainingMs;
    rotateHandle = setTimeout(function () {
      rotateHandle = null;
      rotateDeadline = 0;
      rotateRemainingMs = null;
      rotateNext();
    }, rotateRemainingMs);
  }

  function pauseRotationTimer() {
    if (rotationStopped || rotationTimerPaused) return;
    rotationTimerPaused = true;

    if (rotateHandle) {
      rotateRemainingMs = Math.max(0, rotateDeadline - Date.now());
      clearTimeout(rotateHandle);
      rotateHandle = null;
      rotateDeadline = 0;
    }
  }

  function resumeRotationTimer() {
    if (rotationStopped || !rotationTimerPaused) return;
    rotationTimerPaused = false;

    if (rotateRemainingMs !== null) {
      scheduleRotation(rotateRemainingMs);
    }
  }

  function bindRotationTimerToVideo(video) {
    if (!video || !autoplayEnabled) return;

    video.addEventListener('pause', function () {
      if ($player.find('video')[0] === video && !video.ended) {
        pauseRotationTimer();
      }
    });
    video.addEventListener('play', function () {
      if ($player.find('video')[0] === video) {
        resumeRotationTimer();
      }
    });
  }

  function getQueryParam(name) {
    var search = window.location.search || '';
    if (!search) return '';
    var params = new URLSearchParams(search);
    return params.get(name) || '';
  }

  function normalizeMode(v) {
    v = (v || '').toString().toLowerCase();
    if (v === 'top' || v === 'popular' || v === 'most') return 'top';
    if (v === 'recent' || v === 'latest' || v === 'new') return 'recent';
    return isOverlay ? 'top' : 'recent';
  }

  var initialMode = normalizeMode(getQueryParam('mode') || getQueryParam('sort'));

  // For the public viewer/BRB page, always use the full time
  // range so all stored clips are eligible; the mode toggle
  // only affects ordering (recent vs top).
  var range = 'all';

  var limitFromQuery = parseInt(getQueryParam('limit') || getQueryParam('count') || '', 10);
  var defaultLimit = 100;
  var limit = (!isNaN(limitFromQuery) && limitFromQuery > 0 && limitFromQuery <= 100)
    ? limitFromQuery
    : defaultLimit;

  // Autoplay behavior (applies primarily to the overlay).
  // ?autoplay=0|false|off|no will disable automatic rotation.
  var autoplayParam = (getQueryParam('autoplay') || '').toString().toLowerCase();
  var autoplayEnabled = !(
    autoplayParam === '0' ||
    autoplayParam === 'false' ||
    autoplayParam === 'off' ||
    autoplayParam === 'no'
  );

  function thumbUrl(tpl) {
    if (!tpl) return '';
    return tpl.replace(/%{width}/g, '480').replace(/%{height}/g, '272');
  }

  function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function renderGrid() {
    if (!$grid.length) return;
    if (!clips.length) {
      $grid.html('<div class="clipsEmpty">No clips found yet. Check back after a few streams!</div>');
      return;
    }

    var html = '';
    clips.forEach(function (c, idx) {
      var thumb = thumbUrl(c.thumbnailUrl);
      var views = typeof c.viewCount === 'number'
        ? c.viewCount
        : parseInt(c.viewCount || '0', 10) || 0;
      var created = c.createdAt || '';
      var dateStr = created ? created.substring(0, 10) : '';

      html += '<article class="clipCard" data-index="' + idx + '" data-id="' + c.id + '">' +
        '<div class="thumbWrap">';

      if (thumb) {
        html += '<img src="' + thumb + '" alt="Clip thumbnail">';
      } else if (c.hasLocalFile && c.localUrl) {
        html += '<div class="thumbPlaceholder">Stored clip</div>';
      }

      html += '<div class="thumbOverlay">' +
        '<span class="views">' + views.toLocaleString() + ' views</span>';

      if (dateStr) {
        html += '<span class="date">' + escapeHtml(dateStr) + '</span>';
      }

      html += '</div>' + // thumbOverlay
        '</div>' +       // thumbWrap
        '<div class="clipBody">' +
        '<h3 class="clipTitle">' + escapeHtml(c.displayTitle || c.title || 'Untitled clip') + '</h3>' +
        '<div class="clipMeta">' + escapeHtml(c.creatorName || '') + '</div>' +
        '</div>' +
        '</article>';
    });

    $grid.html(html);
  }

  function markCurrentClip(clip) {
    if (!clip || !clip.id || !$grid.length) return;
    currentClipId = clip.id;
    $grid.find('.clipCard').removeClass('current');
    $grid.find('.clipCard[data-id=\"' + clip.id + '\"]').addClass('current');
  }

  function embedUrlFor(clip) {
    var id = (clip && clip.id) ? String(clip.id) : '';
    if (!id) return '';
    // Twitch fallback embed URL (used when no local file is available).
    var parent = window.location.hostname || 'xogoria.com';
    var base = 'https://clips.twitch.tv/embed?clip=' + encodeURIComponent(id) +
      '&parent=' + encodeURIComponent(parent);

    // For the normal site page, always request autoplay just
    // like the original implementation (this is known to work
    // well in browsers and when used as a Browser Source in OBS).
    if (!isOverlay) {
      return base + '&autoplay=true';
    }

    // For the OBS overlay, honor the ?autoplay= query flag so
    // you can explicitly turn automatic rotation on/off.
    if (autoplayEnabled) {
      return base + '&autoplay=true';
    }
    return base + '&autoplay=false';
  }

  function setPlayerForClip(clip) {
    if (!$player.length || !clip || !clip.id) return;
    var hasLocal = clip.hasLocalFile && clip.localUrl;

    if (!isOverlay) {
      // Site version: prefer locally stored files (Backblaze) when available;
      // otherwise, fall back to the Twitch clip embed.
      if (hasLocal) {
        var startOff = parseFloat(clip.startOffset || 0) || 0;
        var videoHtml = '<video src="' + encodeURI(clip.localUrl) + '" ' +
          'autoplay playsinline controls ' +
          'style="width:100%;height:100%;border:none;display:block;background:#000;"></video>';
        $player.html(videoHtml);
        var v = $player.find('video')[0];
        bindRotationTimerToVideo(v);
        if (v && startOff > 0) {
          v.addEventListener('loadedmetadata', function () {
            if (startOff < v.duration) {
              v.currentTime = startOff;
            }
          }, { once: true });
        }
      } else {
        var iframeHtml = '<iframe src="' + embedUrlFor(clip) + '" ' +
          'allowfullscreen="true" allow="autoplay; fullscreen" frameborder="0"></iframe>';
        $player.html(iframeHtml);
      }
    } else {
      // Overlay version: keep the title bar DOM, but replace any existing
      // media element (iframe/video) with the appropriate source.
      $player.find('iframe,video').remove();

      if (hasLocal) {
        var startOffO = parseFloat(clip.startOffset || 0) || 0;
        var $video = $('<video>', {
          src: clip.localUrl,
          autoplay: true,
          playsinline: true
        }).css({
          width: '100%',
          height: '100%',
          border: 'none',
          display: 'block',
          background: '#000'
        });
        $player.append($video);
        bindRotationTimerToVideo($video[0]);
        try {
          $video[0].addEventListener('loadedmetadata', function () {
            if (startOffO > 0 && startOffO < $video[0].duration) {
              $video[0].currentTime = startOffO;
            }
            $video[0].play().catch(function () { /* autoplay may be gated */ });
          }, { once: true });
        } catch (e) { /* ignore */ }
      } else {
        var $iframe = $('<iframe>', {
          src: embedUrlFor(clip),
          allowfullscreen: 'true',
          allow: 'autoplay; fullscreen',
          frameborder: 0
        });
        $player.append($iframe);
      }
      // Once we have a real clip, drop any "Loading..." message.
      $player.find('.overlayFallback').remove();
    }

    if ($titleBar && $titleBar.length) {
      $titleBar.text(clip.displayTitle || clip.title || 'Untitled clip');
    }

    // Highlight the currently playing clip in the grid (site view).
    markCurrentClip(clip);
  }

  function effectiveDurationSeconds(clip) {
    var total = parseFloat(clip.duration || 0);
    var maxDur = parseFloat(clip.maxDuration || 0);
    var start = parseFloat(clip.startOffset || 0);

    if (isNaN(total) || total <= 0) {
      total = 25;
    }
    if (isNaN(start) || start < 0) {
      start = 0;
    }

    var segmentEnd = (!isNaN(maxDur) && maxDur > 0) ? maxDur : total;
    if (segmentEnd > total) segmentEnd = total;
    if (segmentEnd <= start) {
      // Fallback: ignore bad trim and use full duration.
      start = 0;
      segmentEnd = total;
    }

    var base = segmentEnd - start;
    if (base < 10) base = 10;
    if (base > 90) base = 90;
    return base;
  }

  function markPlayed(clip) {
    if (!clip || !clip.id) return;
    var pc = parseInt(clip.playCount || 0, 10);
    if (isNaN(pc) || pc < 0) pc = 0;
    clip.playCount = pc + 1;
    // Fire and forget; failure is harmless to playback.
    if (window.navigator && navigator.sendBeacon) {
      try {
        var data = new FormData();
        data.append('clipId', clip.id);
        navigator.sendBeacon('/api/clips/clipsPlay.php', data);
      } catch (e) { /* ignore */ }
    } else if (window.fetch) {
      fetch('/api/clips/clipsPlay.php?clipId=' + encodeURIComponent(clip.id), { method: 'GET', keepalive: true })
        .catch(function () { /* ignore */ });
    }
  }

  function randomSubset(source, count) {
    var shuffled = source.slice();
    for (var i = 0; i < count && i < shuffled.length; i += 1) {
      var swapIndex = i + Math.floor(Math.random() * (shuffled.length - i));
      var current = shuffled[i];
      shuffled[i] = shuffled[swapIndex];
      shuffled[swapIndex] = current;
    }
    return shuffled.slice(0, count);
  }

  function chooseNextClip() {
    var pool = clips.slice();
    pool = pool.filter(function (c) { return c.enabled !== false && c.enabled !== 0; });
    if (!pool.length) return null;

    var counts = pool.map(function (clip) {
      var count = parseInt(clip.playCount || 0, 10);
      return isNaN(count) || count < 0 ? 0 : count;
    });
    var positiveCounts = counts.filter(function (count) { return count > 0; });
    var establishedMinimum = positiveCounts.length ? Math.min.apply(Math, positiveCounts) : 0;

    // A newly approved zero that trails the established minimum by more than
    // five joins that existing minimum rank. Existing counts are never lowered.
    if (counts.indexOf(0) !== -1 && establishedMinimum > 5) {
      pool.forEach(function (clip) {
        var count = parseInt(clip.playCount || 0, 10);
        if (isNaN(count) || count < 0) count = 0;
        if (count === 0) clip.playCount = establishedMinimum;
      });
    }

    // A rank is a distinct play-count value. Include every clip tied within
    // the five lowest ranks, so the candidate pool can be larger than five.
    var rankedCounts = pool.map(function (clip) {
      return parseInt(clip.playCount || 0, 10) || 0;
    }).filter(function (count, index, all) {
      return all.indexOf(count) === index;
    }).sort(function (a, b) { return a - b; }).slice(0, 5);

    var candidates = pool.filter(function (clip) {
      var count = parseInt(clip.playCount || 0, 10) || 0;
      return rankedCounts.indexOf(count) !== -1;
    });
    if (!candidates.length) return null;

    // Large tied pools use the requested double draw: random five, then one.
    var finalPool = candidates.length > 8 ? randomSubset(candidates, 5) : candidates;
    return finalPool[Math.floor(Math.random() * finalPool.length)];
  }

  function stopRotation() {
    rotationStopped = true;
    rotationTimerPaused = false;
    rotateRemainingMs = null;
    rotateDeadline = 0;
    rotateNext = null;
    if (rotateHandle) {
      clearTimeout(rotateHandle);
      rotateHandle = null;
    }
  }

  function startRotation() {
    if (!clips.length || !autoplayEnabled) return;
    rotationStopped = false;
    rotationTimerPaused = false;
    rotateRemainingMs = null;
    rotateDeadline = 0;
    if (rotateHandle) {
      clearTimeout(rotateHandle);
      rotateHandle = null;
    }

    rotateNext = function () {
      if (rotationStopped || !clips.length) return;
      var clip = chooseNextClip();
      if (!clip) return;
      markPlayed(clip);
      setPlayerForClip(clip);

      var dur = effectiveDurationSeconds(clip);
      scheduleRotation((dur + 2) * 1000);

      var currentVideo = $player.find('video')[0];
      if (currentVideo && currentVideo.paused) {
        pauseRotationTimer();
      }
    };

    rotateNext();
  }

  function loadClips(mode) {
    if (!mode) mode = initialMode;
    mode = normalizeMode(mode);
    $root.addClass('loading');

    var params = {
      mode: mode,
      range: range,
      limit: limit
    };

    $.getJSON('/api/clips/clipsApi.php', params).done(function (resp) {
      if (!resp || !resp.ok || !resp.clips || !resp.clips.length) {
        clips = [];
        renderGrid();
        if ($player.length) {
          $player.find('.overlayFallback').text('No clips found yet.');
        }
        return;
      }

      // The public API already limits this response to approved clips.
      // Keep the enabled guard so a stale response cannot show a hidden clip.
      clips = resp.clips.filter(function (c) {
        if (!c || !c.id) return false;
        if (c.enabled === false || c.enabled === 0) return false;
        return true;
      });
      renderGrid();

      if (!clips.length) return;

      if (!autoplayEnabled) {
        // Even without rotation, use the balanced selection rules for the
        // initially displayed clip. Viewers can still choose a grid card.
        setPlayerForClip(chooseNextClip() || clips[0]);
      } else {
        // Autoplay enabled (default): rotate through clips
        // back-to-back until stopped, for both the site and
        // the overlay.
        startRotation();
      }
    }).fail(function () {
      if ($player.length) {
        $player.find('.overlayFallback').text('Unable to load clips.');
      }
      if ($grid.length) {
        $grid.html('<div class="clipsError">Unable to load clips right now.</div>');
      }
    }).always(function () {
      $root.removeClass('loading');
    });
  }

  // Full-page interactions
  if (!isOverlay) {
    $('.clipsModeToggle .modeBtn').on('click', function () {
      var $btn = $(this);
      var mode = $btn.data('mode');
      if (!mode || $btn.hasClass('active')) return;
      $('.clipsModeToggle .modeBtn').removeClass('active');
      $('.clipsModeToggle .modeBtn').attr('aria-pressed', 'false');
      $btn.addClass('active');
      $btn.attr('aria-pressed', 'true');
      loadClips(mode);
    });

    $root.on('click', '.clipCard', function () {
      var idx = parseInt($(this).attr('data-index') || '0', 10);
      if (!isNaN(idx)) {
        var clip = clips[idx];
        if (clip) setPlayerForClip(clip);
      }
    });
  } else {
    // Overlay-only controls (e.g., stop button)
    $(document).on('click', '.overlayStopBtn', function () {
      stopRotation();
      if ($player.length) {
        $player.html('<div class="overlayFallback">Clips paused</div>');
      }
    });
  }

  // Initial load
  loadClips(initialMode);
});
