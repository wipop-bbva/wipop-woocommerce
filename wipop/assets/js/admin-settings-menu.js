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
  const settingsForm = document.querySelector('.admin-page-wipop-settings form');

  if (!verifyBtn || !settingsForm || !window.wipopAdminVerify) {
    return;
  }

  const serializeSettings = () => {
    const formData = new FormData(settingsForm);
    const entries = [];

    formData.forEach((value, key) => {
      if (!key.startsWith('wipop_settings[')) {
        return;
      }

      entries.push([key, value.toString()]);
    });

    entries.sort((a, b) => {
      if (a[0] === b[0]) {
        return a[1].localeCompare(b[1]);
      }

      return a[0].localeCompare(b[0]);
    });

    return JSON.stringify(entries);
  };

  const savedSnapshot = serializeSettings();

  const syncVerifyState = () => {
    verifyBtn.disabled = serializeSettings() !== savedSnapshot;
  };

  settingsForm.addEventListener('input', syncVerifyState);
  settingsForm.addEventListener('change', syncVerifyState);
  syncVerifyState();

  verifyBtn.addEventListener('click', () => {
    verifyBtn.disabled = true;

    const body = new URLSearchParams();
    body.append('action', 'wipop_verify_credentials');
    body.append('_wpnonce', wipopAdminVerify.nonce);

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
          window.alert(wipopAdminVerify.successMessage);
        } else {
          throw new Error(data?.data?.message || 'API_ERROR');
        }
      })
      .catch(error => {
        console.error('Verify error: ', error);
        window.alert(wipopAdminVerify.errorMessage);
      })
      .finally(() => {
        verifyBtn.disabled = false;
      });
  });
});
