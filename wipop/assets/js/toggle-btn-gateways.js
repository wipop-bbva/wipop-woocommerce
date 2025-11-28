(function () {
  if (!window.wipopToggle) return;

  function runWhenIdle(callback) {
    if ('requestIdleCallback' in window) {
      requestIdleCallback(callback);
    } else {
      setTimeout(() => callback({ didTimeout: false, timeRemaining: () => 0 }), 1);
    }
  }

  const gateways = [
    { id: 'wipop_bizum_gateway', action: 'wipop_toggle_bizum' },
    { id: 'wipop_card_gateway', action: 'wipop_toggle_card' },
    { id: 'wipop_gpay_gateway', action: 'wipop_toggle_gpay' }
  ];
  const gatewayMap = Object.fromEntries(gateways.map(cfg => [cfg.id, cfg]));

  const containerSelector = '#mainform';
  let observer = null;

  function injectToggle(wrapper, cfg) {
    if (!wrapper || wrapper.dataset.wipopInjected || wrapper.querySelector('.wipop-toggle-button')) return;
    wrapper.dataset.wipopInjected = '1';

    const defaultActions = wrapper.querySelectorAll(
      '.woocommerce-list__item-buttons__actions > a.components-button, .woocommerce-list__item-buttons__actions > button.components-button'
    );
    defaultActions.forEach((button) => button.remove());

    const ellipsisMenu = wrapper.querySelector('.woocommerce-list__item-after');
    if (ellipsisMenu) {
      ellipsisMenu.remove();
    }

    const isActive = !!wrapper.querySelector('.woocommerce-status-badge--success');
    const params = new URLSearchParams({
      action: cfg.action,
      _wpnonce: wipopToggle.nonce,
      wipop_gateway_action: isActive ? 'off' : 'on'
    });

    const btn = document.createElement('a');
    btn.className = 'button wipop-toggle-button ' + (isActive ? 'button-secondary wipop-danger' : 'button-primary');
    btn.href = `/wp-admin/admin-post.php?${params.toString()}`;
    btn.textContent = isActive
      ? (wipopToggle.i18n.deactivate || 'Deactivate')
      : (wipopToggle.i18n.activate || 'Activate');

    if (isActive) {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const gatewayLabel = extractGatewayLabel(wrapper) || wipopToggle.i18n.default_label;
        const message = sprintf(wipopToggle.i18n.confirm_deactivate, gatewayLabel);
        
        showConfirmationModal(message, {
          confirmText: wipopToggle.i18n.confirm,
          cancelText: wipopToggle.i18n.cancel,
          onConfirm: () => {
            window.location.href = btn.href;
          },
          onCancel: () => {
            btn.blur();
          }
        });
      });
    }

    const container = wrapper.querySelector('.woocommerce-list__item-buttons__actions') || wrapper;
    container.appendChild(btn);
  }

  function injectAll() {
    gateways.forEach(cfg => {
      const wrapper = document.getElementById(cfg.id);
      if (wrapper) injectToggle(wrapper, cfg);
    });
  }

  function handleMutation(node) {
    if (!(node instanceof HTMLElement)) return;

    const hasGateway = node.querySelector('[id^="wipop_"]') || gatewayMap[node.id];
    if (!hasGateway) return;

    node.querySelectorAll?.('[id^="wipop_"]').forEach(el => {
      const cfg = gatewayMap[el.id];
      if (cfg) injectToggle(el, cfg);
    });

    const cfgSelf = gatewayMap[node.id];
    if (cfgSelf) injectToggle(node, cfgSelf);
  }

  function startObserver() {
    const container = document.querySelector(containerSelector);
    if (!container) return;

    let pendingMutations = [];
    let processing = false;

    function processMutations() {
      if (processing) return;
      processing = true;
      runWhenIdle(() => {
        pendingMutations.forEach(mutation => {
          mutation.addedNodes.forEach(handleMutation);
        });
        pendingMutations = [];
        processing = false;
      });
    }

    observer = new MutationObserver(mutations => {
      pendingMutations.push(...mutations);
      processMutations();
    });

    runWhenIdle(() => {
      observer.observe(container, { childList: true, subtree: true });
    });

    runWhenIdle(injectAll);

    window.addEventListener('beforeunload', () => {
      if (observer) {
        observer.disconnect();
      }
    });
  }

  const isPaymentsTab = new URLSearchParams(window.location.search).get('tab') === 'checkout';
  if (isPaymentsTab) {
    document.addEventListener('DOMContentLoaded', startObserver);
  }
})();
  function extractGatewayLabel(wrapper) {
    const title = wrapper.querySelector('.woocommerce-list__item-title');
    if (!title) {
      return '';
    }

    return Array.from(title.childNodes)
      .filter((node) => node.nodeType === Node.TEXT_NODE)
      .map((node) => node.textContent || '')
      .join('')
      .trim();
  }
