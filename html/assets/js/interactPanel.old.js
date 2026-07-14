

  function updateAg(j){
    try{
      if(!j) return;
      if(typeof j.balance !== 'undefined') {
        var v = (j.balance===null? '--' : j.balance);
        var el = document.getElementById('ag_balance'); if(el) el.textContent = v;
      }
      if(typeof j.has_account !== 'undefined') {
        var hi = document.getElementById('ag_hint'); if(hi) hi.style.display = j.has_account ? 'none' : 'block';
      }
    } catch(e){}
  }
  function switchTab(tab){
    $('.intTab').removeClass('active');
    $('.intTab[data-tab="'+tab+'"]').addClass('active');
    $('.intPanel').attr('hidden', true);
    $('.intPanel[data-tab="'+tab+'"]').removeAttr('hidden');
  }
  $(document).on('click', '.intTab', function(){ switchTab($(this).data('tab')); });

  function setBtnFeedback($btn, ok, originalText, holdMs, message){
    try{
      var clsOk = 'btn-ok';
      var clsErr = 'btn-err';
      var old = originalText || $btn.data('orig');
      if (!old) { old = $btn.text(); $btn.data('orig', old); }
      if (ok) {
        $btn.addClass(clsOk).text('Sent!');
      } else {
        var msg = (message && String(message).length <= 28) ? String(message) : 'Failed';
        $btn.addClass(clsErr).text(msg);
      }
      var delay = typeof holdMs === 'number' ? holdMs : 5000;
      setTimeout(function(){
        // Remove classes to trigger CSS transition back to default
        $btn.removeClass(clsOk + ' ' + clsErr);
        // After a short tick (allow class removal to register), restore text
        setTimeout(function(){ $btn.text(old); }, 1000);
      }, delay);
    }catch(e){}
  }

  // Effects / Sounds / Pranks buttons
  $(document).on('click', '.card .cBtn', async function(){
    var $btn = $(this);
    var $c = $btn.closest('.card');
    var action = $c.data('action');
    var key = $c.data('key');
    var actionLower = String(action||'').toLowerCase();
    if (actionLower === 'viewer_bat_claim') { return; }
    if (!$btn.data('orig')) { $btn.data('orig', $btn.text()); }
    // Immediate progress feedback
    try {
      var pendingText = (function(a){
        a = String(a||'').toLowerCase();
        if (a === 'effect' || a === 'effect_aoe') return 'Giving effect...';
        if (a === 'sound') return 'Playing sound...';
        if (a === 'prank') return 'Running prank...';
        if (a === 'viewer_bat_claim') return 'Claiming...';
        if (a === 'panel_cmd') return 'Executing...';
        return 'Sending...';
      })(action);
      $btn.text(pendingText);
    } catch(e) {}
    $btn.prop('disabled', true);
    var out = await postAction({ type: action, key: key });
    $btn.prop('disabled', false);
    updateAg(out);
    try {
      var ok = !!(out && out.ok);
      // If server indicates a cooldown (either applied on success or remaining on error), honor it
      var cardCd = parseInt($c.data('cooldown')||0,10)||0;
      var cd = cooldownFrom(out, cardCd);
      if (ok) {
        if (cd > 0) { startCooldown($btn, cd); return; }
        // Fallback to configured data-cooldown when server omitted
        setBtnFeedback($btn, true, $btn.data('orig'), 5000, null);
      } else {
        if ((out && out.error === 'cooldown') || (cd > 0)) { startCooldown($btn, cd || cardCd); return; }
        var msg = (out && out.message) ? out.message : (out && out.error ? String(out.error) : null);
        setBtnFeedback($btn, false, $btn.data('orig'), 5000, msg);
      }
    } catch(e){
      setBtnFeedback($btn, !!(out && out.ok), $btn.data('orig'), 5000, (out && !out.ok && out.message) ? out.message : null);
    }
  });

  // Viewer bat claim button (datapack)
  function getBlockedList($card){
    var cached = $card.data('blockedParsed');
    if (cached) return cached;
    var raw = $card.data('blocked');
    var list = [];
    if (Array.isArray(raw)) list = raw;
    else if (typeof raw === 'string' && raw.trim() !== '') {
      try { var j = JSON.parse(raw); if (Array.isArray(j)) list = j; } catch(e){}
    }
    if (!list || !list.length) {
      list = ['fuck','shit','bitch','ass','asshole','dick','pussy','cunt','cock','whore','slut','fag','faggot','gay','anal','penis','vagina','balls','jizz','cum','rape','sex','sexy','nigger','chink','spic','kike','beaner','twat','prick'];
    }
    list = list.map(function(v){ return String(v||'').toLowerCase(); }).filter(function(v){ return v.length>0; });
    $card.data('blockedParsed', list);
    return list;
  }

  function validateBatName($card){
    try{
      var $input = $card.find('.batNameInput');
      var $btn = $card.find('.batClaimBtn');
      var $hint = $card.find('.batHint');
      var name = String($input.val() || '');
      var lower = name.toLowerCase();
      var blocked = getBlockedList($card);
      var bad = null;
      blocked.some(function(word){
        if (word && lower.indexOf(word) !== -1) { bad = word; return true; }
        return false;
      });
      var invalid = !!bad;
      if (invalid) {
        $input.addClass('invalid');
        if ($hint.length) { $hint.text('Name blocked (contains "' + bad + '").').addClass('visible'); }
        $btn.prop('disabled', true);
      } else {
        $input.removeClass('invalid');
        if ($hint.length) { $hint.text('').removeClass('visible'); }
        if (!$btn.hasClass('btn-cd')) { $btn.prop('disabled', false); }
      }
      return !invalid;
    } catch(e){ return true; }
  }

  $(document).on('input keyup change', '.batNameInput', function(){
    var $card = $(this).closest('.card');
    validateBatName($card);
  });

  $(document).on('click', '.batClaimBtn', async function(){
    var $btn = $(this);
    var $card = $btn.closest('.card');
    var name = String(($card.find('.batNameInput').val() || '')).trim();
    if (!validateBatName($card)) { return; }
    if (!$btn.data('orig')) { $btn.data('orig', $btn.text()); }
    $btn.prop('disabled', true).text('Claiming...');
    var payload = { type: 'viewer_bat_claim' };
    try {
      var ctx = window.__VIEWER_CTX__ || {};
      if (ctx.viewer_id) payload.viewer_id = ctx.viewer_id;
      if (ctx.viewer_login) payload.username = ctx.viewer_login;
      if (ctx.viewer_display) payload.display_name = ctx.viewer_display;
    } catch(e){}
    if (name !== '') { payload.bat_name = name; }
    var out = await postAction(payload);
    updateAg(out);
    var ok = !!(out && out.ok);
    var msg = (out && out.message) ? out.message : (out && out.error ? String(out.error) : null);
    var cd = 0;
    if (out && typeof out.cooldown === 'number') cd = out.cooldown;
    else if (out && typeof out.cooldown_remaining === 'number') cd = out.cooldown_remaining;
    if (!cd) { cd = parseInt($card.data('cooldown') || 0, 10) || 0; }
    setBtnFeedback($btn, ok, $btn.data('orig'), 4000, ok ? null : msg);
    if (cd > 0) { startCooldown($btn, cd); }
    $btn.prop('disabled', false);
    if (ok) { try { $card.find('.batNameInput').val(''); validateBatName($card); } catch(e){} }
  });

  // Spawn generator logic
  function sanitizeInt(v){ var n = parseInt(String(v||'').trim(),10); return isNaN(n)||n<0?0:n; }
  function updateSpawn(){
    var totalCount=0,totalCost=0; var counts={};
    $('.spawnRow').each(function(){
      var $r=$(this); var key=$r.data('mob'); var cost=parseInt($r.data('cost'),10)||0;
      var cnt=sanitizeInt($r.find('input').val()); if(cnt>0){ totalCount+=cnt; totalCost+=cnt*cost; counts[key]=cnt; }
    });
    $('#sum_count').text(totalCount); $('#sum_cost').text(totalCost);
    // Compute dynamic cooldown for the batch
    try{
      var cfg = window.__SPAWN_CD_CFG || { weights:{ bat:0 }, min:20, max:120, factor:1.0 };
      var weights = cfg.weights || {}; var min = cfg.min||20, max=cfg.max||120, factor = (typeof cfg.factor==='number'?cfg.factor:1.0);
      var sum=0; Object.keys(counts).forEach(function(k){ var n=counts[k]||0; var w=(typeof weights[k]==='number'?weights[k]:null); if(w===null){
        // fallback weight from cost
        var $row = $('.spawnRow[data-mob="'+k+'"]').first(); var c = parseInt($row.data('cost')||0,10)||0; w = Math.max(0, Math.round(c * factor));
      }
      if (w>0) sum += (w*n); });
      if (sum>0){ if(sum<min) sum=min; if(sum>max) sum=max; }
      $('#sum_cd').text((sum||0) + 's');
      // Keep the latest estimate on the button so we can use it if server omits
      $('#spawnBtn').data('cooldown', sum||0);
    }catch(e){}
    return counts;
  }
  $(document).on('click', '.spawnRow .inc', function(){ var $i=$(this).siblings('input'); $i.val(sanitizeInt($i.val())+1); updateSpawn(); });
  $(document).on('click', '.spawnRow .dec', function(){ var $i=$(this).siblings('input'); var v=Math.max(0,sanitizeInt($i.val())-1); $i.val(v); updateSpawn(); });
  $(document).on('input change', '.spawnRow input', updateSpawn);
  updateSpawn();
  $(document).on('click', '#spawnBtn', async function(){
    var $btn = $(this);
    var counts = updateSpawn();
    if(Object.keys(counts).length===0){ return; }
    if (!$btn.data('orig')) { $btn.data('orig', $btn.text()); }
    // Immediate progress feedback
    try { $btn.text('Spawning mobs...'); } catch(e){}
    $btn.prop('disabled', true);
    var out = await postAction({ type:'spawn', mobs: counts });
    $btn.prop('disabled', false);
    updateAg(out);
    try {
      var ok = !!(out && out.ok);
      var cd = 0;
      if (out && typeof out.cooldown === 'number') cd = out.cooldown;
      else if (out && typeof out.cooldown_remaining === 'number') cd = out.cooldown_remaining;
      if (ok) {
        if (cd > 0) { startCooldown($btn, cd); return; }
        setBtnFeedback($btn, true, $btn.data('orig'), 3000, null);
      } else {
        if ((out && out.error === 'cooldown') || (cd > 0)) { var fallback = parseInt($btn.data('cooldown')||0,10)||60; startCooldown($btn, cd || fallback); return; }
        var msg = (out && out.message) ? out.message : (out && out.error ? String(out.error) : null);
        setBtnFeedback($btn, false, $btn.data('orig'), 5000, msg);
      }
    } catch(e){
      setBtnFeedback($btn, !!(out && out.ok), $btn.data('orig'), 5000, (out && !out.ok && out.message) ? out.message : null);
    }
  });
  // On load, fetch status to populate AGs
  (async function(){ try{ var out = await postAction({type:'status'}); updateAg(out);}catch(e){} })();
});





