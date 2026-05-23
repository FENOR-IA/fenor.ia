/* FENOR APP — JS mínimo */

// Dropdown
document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-dropdown]');
    document.querySelectorAll('.dropdown__menu--visivel').forEach(m => {
        if (!m.previousElementSibling?.contains(e.target)) {
            m.classList.remove('dropdown__menu--visivel');
        }
    });
    if (trigger) {
        const menu = trigger.nextElementSibling;
        if (menu) menu.classList.toggle('dropdown__menu--visivel');
    }
});

// Confirmação de exclusão
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-confirmar]');
    if (btn) {
        const msg = btn.dataset.confirmar || 'Confirmar esta ação?';
        if (!confirm(msg)) e.preventDefault();
    }
});

// Auto-fechar alertas
setTimeout(() => {
    document.querySelectorAll('.alerta[data-auto-fechar]').forEach(a => {
        a.style.opacity = '0';
        a.style.transition = 'opacity .4s';
        setTimeout(() => a.remove(), 400);
    });
}, 4000);

// Máscara telefone
document.querySelectorAll('input[data-mask="telefone"]').forEach(input => {
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

// Máscara CPF/CNPJ
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

// Busca em tempo real
const inputBusca = document.querySelector('[data-busca-tabela]');
if (inputBusca) {
    const alvo = inputBusca.dataset.buscaTabela;
    const linhas = document.querySelectorAll(`${alvo} [data-busca-linha]`);
    inputBusca.addEventListener('input', () => {
        const q = inputBusca.value.toLowerCase();
        linhas.forEach(linha => {
            linha.style.display = linha.dataset.buscaLinha.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}
