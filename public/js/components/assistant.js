import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { runAction } from '../accounts-ui.js';
import { actionsRow } from '../work-ui.js';
import { aiAvatar, personAvatar } from '../avatars.js';

// La giornata secondo l'assistente: parla per primo, dice cosa decidere,
// fare, fissare e risentire, con il perche' e il pulsante per farlo.
// A chi tocca un'attivita': "Tocca a te" o il nome, con la foto o le iniziali.
function whoChip(who) {
    if (!who) return null;
    if (who.missing) return el('span', { className: 'as-who as-who--missing' }, ['Nessuno assegnato']);
    return el('span', { className: 'as-who' + (who.me ? ' as-who--me' : '') }, [
        personAvatar({ avatar: who.avatar, initials: who.initials, name: who.name }, 18),
        who.me ? 'Tocca a te' : `Tocca a ${who.name}`,
    ]);
}

function agendaItem(item) {
    return el('li', { className: 'as-item' }, [
        el('div', { className: 'as-item__text' }, [
            item.href ? el('a', { className: 'as-item__title', href: item.href }, [item.title]) : el('span', { className: 'as-item__title' }, [item.title]),
            item.context ? el('a', { className: 'as-item__context', href: item.contextHref }, [item.context]) : null,
            whoChip(item.who),
            item.why ? el('span', { className: 'as-item__why' }, [item.why]) : null,
        ]),
        item.actions && item.actions.length ? actionsRow(item.actions) : null,
    ]);
}

function renderAgenda(data) {
    const head = el('section', { className: 'card as-head' }, [
        el('div', { className: 'as-head__text' }, [
            el('h1', { className: 'as-head__greeting' }, [data.greeting]),
            el('p', { className: 'as-head__summary' }, [data.summary]),
        ]),
        data.rulesHref ? el('a', { className: 'btn btn--ghost btn--small', href: data.rulesHref }, [icon('pen'), 'Regole']) : null,
    ]);

    const sections = data.sections.map((s) => el('section', { className: `card acc-section acc-group acc-group--${s.tone || 'neutral'}` }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, [s.title, el('span', { className: 'acc-count' }, [String(s.items.length)])]),
        ]),
        el('ul', { className: 'as-items' }, s.items.map(agendaItem)),
    ]));

    const done = data.done && data.done.length
        ? el('section', { className: 'card acc-section' }, [
            el('h2', { className: 'card__title' }, ["Fatto dall'assistente"]),
            el('ul', { className: 'as-items' }, data.done.map((d) => el('li', { className: 'as-item as-item--done' }, [
                el('span', { className: 'as-item__title' }, [d.title]),
                el('span', { className: 'as-item__why' }, [`${d.stage === 'eseguita' ? 'mandato' : 'non serviva'} ${d.when}`]),
            ]))),
        ])
        : null;

    return el('div', { className: 'as-agenda' }, [head, ...sections, done]);
}

// Le regole: accesa o spenta, dopo quanti giorni, cosa puo' fare da solo.
function renderRules(data) {
    const state = {};
    let lastAgent = null;
    const rows = data.rows.map((r) => {
        state[`${r.code}__enabled`] = r.enabled ? '1' : '0';
        const toggle = el('button', { type: 'button', className: 'field__choice' + (r.enabled ? ' is-on' : ''), 'aria-pressed': r.enabled ? 'true' : 'false' }, ['Accesa']);
        toggle.addEventListener('click', () => {
            const on = state[`${r.code}__enabled`] !== '1';
            state[`${r.code}__enabled`] = on ? '1' : '0';
            toggle.classList.toggle('is-on', on);
            toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        const controls = [toggle];
        if (r.days !== null && r.days !== undefined) {
            state[`${r.code}__days`] = String(r.days);
            const input = el('input', { type: 'number', min: '0', max: '365', className: 'field__input as-rule__days', value: String(r.days), 'aria-label': r.daysLabel || 'giorni' });
            input.addEventListener('input', () => { state[`${r.code}__days`] = input.value; });
            controls.push(el('label', { className: 'as-rule__field' }, [input, el('span', {}, [r.daysLabel || 'giorni'])]));
        }
        if (r.autonomy) {
            state[`${r.code}__autonomy`] = r.autonomy;
            const select = el('select', { className: 'field__input field__input--select' }, data.autonomies.map((a) => {
                const option = el('option', { value: a }, [a.charAt(0).toUpperCase() + a.slice(1)]);
                if (a === r.autonomy) option.selected = true;
                return option;
            }));
            select.addEventListener('change', () => { state[`${r.code}__autonomy`] = select.value; });
            controls.push(select);
        }
        const heading = r.agent && r.agent.key !== lastAgent
            ? el('li', { className: 'ag-rules-head', id: r.agent.key }, [aiAvatar(r.agent.key, 36, r.agent.color), el('span', {}, [el('strong', {}, [r.agent.name]), ` \u00b7 ${r.agent.role}`])])
            : null;
        lastAgent = r.agent ? r.agent.key : lastAgent;
        return [heading, el('li', { className: 'nt-pref' }, [
            el('span', { className: 'nt-pref__text' }, [
                el('span', { className: 'nt-pref__label' }, [r.label]),
                el('span', { className: 'nt-pref__help' }, [r.help]),
            ]),
            el('span', { className: 'nt-pref__choices' }, controls),
        ])];
    }).flat().filter(Boolean);
    const save = el('button', { type: 'button', className: 'btn btn--primary' }, ['Salva']);
    save.addEventListener('click', () => runAction(data.action, { button: save, body: state }));

    return el('section', { className: 'card acc-section nt-prefs' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h1', { className: 'card__title' }, [data.title]),
            el('div', { className: 'dc-actions' }, [
                data.seasonsHref ? el('a', { className: 'btn btn--secondary btn--small', href: data.seasonsHref }, [icon('calendar'), 'Periodi forti']) : null,
                el('a', { className: 'btn btn--ghost btn--small', href: '/assistente' }, [icon('sparkle'), 'I tuoi agenti']),
            ]),
        ]),
        el('p', { className: 'nt-pref__help' }, ["Solo avviso: ricorda e basta. Chiede conferma: prepara il messaggio per il cliente, lo mandi tu. In automatico: lo manda da solo e te lo dice."]),
        el('ul', { className: 'nt-pref-rows' }, rows),
        el('div', { className: 'nt-prefs__save' }, [save]),
    ]);
}

// Il piano di una richiesta con piu' cose da fare: si legge tutto, poi
// si conferma una volta sola. Prima della conferma non e' successo niente.
const ACTION_ICON = { add_note: 'pen', log_contact: 'handshake', notify: 'bell', add_to_team: 'user', create_task: 'check' };

function renderActionPlan(data) {
    const confirm = data.confirmAction ? el('button', { type: 'button', className: 'btn btn--primary' }, [icon('check'), 'Conferma']) : null;
    const cancel = el('button', { type: 'button', className: 'btn btn--ghost' }, ['Annulla']);
    const disable = () => { if (confirm) confirm.disabled = true; cancel.disabled = true; };
    if (confirm) confirm.addEventListener('click', () => { runAction(data.confirmAction, { button: confirm }).then(disable); });
    cancel.addEventListener('click', () => { runAction(data.cancelAction, { button: cancel }).then(disable); });

    return el('section', { className: 'card acc-section as-plan' }, [
        el('h2', { className: 'card__title' }, [icon('sparkle'), ' ', data.summary || 'Ecco cosa faccio']),
        data.steps.length ? el('ol', { className: 'as-plan__steps' }, data.steps.map((s) => el('li', { className: 'as-plan__step' }, [icon(ACTION_ICON[s.action] || 'check'), el('span', {}, [s.text])]))) : null,
        data.problems.length ? el('ul', { className: 'as-plan__problems' }, data.problems.map((p) => el('li', {}, [icon('circle-exclamation'), el('span', {}, [p])]))) : null,
        el('div', { className: 'nt-prefs__save as-plan__actions' }, [cancel, confirm]),
    ]);
}

// La home: i colleghi digitali sotto la ricerca, ognuno con le sue cose
// piu' urgenti o importanti. Sul telefono una fila da scorrere col dito
// (una scheda alla volta, la piu' urgente per prima, vedi assistant.css).
function renderColleagues(data) {
    const toneRank = { urgent: 0, soon: 1, calm: 2, none: 3 };
    const colleagues = [...data.colleagues].sort((a, b) => toneRank[a.tone] - toneRank[b.tone]);
    const card = (c) => el('section', { className: `card as-colleague as-colleague--${c.tone}`, 'aria-label': `${c.name}, ${c.role}` }, [
        el('div', { className: 'as-colleague__head' }, [
            aiAvatar(c.key, 44, c.color),
            el('div', { className: 'as-colleague__who' }, [
                el('span', { className: 'as-colleague__name' }, [c.name]),
                el('span', { className: 'as-colleague__role' }, [c.role]),
            ]),
            c.count ? el('span', { className: `as-colleague__count as-colleague__count--${c.tone}` }, [String(c.count)]) : null,
        ]),
        el('p', { className: 'as-colleague__say' }, [c.say]),
        c.items.length ? el('ul', { className: 'as-colleague__items' }, c.items.map((it, i) => el('li', { className: 'as-colleague__item' + (i > 0 ? ' as-colleague__item--extra' : '') }, [
            el('div', { className: 'as-colleague__meta' }, [
                el('span', { className: `as-tag as-tag--${it.urgency}` }, [it.tag]),
                it.context ? el('a', { className: 'as-colleague__context', href: it.contextHref }, [it.context]) : null,
                whoChip(it.who),
            ]),
            it.href ? el('a', { className: 'as-colleague__title', href: it.href }, [it.title]) : el('span', { className: 'as-colleague__title' }, [it.title]),
            it.why ? el('span', { className: 'as-colleague__why' }, [it.why]) : null,
            it.actions && it.actions.length ? actionsRow(it.actions.slice(0, 2)) : null,
        ]))) : null,
        c.items.length > 1 ? el('a', { className: 'as-colleague__mobile-more', href: `${data.agendaHref}?agente=${c.key}` }, [`+${c.count - 1} da ${c.name}`]) : null,
        el('a', { className: 'as-colleague__more', href: `${data.agendaHref}?agente=${c.key}` }, [c.more || `Apri ${c.name}`]),
    ]);

    return el('div', { className: 'as-colleagues' }, [
        el('div', { className: 'as-colleagues__head' }, [
            el('h2', { className: 'as-colleagues__title' }, ['I tuoi colleghi digitali']),
            el('a', { className: 'as-colleagues__all', href: data.agendaHref }, ['Tutti i tuoi agenti \u2192']),
        ]),
        el('div', { className: 'as-colleagues__grid' }, colleagues.map(card)),
    ]);
}

// La pagina degli agenti: in alto tutti, con quante cose hanno per te;
// sotto quello scelto ti parla, poi le sue cose, cosa ha fatto per te e
// come lavora. La scelta resta nell'indirizzo (?agente=) per tornarci.
function renderAgents(data) {
    let selected = data.selected;
    const tabs = el('div', { className: 'ag-tabs', role: 'tablist', 'aria-label': 'Agenti' });
    const panel = el('div', { className: 'ag-panel' });

    const urgencyClass = { oggi: 'oggi', presto: 'presto', importante: 'importante', idea: 'idea' };
    const item = (it) => el('li', { className: 'ag-item' }, [
        el('div', { className: 'ag-item__text' }, [
            el('div', { className: 'as-colleague__meta' }, [
                el('span', { className: `as-tag as-tag--${urgencyClass[it.urgency] || 'importante'}` }, [it.tag]),
                it.context ? el('a', { className: 'as-colleague__context', href: it.contextHref }, [it.context]) : null,
                whoChip(it.who),
            ]),
            it.href ? el('a', { className: 'ag-item__title', href: it.href }, [it.title]) : el('span', { className: 'ag-item__title' }, [it.title]),
            it.why ? el('span', { className: 'ag-item__why' }, [it.why]) : null,
        ]),
        it.actions && it.actions.length ? actionsRow(it.actions) : null,
    ]);

    function show(key) {
        selected = key;
        const a = data.agents.find((x) => x.key === key) || data.agents[0];
        [...tabs.children].forEach((t) => {
            const on = t.dataset.key === a.key;
            t.classList.toggle('is-on', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        try { window.history.replaceState(window.history.state, '', `/assistente?agente=${a.key}`); } catch (e) { /* niente */ }

        const askInput = el('input', { type: 'text', className: 'field__input ag-ask__input', placeholder: a.ask, 'aria-label': `Chiedi a ${a.name}` });
        const askButton = el('button', { type: 'button', className: 'btn btn--primary' }, ['Chiedi']);
        const ask = () => {
            const text = askInput.value.trim();
            if (text && window.unoSubmitPrompt) window.unoSubmitPrompt(text);
        };
        askButton.addEventListener('click', ask);
        askInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') ask(); });

        const main = el('section', { className: 'card ag-main' }, [
            el('div', { className: 'ag-hello' }, [
                aiAvatar(a.key, 44, a.color),
                el('p', { className: 'ag-hello__bubble' }, [a.hello]),
            ]),
            ...a.sections.map((s) => el('div', { className: `ag-group ag-group--${s.key}` }, [
                el('h3', { className: 'ag-group__title' }, [s.title, el('span', { className: 'acc-count' }, [String(s.items.length)])]),
                el('ul', { className: 'ag-items' }, s.items.map(item)),
            ])),
            el('div', { className: 'ag-ask' }, [askInput, askButton]),
        ]);

        const done = el('details', { className: 'card ag-side', open: window.matchMedia && window.matchMedia('(max-width: 720px)').matches ? null : '' }, [
            el('summary', { className: 'ag-side__title' }, [`Cosa ho fatto per te${a.done.length ? ` (${a.done.length})` : ''}`]),
            a.done.length
                ? el('ul', { className: 'ag-done' }, a.done.map((d) => el('li', {}, [
                    el('span', { className: 'ag-done__dot', style: `background:${a.color}` }),
                    el('span', { className: 'ag-done__text' }, [d.text, el('span', { className: 'ag-done__when' }, [d.when])]),
                ])))
                : el('p', { className: 'acc-empty' }, ['Niente negli ultimi 14 giorni.']),
        ]);
        const rules = el('details', { className: 'card ag-side', open: window.matchMedia && window.matchMedia('(max-width: 720px)').matches ? null : '' }, [
            el('summary', { className: 'ag-side__title' }, ['Come lavoro', a.rulesHref ? el('a', { className: 'ag-side__link', href: a.rulesHref }, ['Cambia']) : null]),
            el('ul', { className: 'ag-rules' }, a.rules.map((r) => el('li', {}, [
                el('span', { className: 'ag-rules__text' }, [el('span', { className: 'ag-rules__label' }, [r.label]), el('span', { className: 'ag-rules__when' }, [r.when])]),
                el('span', { className: `ag-rules__mode ag-rules__mode--${r.tone}` }, [r.autonomy]),
            ]))),
        ]);

        panel.replaceChildren(main, el('div', { className: 'ag-sidecol' }, [done, rules]));
    }

    data.agents.forEach((a) => {
        const tab = el('button', { type: 'button', role: 'tab', className: `ag-tab ag-tab--${a.tone}`, style: `--ai-color:${a.color}` }, [
            aiAvatar(a.key, 52, a.color),
            el('span', { className: 'ag-tab__who' }, [el('span', { className: 'ag-tab__name' }, [a.name]), el('span', { className: 'ag-tab__role' }, [a.role])]),
            a.count ? el('span', { className: `as-colleague__count as-colleague__count--${a.tone}` }, [String(a.count)]) : null,
        ]);
        tab.dataset.key = a.key;
        tab.addEventListener('click', () => show(a.key));
        tabs.appendChild(tab);
    });
    show(selected);

    return el('div', { className: 'ag-page' }, [
        el('div', { className: 'ag-head' }, [
            el('div', {}, [el('h1', { className: 'ag-head__title' }, [data.title]), el('p', { className: 'ag-head__intro' }, [data.intro])]),
            data.rulesHref ? el('a', { className: 'btn btn--secondary', href: data.rulesHref }, [icon('pen'), 'Configura gli agenti']) : null,
        ]),
        tabs,
        panel,
    ]);
}

registerComponent('assistant-agenda', renderAgenda);
registerComponent('assistant-agents', renderAgents);
registerComponent('assistant-colleagues', renderColleagues);
registerComponent('action-plan', renderActionPlan);
registerComponent('assistant-rules', renderRules);
