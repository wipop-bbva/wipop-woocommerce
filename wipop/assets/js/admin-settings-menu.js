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

document.addEventListener('DOMContentLoaded', () => {
  const verifyBtn = document.getElementById('wipop-admin-verify-button');
  const submitBtn = document.getElementById('wipop-admin-save-button');

  const STORAGE_KEY = 'wipop_admin_verified';

  const isVerified = localStorage.getItem(STORAGE_KEY) === 'true';
  submitBtn.disabled = !isVerified;

  verifyBtn.addEventListener('click', () => {
      // TODO: Implement real verification logic

    localStorage.setItem(STORAGE_KEY, 'true');
    submitBtn.disabled = false;
  });
});
