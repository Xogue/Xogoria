class InteractCard {
  constructor(card) {
    this.card = card;
    this.dataElement = $(".cardData", card);
    this.button = $(".cardButton", card);

    this.durationElement = $(".cardDuration input", card);
    this.costElement = $(".cardCost", card);
    this.cooldownElement = $(".cardCooldown", card);
    this.apiEndpoint = "/api/xogoriaApi.php";

    this.request = this.dataElement.data("request");
    this.type = this.dataElement.data("type");
    this.id = this.dataElement.data("id");
    this.label = this.dataElement.data("label");
    this.command = this.dataElement.data("command");

    this.costPer = this.dataElement.data("cost");
    this.initDuration = this.dataElement.data("duration");
    this.initCooldown = this.dataElement.data("cooldown");
    this.initLabel = this.button.text();

    this.cooldownEnd = 0;
    this.btnSuccessClass = "interactionSuccess";
    this.btnFailureClass = "interactionFailure";
    this.btnOnCooldownClass = "interactionCooldown";

    const durationInput = this.durationElement.get(0);
    if (durationInput) {
      durationInput.addEventListener("wheel", (event) => this.wheelEvent(event), { passive: false });
      this.durationElement.on("input change", () => this.syncDuration());
      this.syncDuration();
    }
  }

  async activate() {
    this.button.text("Sending...");
    this.button
      .prop("disabled", true)
      .removeClass(`${this.btnSuccessClass} ${this.btnFailureClass}`);

    const result = await this.send();
    if (!result || !result.success) {
      this.button
        .text((result && result.message) || "Failed")
        .addClass(this.btnFailureClass);
      window.setTimeout(() => {
        this.button.removeClass(this.btnFailureClass).prop("disabled", false).text(this.initLabel);
      }, 3000);
      return;
    }

    this.button.text("Sent!").addClass(this.btnSuccessClass);
    const chargedCost = Number(result.meta && result.meta.cost) || Number(this.dataElement.data("cost")) || 0;
    const currentBalance = Number($(".gemInfo strong").text()) || 0;
    const newBalance = Math.max(0, currentBalance - chargedCost);
    $(".gemInfo strong").text(newBalance);

    const cooldown = Number(result.meta && result.meta.cooldown) || Number(this.dataElement.data("cooldown")) || 0;
    if (cooldown > 0) {
      this.startCooldown(cooldown);
    } else {
      setTimeout(() => {
        this.button
          .removeClass(this.btnSuccessClass)
          .prop("disabled", false)
          .text(this.initLabel);
      }, 3000);
    }
  }

  startCooldown(seconds) {
    this.cooldownEnd = Date.now() + seconds * 1000;
    this.button.addClass(this.btnOnCooldownClass);

    this.tick();
    this.interval = setInterval(() => this.tick(), 1000);
  }

  tick() {
    const remain = Math.ceil((this.cooldownEnd - Date.now()) / 1000);
    if (remain <= 0) {
      this.button
        .removeClass(
          `${this.btnOnCooldownClass} ${this.btnSuccessClass} ${this.btnFailureClass}`,
        )
        .prop("disabled", false)
        .text(this.initLabel);

      clearInterval(this.interval);
      return;
    }
    this.button.text("Cooldown: " + remain + "s");
  }

  wheelEvent(event) {
    event.preventDefault();

    const min = Number(this.durationElement.attr("min")) || 1;
    const max = Number(this.durationElement.attr("max")) || 30;
    const current = Number(this.durationElement.val()) || min;
    const next = Math.min(max, Math.max(min, current + (event.deltaY < 0 ? 1 : -1)));

    this.durationElement.val(next).trigger("input");
  }

  syncDuration() {
    const $input = this.durationElement;
    const min = Number($input.attr("min")) || 1;
    const max = Number($input.attr("max")) || 30;
    const currentDuration = Math.min(max, Math.max(min, Number.parseInt($input.val(), 10) || min));
    const costPer = Number($input.data("costper")) || Number(this.costPer) || 0;
    let cardCooldown = this.dataElement.data("cooldown");
    let currentCost = currentDuration * costPer;

    $input.val(currentDuration);

    if (currentDuration > 10) {
      currentCost = 10 * costPer;
      currentCost += (currentDuration - 10) * (costPer * 10);
      cardCooldown = this.initCooldown + (currentDuration - 10);
      this.cooldownElement.addClass("highlight");
      this.costElement.addClass("highlight");
      $(".extraCost", $input.closest(".card")).removeClass("uiHidden");
    } else {
      currentCost = currentDuration * costPer;
      cardCooldown = this.initCooldown;
      this.cooldownElement.removeClass("highlight");
      this.costElement.removeClass("highlight");
      $(".extraCost", $input.closest(".card")).addClass("uiHidden");
    }

    this.cooldownElement.text("Cooldown: " + cardCooldown + "s");
    this.costElement.text("Cost: " + currentCost + " AGs");

    this.dataElement.data("cooldown", cardCooldown);
    this.dataElement.data("cost", currentCost);
    this.dataElement.data("duration", currentDuration);
  }

  async send() {
    const interactPayload = {
      request: this.request,
      type: this.type,
      action: this.id,
      duration: this.dataElement.data("duration"),
      cooldown: this.dataElement.data("cooldown"),
    };

    try {
      const res = await fetch(this.apiEndpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify(interactPayload),
      });
      return await res.json();
    } catch (e) {
      return { ok: false, error: String(e) };
    }
  }
}
