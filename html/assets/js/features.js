$(document).ready(function () {
	$(".jsFloatLink").on('click', function () {
		$(".jsFloatLink").removeClass("active");
		$(".uiSubSectionBody").addClass("uiHidden");
		$(this).addClass("active");

		var page = $(this).data('page');
		$(".uiSubSectionBody[data-page='" + page + "']").removeClass("uiHidden");
	});

	$(".jsGameLink").on('click', function () {
		$(".jsGameLink").removeClass("active");
		$(".uiSectionBody").addClass("uiHidden");
		$(".uiSubSectionBody").addClass("uiHidden");
		$(this).addClass("active");

		var page = $(this).data('page');
		$(".uiSectionBody[data-page='" + page + "']").removeClass("uiHidden");
		$(".uiSectionBody[data-page='" + page + "']").children(".uiSectionFloatShelf").children(".jsFloatLink").first().click();
		
	});
});