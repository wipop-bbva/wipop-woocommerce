document.addEventListener('DOMContentLoaded', () => {
  const ui = window.wipopAdminVerify || {};
  const notify = (key, fallback) => window.alert(ui[key] || fallback);
  const setTemporaryButtonLabel = (button, nextLabel) => {
    if (!button) {
      return;
    }

    const defaultLabel = button.dataset.wipopDefaultLabel || button.textContent;
    button.dataset.wipopDefaultLabel = defaultLabel;
    button.textContent = nextLabel;
    button.disabled = true;

    window.setTimeout(() => {
      button.textContent = button.dataset.wipopDefaultLabel || defaultLabel;
      button.disabled = false;
    }, 1200);
  };
  const manualCopy = (text) => {
    const promptMessage = ui.manualCopyPrompt || 'Copy this value manually (Ctrl/Cmd + C).';

    if (typeof window.prompt === 'function') {
      window.prompt(promptMessage, text);
      return;
    }

    notify('copyErrorMessage', 'Copy failed.');
  };

  const writeClipboard = async (text) => {
    if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
      return false;
    }

    await navigator.clipboard.writeText(text);
    return true;
  };

  const togglePassword = (icon) => {
    const targetId = icon.getAttribute('data-target');
    const input = document.getElementById(targetId);
    if (!input) {
      return;
    }

    const isPassword = input.getAttribute('type') === 'password';
    input.setAttribute('type', isPassword ? 'text' : 'password');
    icon.classList.toggle('dashicons-visibility');
    icon.classList.toggle('dashicons-hidden');
  };

  document.querySelectorAll('.wipop-toggle-password').forEach((icon) => {
    icon.addEventListener('click', () => togglePassword(icon));
  });

  const copyInputValue = async (inputId, button) => {
    const input = document.getElementById(inputId);
    if (!input || !input.value || input.value === '-') {
      return;
    }

    try {
      const copied = await writeClipboard(input.value);
      if (!copied) {
        manualCopy(input.value);
        return;
      }

      setTemporaryButtonLabel(button, ui.copyDoneLabel || 'Copied');
    } catch (error) {
      manualCopy(input.value);
    }
  };

  document.querySelectorAll('[data-wipop-copy-target]').forEach((button) => {
    button.addEventListener('click', () => {
      copyInputValue(button.getAttribute('data-wipop-copy-target'), button);
    });
  });

  const regenerateForm = document.getElementById('wipop-webhook-regenerate-form');
  if (regenerateForm) {
    regenerateForm.addEventListener('submit', (event) => {
      const confirmMessage = ui.regenerateConfirmMessage || 'Continue?';
      if (!window.confirm(confirmMessage)) {
        event.preventDefault();
      }
    });
  }

  const verifyBtn = document.getElementById('wipop-admin-verify-button');
  const settingsForm = document.querySelector('.admin-page-wipop-settings > form');

  if (!verifyBtn || !settingsForm || !ui.nonce || !ui.ajaxUrl) {
    return;
  }

  const serializeSettings = () => {
    const entries = [];
    const formData = new FormData(settingsForm);

    formData.forEach((value, key) => {
      if (key.startsWith('wipop_settings[')) {
        entries.push([key, value.toString()]);
      }
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

  verifyBtn.addEventListener('click', async () => {
    verifyBtn.disabled = true;

    const body = new URLSearchParams();
    body.append('action', 'wipop_verify_credentials');
    body.append('_wpnonce', ui.nonce);

    try {
      const response = await fetch(ui.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8',
        },
        body: body.toString(),
      });

      const data = await response.json();
      if (!data?.success) {
        throw new Error(data?.data?.message || 'API_ERROR');
      }

      notify('successMessage', 'OK');
    } catch (error) {
      console.error('Verify error:', error);
      notify('errorMessage', 'Verification failed.');
    } finally {
      verifyBtn.disabled = false;
    }
  });
});
