// Utilidades Modales Globales AURA
function showAuraModal(title, message, type = 'info') {
    const modalEl = document.getElementById('auraGlobalModal');
    if (!modalEl) { alert(title + ": " + message); return; }

    document.getElementById('auraGlobalModalTitle').innerHTML = title;
    document.getElementById('auraGlobalModalBody').innerHTML = message;

    const iconContainer = document.getElementById('auraGlobalModalIcon');
    const iconBi = document.getElementById('auraGlobalModalIconBi');

    if (iconContainer && iconBi) {
        iconContainer.className = 'aura-icon-container ' + type;
        if (type === 'success') iconBi.className = 'bi bi-check-lg';
        else if (type === 'danger') iconBi.className = 'bi bi-x-lg';
        else if (type === 'warning') iconBi.className = 'bi bi-exclamation-triangle';
        else iconBi.className = 'bi bi-info-lg';
    }

    const m = new bootstrap.Modal(modalEl);
    m.show();
}

let formToSubmit = null;
function showAuraConfirm(title, message, formElementOrCallback) {
    const modalEl = document.getElementById('auraConfirmModal');
    if (!modalEl) {
        if (confirm(title + "\n\n" + message)) {
            if (typeof formElementOrCallback === 'function') formElementOrCallback();
            else formElementOrCallback.submit();
        }
        return;
    }

    document.getElementById('auraConfirmModalTitle').innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> ${title}`;
    document.getElementById('auraConfirmModalBody').innerHTML = message;

    formToSubmit = formElementOrCallback;

    // Asignar el listener al botón de confirmar solo una vez (limpiando previos clonando el obj)
    const btn = document.getElementById('auraConfirmModalBtn');
    const newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);

    newBtn.addEventListener('click', () => {
        const m = bootstrap.Modal.getInstance(modalEl);
        m.hide();
        if (typeof formToSubmit === 'function') formToSubmit();
        else if (formToSubmit) formToSubmit.submit();
    });

    const m = new bootstrap.Modal(modalEl);
    m.show();
}
