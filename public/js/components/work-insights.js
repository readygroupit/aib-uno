import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { actionButton } from '../accounts-ui.js';

// Pannello dei titolari: prima il dato che conta (le ore della settimana),
// poi cosa guardare, poi i tempi per tipo di lavoro. In fondo, sempre,
// a cosa servono questi dati.
function renderInsights(data) {
    const missing = data.missing.length;
    const stats = el('div', { className: 'stat-grid' }, [
        el('section', { className: 'card stat-box stat-box--feature' }, [
            el('div', { className: 'stat-box__header' }, [
                el('span', { className: 'stat-box__label' }, ['Ore segnate questa settimana']),
                el('span', { className: 'stat-box__badge' }, [`${data.people.length} persone`]),
            ]),
            el('div', { className: 'stat-box__value-row' }, [el('span', { className: 'stat-box__value' }, [data.weekTotal])]),
            el('div', { className: 'stat-box__comparison' }, [data.people.map((p) => `${p.name} ${p.week}`).join(' · ')]),
        ]),
        el('section', { className: 'card stat-box' }, [
            el('div', { className: 'stat-box__header' }, [el('span', { className: 'stat-box__label' }, ['Da guardare'])]),
            el('div', { className: 'stat-box__value-row' }, [el('span', { className: 'stat-box__value' }, [String(data.anomalies.length)])]),
            el('div', { className: 'stat-box__comparison' }, ['lavori fuori dal solito']),
        ]),
        el('section', { className: 'card stat-box' }, [
            el('div', { className: 'stat-box__header' }, [el('span', { className: 'stat-box__label' }, ['Fermi'])]),
            el('div', { className: 'stat-box__value-row' }, [el('span', { className: 'stat-box__value' }, [String(data.stuck.length)])]),
            el('div', { className: 'stat-box__comparison' }, ['commesse e attivita\' senza movimenti']),
        ]),
        el('section', { className: 'card stat-box' }, [
            el('div', { className: 'stat-box__header' }, [el('span', { className: 'stat-box__label' }, ['Non hanno segnato'])]),
            el('div', { className: 'stat-box__value-row' }, [el('span', { className: 'stat-box__value' }, [String(missing)])]),
            el('div', { className: 'stat-box__comparison' }, [missing ? data.missing.map((p) => p.name).join(', ') : 'tutti a posto oggi']),
        ]),
    ]);

    const attention = [];
    data.overloaded.forEach((p) => attention.push(el('a', { className: 'pl-alert pl-alert--danger', href: p.href }, [icon('circle-exclamation'), el('span', {}, [`${p.name} oggi e' al ${p.load}%${p.overflow ? ` (${p.overflow})` : ''}: valuta di spostare o passare qualcosa.`])])));
    data.anomalies.forEach((a) => attention.push(el(a.href ? 'a' : 'div', { className: `pl-alert pl-alert--${a.tone === 'warning' ? 'warning' : 'info'}`, href: a.href || null }, [icon(a.tone === 'warning' ? 'clock' : 'user'), el('span', {}, [a.text])])));
    data.stuck.forEach((s) => attention.push(el('a', { className: 'pl-alert', href: s.href }, [icon('clock'), el('span', {}, [`${s.title}: ${s.when}.`])])));

    const report = data.report.length
        ? el('div', { className: 'wl-report' }, data.report.map((r) => el('div', { className: 'wl-report__row' }, [
            el('div', { className: 'wl-report__name' }, [
                el('strong', {}, [r.label]),
                el('span', { className: 'acc-muted' }, [`${r.samples} volte · da ${r.range}`]),
            ]),
            el('div', { className: 'wl-report__numbers' }, [
                el('span', {}, ['previsto ', el('strong', {}, [r.planned || '—'])]),
                el('span', {}, ['reale ', el('strong', {}, [r.real])]),
                r.deltaLabel ? el('span', { className: `wk-chip wk-chip--${r.tone === 'danger' ? 'danger' : r.tone === 'success' ? 'success' : ''}` }, [r.deltaLabel]) : null,
            ]),
            el('div', { className: 'wl-report__people' }, r.people.map((p) => el('span', { className: 'wk-chip' }, [`${p.name} ${p.median}`, p.n > 1 ? ` (${p.n})` : '']))),
            r.applyAction ? actionButton(r.applyLabel, r.applyAction, { variant: 'secondary' }) : null,
        ])))
        : el('p', { className: 'acc-empty' }, ['Ancora pochi dati: servono almeno 3 lavori dello stesso tipo segnati nel registro.']);

    return el('div', { className: 'pl' }, [
        el('div', { className: 'claim-table__header' }, [el('h1', { className: 'claim-table__title' }, ['Tempi e andamento'])]),
        stats,
        attention.length ? el('section', { className: 'card pl-alerts' }, [el('h2', { className: 'card__title' }, ['Da guardare']), el('div', { className: 'pl-alerts__list' }, attention)]) : null,
        el('section', { className: 'card acc-section' }, [
            el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Quanto durano davvero i lavori'])]),
            el('p', { className: 'acc-muted' }, ['Il tempo tipico (mediana) di ogni tipo di lavoro, dai tempi segnati. Con 3 o piu\' casi l\'organizer usa questi tempi per pianificare.']),
            report,
        ]),
        el('p', { className: 'wl-privacy' }, [icon('circle-exclamation'), data.privacy]),
    ].filter(Boolean));
}

registerComponent('work-insights', renderInsights);
