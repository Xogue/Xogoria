class InteractCard {
  constructor(card) {
    this.card = card;
    this.dataElement = $(".cardData", card);
    this.button = $(".cardButton", card);

    this.durationElement = $(".cardDuration input", card);
    this.costElement = $(".cardCost", card);
    this.cooldownElement = $(".cardCooldown", card);
    this.apiEndpoint = "/api/xogoriaApi.php";
    this.startingBalance = $(".gemInfo strong").text();

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
    this.btnOnCooldownClass = "interactionOnCooldown";

    this.durationElement
      .on("wheel", (event) => this.wheelEvent(event))
      .on("change", (event) => this.changeEvent(event))
      .on("keyup", (event) => this.changeEvent(event));
  }

  async activate() {
    this.button.text("Sending...");
    this.button
      .prop("disabled", true)
      .removeClass(`${this.btnSuccessClass} ${this.btnFailureClass}`);

    await this.send();

    this.button.text("Sent!");
    this.button.addClass(this.btnSuccessClass);
    let newBalance = this.startingBalance - this.dataElement.data("cost");
    $(".gemInfo strong").text(newBalance);
    this.startingBalance = newBalance;

    if (this.initCooldown > 0) {
      this.startCooldown();
    } else {
      setTimeout(() => {
        this.button
          .removeClass(this.btnSuccessClass)
          .prop("disabled", false)
          .text(this.initLabel);
      }, 3000);
    }
  }

  startCooldown() {
    this.cooldownEnd = Date.now() + this.initCooldown * 1000;
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

    let $input = this.durationElement;
    let step = 1;
    let min = Number($input.attr("min"));
    let max = Number($input.attr("max"));
    let current = $input.val() === "" ? 0 : Number($input.val());

    let direction = event.originalEvent.deltaY < 0 ? 1 : -1;
    let next = Math.min(max, Math.max(min, current + direction * step));

    $input.val(next).trigger("input").trigger("change");
  }

  changeEvent(event) {
    let $input = this.durationElement;
    let currentDuration = $input.val();
    let costPer = $input.data("costper");
    let cardCooldown = this.dataElement.data("cooldown");
    let currentCost = currentDuration * costPer;

    if (currentDuration < 1) {
      currentDuration = 1;
      $input.val(1);
    }
    if (currentDuration > 30) {
      currentDuration = 30;
      $input.val(30);
    }

    if (currentDuration > 10) {
      currentCost = 10 * costPer;
      currentCost += (currentDuration - 10) * (costPer * 10);
      cardCooldown = this.initCooldown + (currentDuration - 10);
      this.cooldownElement.addClass("highlight");
      this.costElement.addClass("highlight");
      $(".extraCost", $input.closest(".card")).removeClass("uiHidden");
    } else {
      currentCost = costPer;
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
