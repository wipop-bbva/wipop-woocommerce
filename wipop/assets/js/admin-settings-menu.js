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

  if (!verifyBtn || !submitBtn || !window.wipopAdminVerify) {
    return;
  }

  const STORAGE_KEY = 'wipop_admin_verified';

  const isVerified = localStorage.getItem(STORAGE_KEY) === 'true';
  submitBtn.disabled = !isVerified;

  verifyBtn.addEventListener('click', () => {
    verifyBtn.disabled = true;

    const body = new URLSearchParams({
      action: 'wipop_verify_credentials',
      _wpnonce: wipopAdminVerify.nonce
    });

    fetch(wipopAdminVerify.ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
      },
      body: body.toString()
    })
      .then(response => response.json())
      .then(data => {
        if (data?.success) {
          localStorage.setItem(STORAGE_KEY, 'true');
          submitBtn.disabled = false;
          window.alert(wipopAdminVerify.successMessage);
        } else {
          throw new Error(data?.data?.message || wipopAdminVerify.errorMessage);
        }
      })
      .catch(error => {
        window.alert(error.message || wipopAdminVerify.errorMessage);
      })
      .finally(() => {
        verifyBtn.disabled = false;
      });
  });
});
