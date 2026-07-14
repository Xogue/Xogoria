$(function () {
  var categories = $('.cmdLink.category');
  var permissions = $('.cmdLink.permission');
  var activeCategory = "all";
  var activePermission = "everyone";

  $('.category').click(function () {
    activeCategory = $(this).data('value');
    categories.removeClass('active');
    $(this).addClass('active');
    updateContent();
  });

  $('.permission').click(function () {
    activePermission = $(this).data('value');
    permissions.removeClass('active');
    $(this).addClass('active');
    updateContent();
  });

  function titleCase(s) {
    return String(s || "").replace(/\b([A-Za-z])(\w*)/g, function(_, a, b){
      return a.toUpperCase() + b.toLowerCase();
    });
  }

  function updateContent() {
    var title = "<span>" + titleCase(activeCategory) + "</span> Commands for <span>" + titleCase(activePermission) + "</span>";
    $(".title").html(title);
    var url = "libs/AjaxCommandList.php?category=" + activeCategory + "&perms=" + activePermission;
    $(".uiSectionBody span").load(url);
  }
});
