import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { toast } from '../accounts-ui.js';

// Configurazione, "Logo dell'azienda": le due versioni del logo (normale e
// per sfondi scuri), ognuna con l'anteprima sul suo sfondo, "Carica" e
// "Togli". Il file parte appena scelto (vedi SetupController::logoAction).

async function send(action, body) {
    const response = await fetch(action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '' },
        body,
    });
    try {
        return await response.json();
    } catch {
        return { ok: false, error: 'Qualcosa non ha funzionato: riprova.' };
    }
}

function logoCard(data, logo, replace) {
    const error = el('p', { className: 'form__message form__message--error brand-logo__error', role: 'alert', hidden: '' });
    const input = el('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp', className: 'brand-logo__file' });
    const pick = el('button', { type: 'button', className: 'btn btn--primary btn--small' }, [icon('image'), logo.url ? 'Sostituisci' : 'Carica']);

    const fail = (message) => {
        error.textContent = message;
        error.hidden = false;
        pick.disabled = false;
    };

    pick.addEventListener('click', () => input.click());
    input.addEventListener('change', async () => {
        if (!input.files.length) return;
        error.hidden = true;
        pick.disabled = true;
        const body = new FormData();
        body.append('kind', logo.kind);
        body.append('file', input.files[0]);
        const result = await send(data.action, body);
        if (!result.ok) return fail(result.error || 'Caricamento non riuscito.');
        toast('Logo caricato.');
        refreshShellLogo(result.component);
        replace(result.component);
    });

    const remove = logo.url
        ? el('button', { type: 'button', className: 'btn btn--ghost btn--small' }, ['Togli'])
        : null;
    if (remove) {
        let armed = false;
        remove.addEventListener('click', async () => {
            if (!armed) {
                armed = true;
                remove.textContent = 'Conferma: togli il logo';
                return;
            }
            const body = new FormData();
            body.append('kind', logo.kind);
            body.append('remove', '1');
            const result = await send(data.action, body);
            if (!result.ok) return fail(result.error || 'Non riuscito.');
            toast('Logo tolto.');
            refreshShellLogo(result.component);
            replace(result.component);
        });
    }

    return el('section', { className: 'card acc-section brand-logo' }, [
        el('h2', { className: 'card__title' }, [logo.label]),
        el('p', { className: 'acc-muted brand-logo__help' }, [logo.help]),
        logo.usedIn && logo.usedIn.length ? el('div', { className: 'brand-logo__used' }, [
            el('span', { className: 'brand-logo__used-title' }, ['Dove compare']),
            el('ul', { className: 'brand-logo__used-list' }, logo.usedIn.map((text) => el('li', {}, [text]))),
        ]) : null,
        el('div', { className: 'brand-logo__preview' + (logo.dark ? ' brand-logo__preview--dark' : '') }, [
            logo.url
                ? el('img', { src: logo.url, alt: logo.label })
                : el('span', { className: 'brand-logo__empty' }, ['Nessun logo caricato']),
        ]),
        error,
        el('div', { className: 'brand-logo__actions' }, [pick, remove, input]),
    ]);
}

// Il logo in cima alla chat (hero.js) cambia subito, senza ricaricare.
function refreshShellLogo(current) {
    const normal = current.logos.find((l) => l.kind === 'logo');
    const img = document.querySelector('.hero__brand img');
    if (img && normal && normal.url) img.src = normal.url;
    if (img && normal && !normal.url) img.closest('.hero__brand').remove();
}

function renderBrandLogos(data) {
    const root = el('div', { className: 'acc-board brand-logos' });
    const draw = (current) => {
        root.textContent = '';
        root.append(
            el('div', { className: 'claim-table__header' }, [el('h1', { className: 'claim-table__title' }, [current.title])]),
            el('div', { className: 'brand-logos__grid' }, current.logos.map((logo) => logoCard(current, logo, draw))),
            el('p', { className: 'acc-muted brand-logos__note' }, [current.note]),
        );
    };
    draw(data);

    return root;
}

registerComponent('brand-logos', renderBrandLogos);
