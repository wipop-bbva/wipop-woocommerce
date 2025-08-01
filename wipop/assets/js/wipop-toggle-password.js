document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".wipop-toggle-password").forEach(function (icon) {
    icon.addEventListener("click", function () {
      const targetId = this.getAttribute("data-target");
      const passwordField = document.getElementById(targetId);
      const isPassword = passwordField.getAttribute("type") === "password";

      passwordField.setAttribute("type", isPassword ? "text" : "password");
      this.classList.toggle("dashicons-visibility");
      this.classList.toggle("dashicons-hidden");
    });
  });
});

