import { el } from './dom.js';
import { icon } from './icons.js';
import { openModal, closeModal } from './modal.js';
import { runAction } from './accounts-ui.js';

/**
 * Pezzi in comune tra commesse e attivita': la riga di un'attivita' da
 * spuntare, la barra di avanzamento, la domanda breve ("cosa aspettiamo dal
 * cliente?") e i pulsanti d'azione di una scheda.
 */

export function progressBar(progress, { large = false } = {}) {
    return el('div', { className: 'wk-progress' + (large ? ' wk-progress--large' : '') }, [
        el('div', { className: 'wk-progress__track' }, [
            el('span', { className: 'wk-progress__fill', style: `width:${progress.pct}%` }),
        ]),
        el('span', { className: 'wk-progress__label' }, [progress.total > 0 ? `${progress.done}/${progress.total} fasi` : 'nessuna fase']),
    ]);
}

export function initialsBadge(initials, title) {
    return initials ? el('span', { className: 'wk-who', title: title || '' }, [initials]) : null;
}

/** Domanda con una riga di testo, poi l'azione (es. il motivo dell'attesa). */
export function askText({ title, label, placeholder = '', confirmLabel = 'Conferma', onSubmit }) {
    const input = el('input', { className: 'field__input', type: 'text', placeholder });
    const submit = () => {
        const value = input.value.trim();
        closeModal();
        onSubmit(value);
    };
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') submit();
    });
    openModal({
        title,
        tone: 'info',
        body: [
            el('label', { className: 'field' }, [el('span', { className: 'field__label' }, [label]), input]),
            el('div', { className: 'wk-ask__actions' }, [
                el('button', { type: 'button', className: 'btn btn--ghost', onClick: closeModal }, ['Annulla']),
                el('button', { type: 'button', className: 'btn btn--primary', onClick: submit }, [confirmLabel]),
            ]),
        ],
    });
    setTimeout(() => input.focus(), 50);
}

/**
 * Pulsanti di una scheda. Un'azione puo' essere un link (href, anche da
 * scaricare), portare campi fissi (body) o chiedere prima una riga di
 * testo (ask: l'etichetta della domanda; il testo arriva come reason/text).
 */
export function actionsRow(actions) {
    return el('div', { className: 'acc-profile__actions' }, (actions || []).map((a) => {
        const content = [a.icon ? icon(a.icon) : null, a.label];
        if (a.href) {
            return el('a', { className: `btn btn--${a.variant || 'secondary'} btn--small`, href: a.href, download: a.download ? '' : null, target: a.target || null, rel: a.target ? 'noopener' : null }, content);
        }
        const button = el('button', { type: 'button', className: `btn btn--${a.variant || 'secondary'} btn--small` }, content);
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (a.ask) {
                askText({
                    title: a.label,
                    label: typeof a.ask === 'string' ? a.ask : 'Cosa?',
                    placeholder: a.placeholder || 'Es. i testi della home e le foto del locale',
                    onSubmit: (text) => runAction(a.action, { body: { ...(a.body || {}), reason: text, text }, button }),
                });
                return;
            }
            runAction(a.action, { confirm: a.confirm || null, button, body: a.body || null });
        });
        return button;
    }));
}

/**
 * Un'attivita': il cerchio per spuntarla, il titolo, a cosa appartiene,
 * quando, chi, quanto. Spuntata resta visibile, barrata, fino al giorno dopo.
 */
export function taskRow(item, { showContext = true, showWho = true } = {}) {
    const check = el('button', {
        type: 'button',
        className: 'wk-check' + (item.done ? ' is-done' : ''),
        title: item.done ? 'Riapri' : 'Segna come fatta',
        'aria-label': item.done ? 'Riapri' : 'Segna come fatta',
    }, [icon('check')]);
    check.addEventListener('click', (e) => {
        e.preventDefault();
        runAction(item.toggleAction, { button: check });
    });

    const chips = [
        item.when ? el('span', { className: `wk-chip wk-chip--${item.tone}` }, [icon(item.done ? 'circle-check' : 'calendar'), item.day && !item.done && !item.when.includes(item.day) ? `${item.day} · ${item.when}` : item.when]) : null,
        item.estimate ? el('span', { className: 'wk-chip' }, [icon('clock'), item.actual ? `${item.actual} su ${item.estimate}` : item.estimate]) : null,
        item.checklist ? el('span', { className: 'wk-chip' }, [icon('check'), item.checklist]) : null,
        item.waits ? el('span', { className: 'wk-chip wk-chip--warning' }, [icon('clock'), 'aspetta il cliente']) : null,
        item.recurring ? el('span', { className: 'wk-chip' }, [icon('arrows-rotate'), item.recurring]) : null,
        item.priority === 'alta' && !item.done ? el('span', { className: 'wk-chip wk-chip--danger' }, ['priorita alta']) : null,
    ].filter(Boolean);

    return el('div', { className: 'wk-task' + (item.done ? ' wk-task--done' : '') + (item.isNext ? ' wk-task--next' : '') }, [
        check,
        el('div', { className: 'wk-task__body' }, [
            el('div', { className: 'wk-task__top' }, [
                el('a', { className: 'wk-task__title', href: item.href }, [item.title]),
                item.isNext ? el('span', { className: 'badge badge--accent' }, ['ora tocca a questa']) : null,
            ]),
            showContext && item.context ? el('a', { className: 'wk-task__context', href: item.contextHref }, [item.context]) : null,
            chips.length ? el('div', { className: 'wk-task__chips' }, chips) : null,
        ]),
        showWho ? initialsBadge(item.whoInitials, item.who) : null,
    ]);
}
