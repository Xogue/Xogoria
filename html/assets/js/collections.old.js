$(function () {
  var $links = $(".collect_link");
  var $sections = $(".collectionsSection");

  function switchCollection(key) {
    var $link = $links.filter('[data-section="' + key + '"]');
    if (!$link.length) return;

    // Active state
    $links.removeClass("active");
    $link.addClass("active");

    // Show matching section
    $sections.attr("hidden", true);
    $("#section-" + key).removeAttr("hidden");

    // Update header title
    $(".collectionsTitle").text($link.text());
  }

  // Click handling
  $links.on("click", function (e) {
    e.preventDefault();
    var key = $(this).data("section");
    switchCollection(key);
  });

  // Initial state: support GET `focus` (quotes|objectives|name)
  var defaultKey = "quotes";
  function scrollToHeader(){
    var $h = $('#collectionsHeader');
    if ($h.length){
      try { $h[0].scrollIntoView({behavior:'smooth', block:'start'}); }
      catch(_){ $('html, body').animate({scrollTop: $h.offset().top}, 300); }
    }
  }
  try {
    var params = new URLSearchParams(window.location.search || '');
    var focus = (params.get('focus') || '').toString().trim().toLowerCase();
    var usedFocus = false;
    if (focus === 'objectives') {
      switchCollection('objectives'); usedFocus = true;
    } else if (focus === 'name') {
      switchCollection('monsters'); usedFocus = true;
    } else if (focus === 'quotes') {
      switchCollection('quotes'); usedFocus = true;
    } else {
      switchCollection(defaultKey);
    }
    if (usedFocus) setTimeout(scrollToHeader, 30);
  } catch(_) {
    switchCollection(defaultKey);
  }
});
