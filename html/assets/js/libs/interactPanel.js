$(document).ready(function () {
    let debugEnabled = false;
    let debugRequestSequence = 0;
    let pendingUiContext = null;
    const nativeFetch = window.fetch.bind(window);

    const formatDebugValue = (value) => {
        if (value === undefined) return "undefined";
        if (value === null) return "null";
        if (typeof value === "string") {
            try {
                return JSON.stringify(JSON.parse(value), null, 2);
            } catch (_) {
                return value;
            }
        }
        try {
            return JSON.stringify(value, null, 2);
        } catch (_) {
            return String(value);
        }
    };

    const appendDebug = (kind, value, options = {}) => {
        if (!debugEnabled) return;
        const log = document.querySelector(".debugLog");
        if (!log) return;

        if (options.separator) {
            const separator = document.createElement("hr");
            separator.className = "debugSeparator";
            log.appendChild(separator);
        }

        const entry = document.createElement("div");
        entry.className = `debugEntry${options.error ? " debugEntryError" : ""}`;
        const timestamp = new Date().toLocaleTimeString([], { hour12: false, fractionalSecondDigits: 3 });
        const heading = document.createElement("span");
        heading.className = "debugEntryKind";
        heading.textContent = `[${timestamp}] ${kind}`;
        entry.appendChild(heading);
        if (value !== undefined && value !== "") {
            entry.appendChild(document.createTextNode(`\n${formatDebugValue(value)}`));
        }
        log.appendChild(entry);
        log.scrollTop = log.scrollHeight;
    };

    const requestLabel = (body, method, url) => {
        if (body && typeof body === "object") {
            return [body.request, body.type, body.action].filter(Boolean).join(" / ") || `${method} ${url}`;
        }
        return `${method} ${url}`;
    };

    window.fetch = async (input, init = {}) => {
        if (!debugEnabled) return nativeFetch(input, init);

        const url = typeof input === "string" ? input : input.url;
        const method = String(init.method || (typeof input !== "string" && input.method) || "GET").toUpperCase();
        const debugHeaders = new Headers(init.headers || (typeof input !== "string" ? input.headers : undefined));
        debugHeaders.set("X-Interact-Debug", "1");
        const debugInit = { ...init, headers: debugHeaders };
        let requestBody = init.body;
        if (typeof requestBody === "string") {
            try { requestBody = JSON.parse(requestBody); } catch (_) { /* Keep the original text. */ }
        }

        const requestId = ++debugRequestSequence;
        const startedAt = performance.now();
        appendDebug(`ACTION #${requestId}: ${requestLabel(requestBody, method, url)}`, pendingUiContext, { separator: true });
        pendingUiContext = null;
        appendDebug(`REQUEST #${requestId}`, {
            url,
            method,
            credentials: init.credentials || "default",
            headers: Object.fromEntries(debugHeaders.entries()),
            body: requestBody ?? null,
        });

        try {
            const response = await nativeFetch(input, debugInit);
            appendDebug("RESPONSE HEADERS", {
                requestId,
                status: response.status,
                statusText: response.statusText,
                ok: response.ok,
                elapsedMs: Math.round((performance.now() - startedAt) * 100) / 100,
                headers: Object.fromEntries(response.headers.entries()),
            }, { error: !response.ok });

            response.clone().text()
                .then((body) => appendDebug(`RESPONSE BODY #${requestId}`, body || "(empty response)", { error: !response.ok }))
                .catch((error) => appendDebug(`RESPONSE BODY READ FAILED #${requestId}`, error.stack || String(error), { error: true }));
            return response;
        } catch (error) {
            appendDebug("FETCH FAILED", {
                requestId,
                elapsedMs: Math.round((performance.now() - startedAt) * 100) / 100,
                error: error.stack || String(error),
            }, { error: true });
            throw error;
        }
    };

    document.addEventListener("click", (event) => {
        if (!debugEnabled) return;
        const button = event.target.closest(".cardButton, .setGame");
        if (!button) return;
        const card = button.closest(".card");
        const cardData = card ? card.querySelector(".cardData") : null;
        pendingUiContext = {
            button: button.textContent.trim(),
            cardLabel: card?.querySelector(".cardLabel")?.textContent.trim() || null,
            interaction: cardData ? { ...cardData.dataset } : null,
            selectedGame: button.matches(".setGame") ? $("select[name='gameSelector']").val() : null,
            spawnSummary: button.matches("#spawnBtn") ? {
                totalMobs: $("#sum_count").text(),
                totalCost: $("#sum_cost").text(),
                cooldown: $("#sum_cd").text(),
            } : null,
            batName: button.matches(".batClaimBtn") ? $(".batNameInput").val() : null,
        };
        const capturedContext = pendingUiContext;
        window.setTimeout(() => {
            if (pendingUiContext === capturedContext) pendingUiContext = null;
        }, 0);
    }, true);

    $(".debugToggle").on("click", function () {
        debugEnabled = !debugEnabled;
        $(this)
            .toggleClass("active", debugEnabled)
            .attr("aria-pressed", String(debugEnabled))
            .text(debugEnabled ? "Disable Debug Mode" : "Enable Debug Mode");
        $("#interactDebugPanel").toggleClass("uiHidden", !debugEnabled);
        if (debugEnabled) {
            appendDebug("DEBUG MODE ENABLED", {
                page: window.location.href,
                userAgent: navigator.userAgent,
                viewport: `${window.innerWidth}x${window.innerHeight}`,
            });
        }
    });

    $(".debugClear").on("click", function () {
        $(".debugLog").empty().focus();
    });

    $(".debugCopy").on("click", async function () {
        const $button = $(this);
        const originalText = $button.text();
        const copiedText = Array.from(document.querySelector(".debugLog").children)
            .map((entry) => entry.matches(".debugSeparator") ? "========================================" : entry.innerText)
            .join("\n\n");
        try {
            await navigator.clipboard.writeText(copiedText);
            $button.text("Copied!");
        } catch (error) {
            $button.text("Copy failed");
            appendDebug("COPY FAILED", error.stack || String(error), { error: true });
        }
        window.setTimeout(() => $button.text(originalText), 1500);
    });

    window.addEventListener("error", (event) => {
        appendDebug("UNHANDLED PAGE ERROR", {
            message: event.message,
            source: event.filename,
            line: event.lineno,
            column: event.colno,
            stack: event.error?.stack || null,
        }, { separator: true, error: true });
    });

    window.addEventListener("unhandledrejection", (event) => {
        appendDebug("UNHANDLED PROMISE REJECTION", event.reason?.stack || event.reason, { separator: true, error: true });
    });

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
        $(this).text(
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
        appendDebug("UI RESULT", {
            success,
            message: result && result.message,
            code: result && result.code,
            meta: result && result.meta,
        }, { error: !success });
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
