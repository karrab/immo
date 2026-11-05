(() => {
    'use strict';

    // Confirmation via modale Bootstrap pour tout formulaire [data-confirm]
    const modalEl = document.getElementById('confirmModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        let pending = null;
        document.addEventListener('submit', (ev) => {
            const form = ev.target;
            if (!form.matches('form[data-confirm]') || form.dataset.confirmed === '1') return;
            ev.preventDefault();
            pending = form;
            document.getElementById('confirmMessage').textContent = form.dataset.confirm;
            const ok = document.getElementById('confirmOk');
            ok.className = 'btn ' + (form.dataset.confirmClass || 'btn-danger');
            ok.textContent = form.dataset.confirmLabel || 'Confirmer';
            modal.show();
        });
        document.getElementById('confirmOk').addEventListener('click', () => {
            if (!pending) return;
            pending.dataset.confirmed = '1';
            modal.hide();
            pending.requestSubmit ? pending.requestSubmit() : pending.submit();
        });
    }

    // Sélecteur "lignes par page"
    document.querySelectorAll('.js-per-page').forEach((s) => s.addEventListener('change', () => { window.location = s.value; }));

    // Listes déroulantes avec recherche
    if (window.TomSelect) {
        document.querySelectorAll('select.js-select').forEach((el) => {
            new TomSelect(el, { allowEmptyOption: true, maxOptions: 500, plugins: el.multiple ? ['remove_button'] : [] });
        });
    }

    // Validation Bootstrap côté client
    document.querySelectorAll('form.needs-validation').forEach((f) => {
        f.addEventListener('submit', (ev) => {
            if (!f.checkValidity()) { ev.preventDefault(); ev.stopPropagation(); }
            f.classList.add('was-validated');
        });
    });

    // Activation des info-bulles
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
})();
