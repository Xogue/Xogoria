$(document).ready(function () {
    $(".typeButton").on("click", function () {
        $(".typeButton").removeClass("active");
        $(".interactions").addClass("uiHidden");
        $(this).addClass("active");

        let page = $(this).data("tab");
        $(".interactions[data-tab='" + page + "']").removeClass("uiHidden");
    });

    $(".cardButton")
        .each(function () {
            const card = new InteractCard($(this).closest(".card"));
            $(this).data("cardInstance", card);
        })
        .click(function () {
            $(this).data("cardInstance").activate();
        });

    $(".adminPanelTitle").click(function () {
        $(".adminPanel").toggleClass("active");
        $(".adminPanelTitle", $(this)).text(
            $(".adminPanel").hasClass("active") ? "Close Admin Controls" : "Open Admin Controls",
        );
    });

    $(".setGame").click(async function () {
        const game = $('select[name="gameSelector"] option:selected').data("game");
        const profile = $('select[name="gameSelector"] option:selected').data("profile");
        const payload = {
            request: "configure",
            type: "game",
            action: game + "-" + profile,
        };

        try {
            const res = await fetch("/api/xogoriaApi.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify(payload),
            });
            location.reload();
        } catch (e) {
            return { ok: false, error: String(e) };
        }
    });

    $(".typeButton").first().click();
});
