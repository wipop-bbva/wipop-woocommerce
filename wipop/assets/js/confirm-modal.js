async function insertModalTemplate(url) {
    const res = await fetch(url);
    return res.text();
}

async function showConfirmationModal(message, {
    confirmText = 'Confirmar',
    cancelText = 'Cancelar',
    onConfirm = () => {},
    onCancel = () => {}
} = {}) {
    const existing = document.querySelector('.wipop-confirmation-modal');
    if (existing) existing.remove();

    try {
        const html = await insertModalTemplate(wipopToggle.modalTemplateUrl);
        document.body.insertAdjacentHTML('beforeend', html);

        const modal = document.querySelector('.wipop-confirmation-modal');
        const msgEl = document.getElementById('wipop-confirm-msg');
        if (msgEl) msgEl.textContent = message;

        modal.querySelector('.wipop-confirm').textContent = confirmText;
        modal.querySelector('.wipop-cancel').textContent = cancelText;

        function closeModal() {
            modal.classList.add('wipop-fade-out-modal');
            modal.addEventListener('transitionend', () => modal.remove(), { once: true });
        }

        modal.querySelector('.wipop-confirm').addEventListener('click', () => {
            if (typeof onConfirm === 'function') onConfirm();
            closeModal();
        });

        modal.querySelector('.wipop-cancel').addEventListener('click', () => {
            if (typeof onCancel === 'function') onCancel();
            closeModal();
        });

        modal.querySelector('.wipop-close').addEventListener('click', closeModal);

        document.addEventListener('keydown', function escHandler(e) {
            if (e.key === 'Escape' && document.body.contains(modal)) {
                if (typeof onCancel === 'function') onCancel();
                closeModal();
                document.removeEventListener('keydown', escHandler);
            }
        });

    } catch (error) {
        showToastError(wipopToggle.i18n.error_message);
        if (typeof onCancel === 'function') onCancel();
        console.error('Error al mostrar el modal de confirmación:', error);
    }
}

function showToastError(message) {
    const toast = document.createElement('div');
    toast.className = 'wipop-toast-error';
    toast.innerHTML = `
        <div class="wipop-toast-inner">
            <strong>${message}</strong>
        </div>
    `;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('wipop-fade-out-toast');
        toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    }, 5000);
}
