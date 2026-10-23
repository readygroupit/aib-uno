import { registerComponent, renderComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { actionButton } from '../accounts-ui.js';

// Piano editoriale: la settimana, una riga per cliente. Ogni contenuto e'
// un'etichetta colorata per stato (cosa manca a quel contenuto si vede
// dal colore); sopra i buchi rispetto al contratto, da riempire a un clic.

const CHANNEL_SHORT = { Instagram: 'IG', Facebook: 'FB', TikTok: 'TT', LinkedIn: 'IN' };

function chip(p) {
    return el('a', {
        className: `ed-chip ed-chip--${p.tone}` + (p.late ? ' ed-chip--late' : ''),
        href: p.href,
        title: `${p.channel} · ${p.format} · ${p.stage}${p.time ? ' · ' + p.time : ''}`,
    }, [
        el('span', { className: 'ed-chip__channel' }, [CHANNEL_SHORT[p.channel] || p.channel]),
        el('span', { className: 'ed-chip__title' }, [p.title]),
        p.who ? el('span', { className: 'ed-chip__who' }, [p.who]) : null,
    ]);
}

function legend() {
    const items = [['neutral', 'idea'], ['info', 'in lavorazione'], ['accent', 'revisione interna'], ['warning', 'dal cliente'], ['danger', 'da modificare'], ['success', 'approvato'], ['strong', 'programmato']];
    return el('div', { className: 'ed-legend' }, items.map(([tone, label]) => el('span', { className: 'ed-legend__item' }, [el('span', { className: `ed-legend__dot ed-chip--${tone}` }), label])));
}

function renderEditorialBoard(data) {
    const header = el('div', { className: 'claim-table__header' }, [
        el('h1', { className: 'claim-table__title' }, [data.title]),
        el('div', { className: 'claim-table__actions' }, [
            el('a', { className: 'btn btn--ghost btn--small', href: data.prevHref, title: 'Settimana prima' }, [icon('chevron-left')]),
            el('span', { className: 'ed-period' }, [data.period]),
            el('a', { className: 'btn btn--ghost btn--small', href: data.nextHref, title: 'Settimana dopo' }, [icon('chevron-right')]),
            data.todayHref ? el('a', { className: 'btn btn--ghost btn--small', href: data.todayHref }, ['Oggi']) : null,
            el('a', { className: 'btn btn--ghost', href: data.quotasHref }, ['Contratti']),
            el('a', { className: 'btn btn--secondary', href: data.createHref }, [icon('plus'), 'Contenuto']),
        ]),
    ]);

    // La coda dei titolari: cosa aspetta la revisione interna, cliente per cliente, approvabile in un colpo.
    const review = data.review.length
        ? el('section', { className: 'card acc-section acc-group acc-group--info' }, [
            el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Da approvare internamente'])]),
            el('div', { className: 'acc-due-list' }, data.review.map((r) => el('div', { className: 'ed-review' }, [
                el('div', { className: 'ed-review__head' }, [
                    el('strong', {}, [r.name]),
                    el('span', { className: 'acc-muted' }, [`${r.count} ${r.count === 1 ? 'contenuto' : 'contenuti'}, dal ${r.first}`]),
                    actionButton(r.count === 1 ? 'Approva' : `Approva tutti (${r.count})`, r.action, { iconName: 'check', variant: 'primary', confirm: `Approvare ${r.count === 1 ? 'il contenuto' : `i ${r.count} contenuti`} di ${r.name}? Poi li vede il cliente.` }),
                ]),
                el('div', { className: 'ed-review__posts' }, r.posts.map((p) => el('a', { className: 'ed-chip ed-chip--accent', href: p.href }, [
                    el('span', { className: 'ed-chip__channel' }, [CHANNEL_SHORT[p.channel] || p.channel]),
                    el('span', { className: 'ed-chip__title' }, [`${p.when} · ${p.title}`]),
                ]))),
            ]))),
        ])
        : null;

    const gaps = data.gaps.length
        ? el('section', { className: 'card acc-section acc-group acc-group--warning' }, [
            el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Mancano contenuti'])]),
            el('div', { className: 'acc-due-list' }, data.gaps.map((g) => el('div', { className: 'ed-gap' }, [
                el('span', { className: 'ed-gap__text' }, [g.text]),
                el('span', { className: 'ed-gap__actions' }, [
                    g.aiAction ? actionButton("Proponi con l'IA", g.aiAction, { iconName: 'wand-magic-sparkles', variant: 'secondary' }) : null,
                    actionButton(g.label, g.action, { iconName: 'plus', variant: g.aiAction ? 'ghost' : 'secondary' }),
                ].filter(Boolean)),
            ]))),
        ])
        : null;

    const grid = el('section', { className: 'card ed-grid-card' }, [
        // Il fine settimana piu' stretto: si pubblica meno, lo spazio serve ai giorni feriali.
        el('div', { className: 'ed-grid', style: `grid-template-columns: minmax(150px, 1.1fr) ${data.days.map((d) => (d.isWeekend ? 'minmax(90px, 0.6fr)' : 'minmax(120px, 1fr)')).join(' ')}` }, [
            el('div', { className: 'ed-grid__corner' }, ['Cliente']),
            ...data.days.map((d) => el('div', { className: 'ed-grid__day' + (d.isToday ? ' is-today' : '') + (d.isWeekend ? ' is-weekend' : '') }, [d.label])),
            ...data.rows.flatMap((r) => [
                el('div', { className: 'ed-grid__customer' }, [
                    el('a', { className: 'ed-grid__name', href: r.href }, [r.name]),
                    r.quota.length ? el('span', { className: 'ed-grid__quota' }, [r.quota.join(' · ')]) : null,
                    el('span', { className: 'ed-grid__links' }, [
                        el('a', { href: r.strategyHref }, [r.hasStrategy ? 'Strategia' : '+ Strategia']),
                        // Chi chiede tante modifiche si vede subito: utile al rinnovo e ai preventivi.
                        r.revisions >= 2 ? el('span', { className: 'ed-grid__revisions', title: 'Giri di modifiche nell\'ultimo mese' }, [`${r.revisions} modifiche`]) : null,
                    ]),
                ]),
                ...r.cells.map((c) => el('div', { className: 'ed-grid__cell' + (data.days.find((d) => d.date === c.date)?.isWeekend ? ' is-weekend' : '') }, [
                    ...c.posts.map(chip),
                    el('a', { className: 'ed-grid__add', href: `${r.newHref}&publish_date=${c.date}`, title: 'Nuovo contenuto' }, [icon('plus')]),
                ])),
            ]),
        ]),
        data.rows.length ? legend() : el('p', { className: 'acc-empty' }, ['Nessun cliente con contenuti o contratti social. ', el('a', { href: data.quotasHref }, ['Imposta il primo contratto'])]),
    ]);

    return el('div', { className: 'acc-board' }, [
        header,
        el('div', { className: 'stat-grid' }, data.stats.map(renderComponent).filter(Boolean)),
        review,
        gaps,
        grid,
    ].filter(Boolean));
}

registerComponent('editorial-board', renderEditorialBoard);
