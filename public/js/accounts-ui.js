import { el } from './dom.js';
import { icon } from './icons.js';
import { refreshCurrentEntry, openInPanel } from './components/hero.js';
import { openModal, closeModal } from './modal.js';

/**
 * Pezzi in comune tra lista clienti, scheda cliente, scadenzario e
 * servizio: badge, riga di un incasso, azioni con un clic (incassato,
 * sollecitato, rinnova) e l'avviso breve che conferma cosa e' successo.
 */

export function badge(label, tone = 'neutral', iconName = null) {
    return el('span', { className: `badge badge--${tone} acc-badge` }, [iconName ? icon(iconName) : null, label]);
}

let toastTimer = null;

/** Conferma breve in basso: cosa e' appena successo, senza un modale da chiudere. */
export function toast(message, tone = 'success') {
    document.querySelector('.acc-toast')?.remove();
    clearTimeout(toastTimer);
    const node = el('div', { className: `acc-toast acc-toast--${tone}`, role: 'status' }, [
        icon(tone === 'success' ? 'circle-check' : 'circle-exclamation'),
        el('span', {}, [message]),
    ]);
    document.body.appendChild(node);
    requestAnimationFrame(() => node.classList.add('is-visible'));
    toastTimer = setTimeout(() => {
        node.classList.remove('is-visible');
        setTimeout(() => node.remove(), 300);
    }, 3600);
}

async function reloadCurrentScreen() {
    const response = await fetch(window.location.pathname + window.location.search, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await response.json();
    refreshCurrentEntry(data.components || []);
}

/**
 * POST di un'azione. Se il server risponde con una schermata
 * (components) la mostra; se risponde con un messaggio, lo conferma e
 * ricarica la schermata corrente, cosi' i numeri sono subito aggiornati.
 */
export async function runAction(url, { confirm = null, button = null, body = null } = {}) {
    if (confirm && !window.confirm(confirm)) return;
    if (button) button.disabled = true;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '' },
            // Campi semplici come un form (es. il motivo dell'attesa): li legge $_POST.
            body: body ? new URLSearchParams(body) : null,
        });
        const data = await response.json();
        if (data.components) {
            refreshCurrentEntry(data.components);
            return;
        }
        // Un testo da mandare al cliente (es. link di pagamento): copia o email.
        if (data.share) {
            shareModal(data.share);
            return;
        }
        // La schermata da aprire dopo l'azione (es. la bozza di fattura appena creata).
        if (data.ok && data.open) {
            if (data.message) toast(data.message);
            openInPanel(data.open);
            return;
        }
        toast(data.message || (data.ok ? 'Fatto.' : 'Non riuscito.'), data.ok ? 'success' : 'error');
        if (data.ok) await reloadCurrentScreen();
    } catch (e) {
        openModal({ title: 'Errore di comunicazione', tone: 'error', body: "Non e' stato possibile contattare il server. Riprova." });
    } finally {
        if (button && button.isConnected) button.disabled = false;
    }
}

/** Link e messaggio pronti per il cliente: copia il link, copia il testo o apri l'email. */
export function shareModal(share) {
    const copy = (text, label) => {
        const button = el('button', { type: 'button', className: 'btn btn--secondary btn--small' }, [icon('copy'), label]);
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(text);
                toast('Copiato.');
            } catch (e) {
                window.prompt('Copia da qui:', text);
            }
        });
        return button;
    };
    const mail = share.email
        ? el('a', {
            className: 'btn btn--primary btn--small',
            href: `mailto:${encodeURIComponent(share.email)}?subject=${encodeURIComponent(share.subject || '')}&body=${encodeURIComponent(share.text || share.url)}`,
            onClick: () => closeModal(),
        }, [icon('envelope'), `Scrivi a ${share.email}`])
        : null;

    openModal({
        title: share.title || 'Link',
        tone: 'info',
        body: [
            share.intro ? el('p', { className: 'acc-share__intro' }, [share.intro]) : null,
            el('div', { className: 'acc-share__url' }, [share.url]),
            el('div', { className: 'acc-share__actions' }, [
                mail,
                copy(share.url, 'Copia il link'),
                share.text ? copy(share.text, 'Copia il messaggio') : null,
            ].filter(Boolean)),
        ].filter(Boolean),
    });
}

export function actionButton(label, url, { confirm = null, variant = 'secondary', iconName = null } = {}) {
    const button = el('button', { type: 'button', className: `btn btn--${variant} btn--small` }, [iconName ? icon(iconName) : null, label]);
    button.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        runAction(url, { confirm, button });
    });
    return button;
}

/**
 * Una riga di scadenzario: data, cosa, (cliente), importo, stato, azioni.
 * Stessa forma ovunque, cosi' "incassato" si trova sempre allo stesso posto.
 */
export function installmentRow(item, { showCustomer = true } = {}) {
    const dateBox = el('div', { className: `acc-due__date acc-due__date--${item.tone}` }, [
        el('span', { className: 'acc-due__day' }, [item.date]),
    ]);

    const text = el('div', { className: 'acc-due__text' }, [
        showCustomer && item.customer
            ? el('a', { className: 'acc-due__customer', href: item.customerHref }, [item.customer])
            : null,
        el('a', { className: 'acc-due__desc', href: item.href }, [item.description]),
        el('span', { className: 'acc-due__meta' }, [
            [item.when, item.method, item.reminded].filter(Boolean).join(' · '),
        ]),
    ]);

    const actions = el('div', { className: 'acc-due__actions' });
    if (item.payAction) {
        if (item.tone === 'danger') {
            actions.appendChild(actionButton('Ho sollecitato', item.payAction.replace('/incassa', '/sollecitato'), { iconName: 'bell', variant: 'ghost' }));
        }
        if (item.linkAction) {
            actions.appendChild(actionButton('Link', item.linkAction, { iconName: 'link', variant: 'ghost' }));
        }
        actions.appendChild(actionButton('Incassato', item.payAction, { iconName: 'check', variant: 'secondary' }));
    } else if (item.paid) {
        actions.appendChild(badge('Incassato', 'success', 'circle-check'));
    }
    // La fattura: aprirla se c'e', prepararla se manca.
    if (item.invoiceHref) {
        actions.appendChild(el('a', { className: 'btn btn--ghost btn--small', href: item.invoiceHref, title: 'Apri la fattura' }, [icon('file-invoice'), item.invoiceLabel || 'Fattura']));
    } else if (item.invoiceAction) {
        actions.appendChild(actionButton('Fattura', item.invoiceAction, { iconName: 'file-invoice', variant: 'ghost' }));
    }

    return el('div', { className: `acc-due acc-due--${item.tone}` + (item.paid ? ' acc-due--paid' : '') }, [
        dateBox,
        text,
        el('span', { className: 'acc-due__amount' }, [item.amount]),
        actions,
    ]);
}

/** Il consiglio "cosa fare adesso" (vedi AccountOverviewService::suggestion()). */
export function suggestionBox(suggestion) {
    if (!suggestion) return null;
    return el('div', { className: `acc-suggestion acc-suggestion--${suggestion.tone}` }, [
        el('span', { className: 'acc-suggestion__icon' }, [icon(suggestion.tone === 'danger' ? 'circle-exclamation' : suggestion.tone === 'info' ? 'sparkle' : 'bell')]),
        el('div', {}, [
            el('span', { className: 'acc-suggestion__title' }, [suggestion.title]),
            el('p', { className: 'acc-suggestion__text' }, [suggestion.text]),
        ]),
    ]);
}

export function avatar(initials, variant, size = 'md') {
    return el('span', { className: `acc-avatar acc-avatar--${size} acc-avatar--${variant}` }, [initials]);
}
