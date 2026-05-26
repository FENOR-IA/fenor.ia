/* FENOR APP — Minimal JS */

// Dropdown toggle
document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-dropdown]');
    document.querySelectorAll('.dropdown__menu--visible').forEach(m => {
        if (!m.previousElementSibling?.contains(e.target)) {
            m.classList.remove('dropdown__menu--visible');
        }
    });
    if (trigger) {
        const menu = trigger.nextElementSibling;
        if (menu) menu.classList.toggle('dropdown__menu--visible');
    }
});

// Delete / action confirmation
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-confirm]');
    if (btn) {
        const msg = btn.dataset.confirm || 'Confirm this action?';
        if (!confirm(msg)) e.preventDefault();
    }
});

// Auto-close alerts
setTimeout(() => {
    document.querySelectorAll('.alert[data-auto-close]').forEach(a => {
        a.style.opacity = '0';
        a.style.transition = 'opacity .4s';
        setTimeout(() => a.remove(), 400);
    });
}, 4000);

// Phone mask (Brazilian format)
document.querySelectorAll('input[data-mask="phone"]').forEach(input => {
    input.addEventListener('input', () => {
        let v = input.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) {
            v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
        } else if (v.length > 6) {
            v = v.replace(/^(\d{2})(\d{4})(\d*)$/, '($1) $2-$3');
        } else if (v.length > 2) {
            v = v.replace(/^(\d{2})(\d*)$/, '($1) $2');
        }
        input.value = v;
    });
});

// CPF/CNPJ mask (Brazilian tax IDs)
document.querySelectorAll('input[data-mask="cpfcnpj"]').forEach(input => {
    input.addEventListener('input', () => {
        let v = input.value.replace(/\D/g, '');
        if (v.length <= 11) {
            v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2}).*/, '$1.$2.$3-$4');
        } else {
            v = v.slice(0, 14).replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2}).*/, '$1.$2.$3/$4-$5');
        }
        input.value = v;
    });
});

// Real-time search / filter
const searchInput = document.querySelector('[data-search-table]');
if (searchInput) {
    const target = searchInput.dataset.searchTable;
    const rows   = document.querySelectorAll(`${target} [data-search-row]`);
    searchInput.addEventListener('input', () => {
        const q = searchInput.value.toLowerCase();
        rows.forEach(row => {
            row.style.display = row.dataset.searchRow.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}
