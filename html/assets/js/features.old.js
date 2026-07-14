$(function(){
  var $top = $(".feat_link");
  var $sub = $(".feat_sub_link");
  var defaultTop = 'interaction';
  var defaultSub = 'gems';

  function switchTop(key){
    var $link = $top.filter('[data-section="'+key+'"]');
    if(!$link.length) return;
    $top.removeClass('active');
    $link.addClass('active');
    $(".featuresSection").attr('hidden', true);
    $("#section-"+key).removeAttr('hidden');
    $(".featuresTitle").text($link.text());
  }

  function switchSub(key){
    if(!$sub.length) return;
    $sub.removeClass('active');
    var $link = $sub.filter('[data-sub="'+key+'"]');
    if($link.length){
      $link.addClass('active');
      $(".subTitle").text($link.text());
    }
  }

  function parseHash(){
    // Use GET variable `focus` to choose initial tab/sub; fallback to defaults
    try {
      var params = new URLSearchParams(window.location.search || '');
      var focus = (params.get('focus') || '').toString().trim().toLowerCase();
      if (!focus) return { top: defaultTop, sub: defaultSub };
      // Map focus keys to section/sub
      if (focus === 'gems') return { top: 'interaction', sub: 'gems' };
      if (focus === 'interacting') return { top: 'interaction', sub: 'easy' };
      // Power Spawning tab removed
      // Unknown → defaults
      return { top: defaultTop, sub: defaultSub };
    } catch(_) {
      return { top: defaultTop, sub: defaultSub };
    }
  }

  function setHash(topKey, subKey){ /* no-op (URL unchanged) */ }

  function applyState(topKey, subKey, updateUrl){
    switchTop(topKey);
    if(topKey === 'interaction'){
      switchSub(subKey || defaultSub);
    }
    if(updateUrl){ setHash(topKey, subKey); }
  }

  // Click handlers update both UI and URL
  $top.on('click', function(e){
    e.preventDefault();
    var topKey = $(this).data('section');
    var subKey = (topKey === 'interaction') ? ($sub.filter('.active').data('sub') || defaultSub) : null;
    applyState(topKey, subKey, false);
  });

  $sub.on('click', function(e){
    e.preventDefault();
    var subKey = $(this).data('sub');
    var topKey = $top.filter('.active').data('section') || defaultTop;
    if(topKey !== 'interaction') topKey = 'interaction';
    applyState(topKey, subKey, false);
  });

  // Respond to URL hash (deep-link + back/forward)
  // No hash syncing

  // Initial state from URL (or defaults)
  var init = parseHash();
  applyState(init.top, init.sub, false);
});
