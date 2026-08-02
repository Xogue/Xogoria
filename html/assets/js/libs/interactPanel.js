$(document).ready(function () {
    $(".typeButton").on("click", function () {
        $(".typeButton").removeClass("active");
        $(".interactions").addClass("uiHidden");
        $(this).addClass("active");

        let page = $(this).data("tab");
        $(".interactions[data-tab='" + page + "']").removeClass("uiHidden");
    });

    $(".cardButton").not(".spawnButton, .batClaimBtn")
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

    const postInteraction = async (payload) => {
        try {
            const response = await fetch("/api/xogoriaApi.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify({ request: "interaction", ...payload }),
            });
            return await response.json();
        } catch (error) {
            return { success: false, message: String(error) };
        }
    };

    const showButtonResult = ($button, result, originalText, enableAfter = true) => {
        const success = Boolean(result && result.success);
        $button
            .removeClass("interactionSuccess interactionFailure")
            .addClass(success ? "interactionSuccess" : "interactionFailure")
            .text(success ? "Sent!" : (result && result.message) || "Failed");

        if (success && result.meta && Number(result.meta.cost) > 0) {
            const $balance = $(".gemInfo strong");
            $balance.text(Math.max(0, Number($balance.text()) - Number(result.meta.cost)));
        }

        window.setTimeout(() => {
            $button.removeClass("interactionSuccess interactionFailure").text(originalText);
            $button.prop("disabled", !enableAfter);
        }, 3000);
    };

    const cleanQuantity = (value) => Math.max(0, Math.min(25, Number.parseInt(value, 10) || 0));

    const updateSpawnSummary = () => {
        let totalCount = 0;
        let totalCost = 0;
        let cooldown = 0;

        $(".spawnRow").each(function () {
            const $row = $(this);
            const quantity = cleanQuantity($row.find("input").val());
            $row.find("input").val(quantity);
            totalCount += quantity;
            totalCost += quantity * (Number($row.data("cost")) || 0);
            cooldown += quantity * (Number($row.data("cooldown")) || 0);
        });

        const $wrap = $(".spawnWrap");
        if (totalCount > 0) {
            cooldown = Math.max(Number($wrap.data("cooldown-min")) || 0, cooldown);
            cooldown = Math.min(Number($wrap.data("cooldown-max")) || cooldown, cooldown);
        }
        $("#sum_count").text(totalCount);
        $("#sum_cost").text(totalCost);
        $("#sum_cd").text(`${cooldown}s`);
        $("#spawnBtn").prop("disabled", totalCount === 0);
    };

    $(document).on("click", ".spawnRow .inc, .spawnRow .dec", function () {
        const $input = $(this).siblings("input");
        const change = $(this).hasClass("inc") ? 1 : -1;
        $input.val(cleanQuantity(cleanQuantity($input.val()) + change));
        updateSpawnSummary();
    });
    $(document).on("input change", ".spawnRow input", updateSpawnSummary);

    $("#spawnBtn").on("click", async function () {
        const $button = $(this);
        const mobs = {};
        $(".spawnRow").each(function () {
            const quantity = cleanQuantity($(this).find("input").val());
            if (quantity > 0) mobs[String($(this).data("mob"))] = quantity;
        });
        if (Object.keys(mobs).length === 0) return;

        const originalText = $button.text();
        $button.prop("disabled", true).text("Spawning...");
        const result = await postInteraction({ type: "powerSpawn", action: "spawn", mobs });
        if (result && result.success) {
            $(".spawnRow input").val(0);
            updateSpawnSummary();
        }
        showButtonResult($button, result, originalText, !(result && result.success));
    });

    const normalizeUserText = (value) => String(value || "")
        .normalize("NFKD")
        .replace(/\p{M}+/gu, "")
        .toLocaleLowerCase()
        .replace(/[013457@$]/g, (character) => ({
            "0": "o", "1": "i", "3": "e", "4": "a", "5": "s", "7": "t", "@": "a", "$": "s",
        })[character]);
    const escapeRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

    const validateBatName = ($card) => {
        const value = normalizeUserText($card.find(".batNameInput").val());
        const configured = $card.data("blocked");
        const restrictedWords = Array.isArray(configured) ? configured : [];
        const blocked = restrictedWords.some((word) => {
            const normalizedWord = normalizeUserText(word).replace(/[^\\p{L}\\p{N}]+/gu, "");
            const pattern = [...normalizedWord].map(escapeRegExp).join("[^\\p{L}\\p{N}]*");
            return pattern !== "" && new RegExp(`((^|[^\\p{L}\\p{N}])${pattern}|${pattern}($|[^\\p{L}\\p{N}]))`, "u").test(value);
        });
        const $input = $card.find(".batNameInput");
        const $hint = $card.find(".batHint");
        $input.toggleClass("invalid", blocked);
        $hint.toggleClass("visible", blocked).text(blocked ? "That name is not allowed." : "");
        $card.find(".batClaimBtn").prop("disabled", blocked);
        return !blocked;
    };

    $(document).on("input", ".batNameInput", function () {
        validateBatName($(this).closest(".cardBatClaim"));
    });

    $(".batClaimBtn").on("click", async function () {
        const $button = $(this);
        const $card = $button.closest(".cardBatClaim");
        if (!validateBatName($card)) return;

        const originalText = $button.text();
        $button.prop("disabled", true).text("Claiming...");
        const result = await postInteraction({
            type: "special",
            action: "batClaim",
            batName: String($card.find(".batNameInput").val() || "").trim(),
        });
        if (result && result.success) $card.find(".batNameInput").val("");
        showButtonResult($button, result, originalText);
    });

    updateSpawnSummary();
});
