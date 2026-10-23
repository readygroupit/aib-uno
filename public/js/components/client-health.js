import { registerComponent, renderComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge } from '../accounts-ui.js';

// Temperatura dei clienti: in cima chi rischia, ognuno con il perche' e
// la cosa da fare; un clic per registrare com'e' andata la telefonata.

export function healthDot(tone, title) {
    return el('span', { className: `ch-dot ch-dot--${tone}`, title: title || '' });
}

export function touchpointRow(t) {
    return el('a', { className: 'ch-touch', href: t.href }, [
        el('span', { className: 'ch-touch__date' }, [t.date]),
        el('span', { className: 'ch-touch__body' }, [
            el('span', { className: 'ch-touch__title' }, [t.customer ? `${t.customer} · ` : '', t.summary]),
            el('span', { className: 'ch-touch__meta' }, [[t.channel, t.who, t.next ? `poi: ${t.next}` : null].filter(Boolean).join(' · ')]),
        ]),
        badge(t.mood, t.moodTone),
    ]);
}

function row(r) {
    return el('div', { className: `ch-row ch-row--${r.tone}` }, [
        el('div', { className: 'ch-row__head' }, [
            healthDot(r.tone, r.label),
            el('a', { className: 'ch-row__name', href: r.href }, [r.name]),
            badge(r.label, r.tone),
            el('span', { className: 'ch-row__score', title: 'Temperatura da 0 a 100' }, [`${r.score}°`]),
        ]),
        r.reasons.length ? el('p', { className: 'ch-row__reasons' }, [r.reasons.join(' · ')]) : null,
        el('div', { className: 'ch-row__foot' }, [
            el('span', { className: 'ch-row__suggestion' }, [icon('sparkle'), r.suggestion]),
            el('a', { className: 'btn btn--secondary btn--small', href: r.logHref }, [icon('phone'), "Com'e' andata"]),
        ]),
    ]);
}

function renderClientHealth(data) {
    const risky = data.rows.filter((r) => r.level !== 'bene');
    const fine = data.rows.filter((r) => r.level === 'bene');

    return el('div', { className: 'acc-board' }, [
        el('div', { className: 'claim-table__header' }, [
            el('h1', { className: 'claim-table__title' }, [data.title]),
            el('div', { className: 'claim-table__actions' }, [
                data.createHref ? el('a', { className: 'btn btn--secondary', href: data.createHref }, [icon('plus'), 'Registra un contatto']) : null,
            ]),
        ]),
        el('div', { className: 'stat-grid' }, data.stats.map(renderComponent).filter(Boolean)),
        el('div', { className: 'acc-board__layout' }, [
            el('div', { className: 'acc-board__main' }, [
                el('section', { className: 'card acc-section' }, [
                    el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Da sentire'])]),
                    risky.length
                        ? el('div', { className: 'ch-list' }, risky.map(row))
                        : el('p', { className: 'acc-empty' }, [icon('circle-check'), ' Nessun cliente da recuperare: tutti stanno bene.']),
                ]),
                fine.length ? el('section', { className: 'card acc-section' }, [
                    el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, [`Stanno bene (${fine.length})`])]),
                    el('div', { className: 'acc-chips' }, fine.map((r) => el('a', { className: 'acc-chip', href: r.href }, [healthDot('success'), r.name]))),
                ]) : null,
            ].filter(Boolean)),
            el('div', { className: 'acc-board__side' }, [
                el('section', { className: 'card acc-section' }, [
                    el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Ultimi contatti'])]),
                    data.recent.length
                        ? el('div', { className: 'ch-touches' }, data.recent.map(touchpointRow))
                        : el('p', { className: 'acc-empty' }, ['Ancora nessun contatto registrato. Dopo una telefonata importante, segna in 20 secondi com\'e\' andata.']),
                ]),
            ]),
        ]),
    ]);
}

registerComponent('client-health', renderClientHealth);
