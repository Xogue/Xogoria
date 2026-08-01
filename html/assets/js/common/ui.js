$(document).ready(function () {
    var currentPage = window.location.pathname.split("/").pop().split(".").shift();
    currentPage = currentPage.charAt(0).toUpperCase() + currentPage.slice(1);
    if (currentPage == "") {
        currentPage = "About";
    }
    const anchorCount = $(".js" + currentPage + "Anchor1A").length;
    const streamStatusBadge = document.getElementById("streamStatusBadge");
    const liveBanner = document.getElementById("liveBanner");

    if (streamStatusBadge) {
        let periodCount = 1;
        const checkingText = "Checking if Xogue is live";
        const endpoint = streamStatusBadge.dataset.streamStatusEndpoint;
        const statusOverride = new URLSearchParams(window.location.search).get("streamStatus");
        const applyStreamStatus = function (isLive) {
            window.clearInterval(statusTimer);
            streamStatusBadge.textContent = isLive ? "Xogue is live." : "Xogue is offline.";

            if (liveBanner) {
                liveBanner.classList.toggle("uiHidden", !isLive);
                liveBanner.setAttribute("aria-hidden", isLive ? "false" : "true");
            }
        };

        streamStatusBadge.textContent = checkingText + ".";
        const statusTimer = window.setInterval(function () {
            periodCount = periodCount === 3 ? 1 : periodCount + 1;
            streamStatusBadge.textContent = checkingText + ".".repeat(periodCount);
        }, 450);

        if (statusOverride === "live" || statusOverride === "offline") {
            applyStreamStatus(statusOverride === "live");
        } else {
            const requestStreamStatus = function () {
                fetch(endpoint, { cache: "no-store" })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error("Stream status request failed");
                        }

                        return response.json();
                    })
                    .then(function (data) {
                        if (data && data.status === "checking") {
                            window.setTimeout(requestStreamStatus, 1000);
                            return;
                        }

                        const isLive = data && data.status === "live";
                        applyStreamStatus(isLive);
                    })
                    .catch(function () {
                        window.clearInterval(statusTimer);
                        streamStatusBadge.textContent = "Could not check if Xogue is live.";
                    });
            };

            requestStreamStatus();
        }
    }

    $(".uiVeryShortChain, .uiShortChain, .uiMediumChain, .uiLongChain, .uiVeryLongChain").each(
        function () {
            const styles = getComputedStyle(this);
            const chainLengthMin = parseInt(styles.getPropertyValue("--chain-min-length")); // Minimum chain length in pixels
            const chainLengthMax = parseInt(styles.getPropertyValue("--chain-max-length")); // Maximum chain length in pixels
            const randomLength =
                Math.floor(Math.random() * (chainLengthMax - chainLengthMin + 1)) + chainLengthMin;
            this.style.setProperty("--chain-length", randomLength + "px");
        },
    );

    for (let i = 1; i <= anchorCount; i++) {
        const elementA = document.getElementsByClassName(
            "js" + currentPage + "Anchor" + i + "A",
        )[0];
        const elementB = document.getElementsByClassName(
            "js" + currentPage + "Anchor" + i + "B",
        )[0];
        if (!elementA || !elementB) continue;
        const aYPos = elementA.getBoundingClientRect();
        const bYPos = elementB.getBoundingClientRect();
        const distance = bYPos.top - aYPos.bottom;

        $(".jsChainLength" + i).each(function () {
            this.style.setProperty("--chain-length", distance + "px");
        });
    }

    $(".jsPillLink").on("click", function () {
        $(".jsPillLink").removeClass("active");
        $(".uiSectionBody").addClass("uiHidden");
        $(this).addClass("active");
        $(".title").text($(this).text());

        var page = $(this).data("page");
        if (page == "interacting") {
            $(".jsGameShelf").removeClass("uiHidden");
            $(".uiVerticalPlate").css("bottom", "53px");
            $(".jsGameLink").first().click();
        } else {
            $(".jsGameShelf").addClass("uiHidden");
            $(".uiVerticalPlate").css("bottom", "-9px");
        }
        $(".uiSectionBody[data-page='" + page + "']").removeClass("uiHidden");
    });
});
