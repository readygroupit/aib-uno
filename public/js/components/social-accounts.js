import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { toast } from '../accounts-ui.js';

// Configurazione, "Pagine e profili social": ogni pagina Facebook e profilo
// Instagram dell'account Meta collegato, con il cliente a cui va (vedi
// SetupController::socialAction). Si salva appena si sceglie.

async function post(action, body) {
    const response = await fetch(action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '' },
        body: JSON.stringify(body),
    });
    try {
        return await response.json();
    } catch {
        return { ok: false, message: 'Non riuscito: riprova.' };
    }
}

function renderSocialAccounts(data) {
    const root = el('div', { className: 'acc-board social-accounts' });
    const draw = (current) => {
        root.textContent = '';
        const refresh = el('button', { type: 'button', className: 'btn btn--secondary' }, [icon('arrows-rotate'), 'Rileggi da Facebook']);
        refresh.addEventListener('click', async () => {
            refresh.disabled = true;
            const result = await post(current.action, { refresh: 1 });
            toast(result.message, result.ok ? 'success' : 'error');
            if (result.ok) draw(result.component);
            refresh.disabled = false;
        });
        const rows = current.accounts.map((a) => {
            const select = el('select', { className: 'field__input social-accounts__select' }, [
                el('option', { value: '' }, ['Nessun cliente']),
                ...current.customers.map((c) => el('option', { value: String(c.id), selected: a.customerId === c.id ? 'selected' : null }, [c.name])),
            ]);
            select.addEventListener('change', async () => {
                const result = await post(current.action, { account: a.id, customer: select.value });
                toast(result.message, result.ok ? 'success' : 'error');
            });
            return el('div', { className: 'social-accounts__row' + (a.customerId ? '' : ' is-free') }, [
                el('span', { className: `social-accounts__network social-accounts__network--${a.network}` }, [a.network === 'instagram' ? 'Instagram' : 'Facebook']),
                el('span', { className: 'social-accounts__name' }, [a.label.replace(/^(Instagram|Facebook) /, '')]),
                select,
            ]);
        });
        root.append(
            el('div', { className: 'claim-table__header' }, [
                el('h1', { className: 'claim-table__title' }, [current.title]),
                el('div', { className: 'claim-table__actions' }, [refresh]),
            ]),
            el('section', { className: 'card acc-section' }, [
                el('p', { className: 'acc-muted social-accounts__intro' }, [current.intro]),
                rows.length ? el('div', { className: 'social-accounts__list' }, rows) : el('p', { className: 'acc-empty' }, ['Nessuna pagina trovata: controlla che l\'account gestisca le pagine dei clienti, poi "Rileggi da Facebook".']),
            ]),
        );
    };
    draw(data);

    return root;
}

registerComponent('social-accounts', renderSocialAccounts);
