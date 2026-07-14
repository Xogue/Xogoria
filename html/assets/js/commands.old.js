$(function () {


  var currentPage = 1;
  var pageSize = 15;

  // Table filtering by selected category/permission + search, with pagination
  function ensureEmptyRow($tbody) {
    var $er = $tbody.find('tr.empty');
    if (!$er.length) {
      $er = $('<tr class="empty"><td class="empty" colspan="4">No matching commands.</td></tr>');
      $tbody.append($er);
    }
    return $er;
  }
  function filterTable(left, right, resetPage) {
    var $table = $('#commandsTable');
    if (!$table.length) return;
    var $tbody = $table.find('tbody');
    var c = (left || '').toLowerCase();
    var p = (right || '').toLowerCase();
    var term = ($.trim($('#commandsSearch').val() || '') + '').toLowerCase();
    if (resetPage) currentPage = 1;
    var catAll = (c === 'all' || !c);
    var permAll = (p === 'everyone' || !p);
    var anyShown = false;
    var matched = [];
    $tbody.find('tr.data-row').each(function(){
      var $r = $(this);
      var rc = String($r.data('cat') || '').toLowerCase();
      var rp = String($r.data('perm') || '').toLowerCase();
      var matchesFilters = (catAll || rc === c) && (permAll || rp === p);
      var matchesSearch = true;
      if (term) {
        var cmd = ($r.children('td').eq(0).text() || '').toLowerCase();
        var desc = ($r.children('td').eq(1).text() || '').toLowerCase();
        matchesSearch = (cmd.indexOf(term) !== -1) || (desc.indexOf(term) !== -1);
      }
      var show = matchesFilters && matchesSearch;
      if (show) matched.push($r);
    });

    // Pagination
    var total = matched.length;
    var pageCount = Math.max(1, Math.ceil(total / pageSize));
    if (currentPage > pageCount) currentPage = pageCount;
    var start = (currentPage - 1) * pageSize;
    var end = start + pageSize;

    // Hide all, then show slice
    $tbody.find('tr.data-row').hide();
    $(matched.slice(start, end)).each(function(){ $(this).show(); anyShown = true; });

    var $er = $tbody.find('tr.empty');
    if (!anyShown) ensureEmptyRow($tbody).show(); else $er.hide();

    // Update pagination UI
    var $pg = $('.cmdPagination');
    if (total > pageSize) {
      $pg.removeAttr('hidden');
      $('.cmdInfo').text('Page ' + currentPage + ' of ' + pageCount);
    } else {
      $pg.attr('hidden', true);
    }
  }

  function activateByValue($set, value) {
    var $el = $set.filter("[data-value='" + value + "']").first();
    if (!$el.length) return;
    $set.removeClass('active');
    $el.addClass('active');
  }

  function parseHash() {
    try {
      var params = new URLSearchParams(window.location.search || '');
      var focus = (params.get('focus') || '').toString().trim().toLowerCase();
      var left = ($left.filter('.active').data('value') || 'all') + '';
      var right = ($right.filter('.active').data('value') || 'everyone') + '';
      if (!focus) return { left: left, right: right };
      // Expect format: category_permission (e.g., all_everyone)
      var parts = focus.split('_');
      if (parts.length >= 1 && parts[0]) left = parts[0];
      if (parts.length >= 2 && parts[1]) right = parts[1];
      // Validate against allowed lists
      var allowedLeft = { all:1, utility:1, playful:1, supportive:1, other:1 };
      var allowedRight = { everyone:1, viewers:1, vips:1, mods:1 };
      if (!allowedLeft[left]) left = ($left.filter('.active').data('value') || 'all') + '';
      if (!allowedRight[right]) right = ($right.filter('.active').data('value') || 'everyone') + '';
      return { left: left, right: right };
    } catch(_) {
      var l = ($left.filter('.active').data('value') || 'all') + '';
      var r = ($right.filter('.active').data('value') || 'everyone') + '';
      return { left: l, right: r };
    }
  }

  function setHash(left, right) { /* no-op (URL unchanged) */ }

  function applyState(left, right, updateUrl) {
    activateByValue($left, left);
    activateByValue($right, right);
    updateHeader();
    filterTable(left, right);
    if (updateUrl) setHash(left, right);
  }

  $left.on('click', function (e) {
    e.preventDefault();
    var l = $(this).data('value');
    var r = ($right.filter('.active').data('value') || 'everyone');
    applyState(l, r, false);
  });

  $right.on('click', function (e) {
    e.preventDefault();
    var r = $(this).data('value');
    var l = ($left.filter('.active').data('value') || 'all');
    applyState(l, r, false);
  });

  // Search handlers
  $(document).on('input', '#commandsSearch', function(){
    var l = ($left.filter('.active').data('value') || 'all');
    var r = ($right.filter('.active').data('value') || 'everyone');
    filterTable(l, r, true);
  });
  $(document).on('click', '.qsClear', function(){
    var $inp = $('#commandsSearch');
    if ($inp.length) { $inp.val(''); }
    var l = ($left.filter('.active').data('value') || 'all');
    var r = ($right.filter('.active').data('value') || 'everyone');
    filterTable(l, r, true);
  });

  // Pagination controls
  $(document).on('click', '.cmdPrev', function(){
    var l = ($left.filter('.active').data('value') || 'all');
    var r = ($right.filter('.active').data('value') || 'everyone');
    currentPage = Math.max(1, currentPage - 1);
    filterTable(l, r, false);
  });
  $(document).on('click', '.cmdNext', function(){
    var l = ($left.filter('.active').data('value') || 'playful');
    var r = ($right.filter('.active').data('value') || 'everyone');
    currentPage = currentPage + 1; // will be clamped in filterTable
    filterTable(l, r, false);
  });

  // No hash syncing

  // Initial state from GET `focus` or current markup
  var init = parseHash();
  applyState(init.left, init.right, false);
  try {
    var params = new URLSearchParams(window.location.search || '');
    if (params.has('focus')) {
      var $h = $('#commandsHeader');
      if ($h.length) {
        setTimeout(function(){
          try { $h[0].scrollIntoView({behavior:'smooth', block:'start'}); }
          catch(_) { $('html, body').animate({scrollTop: $h.offset().top}, 300); }
        }, 30);
      }
    }
  } catch(_) { /* ignore */ }
});
