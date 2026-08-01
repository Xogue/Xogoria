class Alert {
  constructor(type, message) {
    this.type = type;
    this.message = message;

    this.show();
  }

  show() {
    $(".uiInvisible").css("opacity", "1");
    $(".alert").addClass(this.type);
    $(".alert").html("<p>" + this.message + "</p>");

    setTimeout(() => {
      $(".alert").removeClass(this.type);
      $(".uiInvisible").css("opacity", "0");
      $(".alert").addClass("uiInvisible");
    }, 4000);
  }
}
