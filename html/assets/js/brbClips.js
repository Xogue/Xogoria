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
  var rotationQueue = [];
  var rotationIndex = 0;
  var playedSession = {};
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
    playedSession[clip.id] = true;
    if (clip.isFavorite) {
      var pc = parseInt(clip.playCount || 0, 10);
      if (isNaN(pc) || pc < 0) pc = 0;
      clip.playCount = pc + 1;
      // Fire and forget; failure is harmless.
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
  }

  function buildPriorityQueue(sourceClips) {
    var pool = (sourceClips || []).slice();
    if (!pool.length) return [];

    // Enabled only (API may still include disabled when asked)
    pool = pool.filter(function (c) { return c.enabled !== false && c.enabled !== 0; });
    if (!pool.length) return [];

    var favorites = [];
    var nonFavs = [];
    pool.forEach(function (c) {
      if (c.isFavorite) favorites.push(c);
      else nonFavs.push(c);
    });

    favorites.sort(function (a, b) {
      var pa = parseInt(a.playCount || 0, 10);
      var pb = parseInt(b.playCount || 0, 10);
      if (isNaN(pa) || pa < 0) pa = 0;
      if (isNaN(pb) || pb < 0) pb = 0;
      if (pa !== pb) return pa - pb; // lowest playCount first
      var da = a.createdAt || '';
      var db = b.createdAt || '';
      if (da === db) return 0;
      return da < db ? 1 : -1; // newer first
    });

    var recent = nonFavs.slice().sort(function (a, b) {
      var da = a.createdAt || '';
      var db = b.createdAt || '';
      if (da === db) return 0;
      return da < db ? 1 : -1; // newer first
    });

    var mostWatched = nonFavs.slice().sort(function (a, b) {
      var va = parseInt(a.viewCount || 0, 10);
      var vb = parseInt(b.viewCount || 0, 10);
      if (isNaN(va) || va < 0) va = 0;
      if (isNaN(vb) || vb < 0) vb = 0;
      if (va === vb) return 0;
      return vb - va; // highest first
    });

    var seen = {};
    var queue = [];
    function pushList(list) {
      list.forEach(function (c) {
        if (!c || !c.id) return;
        if (seen[c.id]) return;
        seen[c.id] = true;
        queue.push(c);
      });
    }

    // Favorites (by playCount), then newest, then most watched.
    pushList(favorites);
    pushList(recent);
    pushList(mostWatched);
    return queue;
  }

  function rebuildRotationQueue() {
    if (!clips.length) {
      rotationQueue = [];
      rotationIndex = 0;
      return;
    }
    // Prefer clips that have not yet been played this session.
    var unplayed = clips.filter(function (c) {
      return !playedSession[c.id] && c.enabled !== false && c.enabled !== 0;
    });
    var source = unplayed.length ? unplayed : clips.slice();
    if (!unplayed.length) {
      // New cycle: allow repeats again.
      playedSession = {};
    }
    rotationQueue = buildPriorityQueue(source);
    rotationIndex = 0;
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
      if (!rotationQueue.length || rotationIndex >= rotationQueue.length) {
        rebuildRotationQueue();
      }
      if (!rotationQueue.length) return;

      var clip = rotationQueue[rotationIndex];
      rotationIndex += 1;
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
        rotationQueue = [];
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
        // Autoplay disabled: always show just the first clip
        // (both on the site and in the overlay), letting the
        // viewer manually pick others from the grid.
        setPlayerForClip(clips[0]);
      } else {
        // Autoplay enabled (default): rotate through clips
        // back-to-back until stopped, for both the site and
        // the overlay.
        rebuildRotationQueue();
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
