import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { agentAvatar } from '../avatars.js';
import { icon } from '../icons.js';

function post(url) {
    return fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '', 'Content-Type': 'application/json' }, body: '{}' })
        .then((r) => r.json())
        .catch(() => ({ ok: false }));
}

const MONTHS = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];

// "09:02" se di oggi, "ieri 17:40", altrimenti "28 set".
function whenLabel(createdAt) {
    const d = new Date(String(createdAt).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '';
    const time = d.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
    const dayDiff = Math.round((new Date().setHours(0, 0, 0, 0) - new Date(d).setHours(0, 0, 0, 0)) / 86400000);
    if (dayDiff === 0) return time;
    if (dayDiff === 1) return 'ieri ' + time;
    return `${d.getDate()} ${MONTHS[d.getMonth()]}`;
}

// Riga compatta (stile "Da approvare" del design): volto, chi e', il
// messaggio su massimo due righe, i pulsanti a destra. La bozza preparata
// dall'agente resta chiusa: si apre con "Rivedi".
function buildMessage(item, canEdit, refresh) {
    const done = item.state === 'done';
    const actions = el('div', { className: 'agent-row__actions' });
    const draft = item.detail ? el('blockquote', { className: 'agent-msg__draft', hidden: '' }, [item.detail]) : null;

    if (!done && canEdit && item.actionType) {
        const approve = el('button', { type: 'button', className: 'btn btn--primary btn--small' }, [item.actionLabel || 'Approva']);
        approve.addEventListener('click', async () => {
            approve.disabled = true;
            approve.textContent = '...';
            const result = await post(`/agenti/messaggi/${item.id}/approva`);
            if (result.ok) refresh(); else { approve.disabled = false; approve.textContent = item.actionLabel || 'Approva'; }
        });
        actions.appendChild(approve);
    }
    if (draft) {
        const review = el('button', { type: 'button', className: 'btn btn--secondary btn--small' }, ['Rivedi']);
        review.addEventListener('click', () => {
            draft.hidden = !draft.hidden;
            review.textContent = draft.hidden ? 'Rivedi' : 'Chiudi';
        });
        actions.appendChild(review);
    } else if (item.linkHref) {
        actions.appendChild(el('a', { className: 'btn btn--secondary btn--small', href: item.linkHref }, [item.actionType && !done ? 'Rivedi' : 'Apri']));
    }
    if (!done && canEdit) {
        const dismiss = el('button', { type: 'button', className: 'agent-row__dismiss', title: item.actionType ? 'Ignora' : 'Ok, letto', 'aria-label': item.actionType ? 'Ignora' : 'Ok, letto' }, ['\u00d7']);
        dismiss.addEventListener('click', async () => { dismiss.disabled = true; await post(`/agenti/messaggi/${item.id}/ignora`); refresh(); });
        actions.appendChild(dismiss);
    }

    const main = [
        el('div', { className: 'agent-row__head' }, [
            el('span', { className: 'agent-row__name' }, [item.agentName]),
            el('span', { className: 'agent-row__role' }, [item.agentRole]),
            el('span', { className: 'agent-row__time' }, [whenLabel(item.createdAt)]),
        ]),
        el('p', { className: 'agent-row__text', title: item.body }, [item.body]),
    ];
    if (draft) main.push(draft);
    if (done && item.resultText) {
        main.push(el('div', { className: 'agent-msg__result' }, [icon('circle-check'), item.resultText]));
    }

    return el('div', { className: 'agent-row' + (done ? ' is-done' : '') }, [
        el('span', { className: 'agent-row__avatar' }, [agentAvatar(item.agentCode, 36)]),
        el('div', { className: 'agent-row__main' }, main),
        actions.children.length ? actions : null,
    ].filter(Boolean));
}

const AUTONOMY_LABELS = { auto: 'Autonomo', approval: 'Chiede OK', suggest: 'Suggerisce' };

// Colonna destra "Agenti al lavoro" (stile del design): stato, autonomia e
// quante cose aspettano il tuo OK.
function buildTeam(agents) {
    return el('aside', { className: 'agent-team' }, [
        el('span', { className: 'agent-feed__eyebrow agent-team__head' }, ['Agenti al lavoro']),
        el('ul', { className: 'agent-team__list' }, agents.map((agent) => el('li', { className: 'agent-team__item' + (agent.enabled ? '' : ' is-off') }, [
            el('span', { className: 'agent-team__avatar' }, [agentAvatar(agent.code, 28)]),
            el('span', { className: 'agent-team__who' }, [
                el('span', { className: 'agent-team__name' }, [agent.name]),
                el('span', { className: 'agent-team__role' }, [agent.role]),
            ]),
            agent.pending ? el('span', { className: 'agent-team__pending', title: 'Da approvare' }, [String(agent.pending)]) : null,
            el('span', { className: `agent-team__chip agent-team__chip--${agent.enabled ? agent.autonomy : 'off'}` }, [agent.enabled ? (AUTONOMY_LABELS[agent.autonomy] || agent.autonomy) : 'Spento']),
        ].filter(Boolean)))),
    ]);
}

function renderAgentFeed(data) {
    const list = el('div', { className: 'agent-feed__list' });
    const pendingBadge = el('span', { className: 'agent-feed__count' });

    function draw(items) {
        list.textContent = '';
        const pending = items.filter((i) => i.state === 'pending' && i.actionType).length;
        pendingBadge.textContent = pending ? `${pending} da approvare` : '';
        pendingBadge.hidden = !pending;

        if (items.length === 0) {
            list.appendChild(el('p', { className: 'agent-feed__empty' }, ['I tuoi colleghi digitali non hanno niente da segnalare. Programmali dalla pagina Agenti.']));
            return;
        }
        items.forEach((item) => list.appendChild(buildMessage(item, data.canEdit, refresh)));
    }

    function refresh() {
        fetch('/agenti/messaggi', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => r.json())
            .then((fresh) => draw(fresh.items || []))
            .catch(() => {});
    }

    draw(data.items || []);

    return el('section', { className: 'card agent-feed' }, [
        el('div', { className: 'agent-feed__header' }, [
            el('div', {}, [
                el('span', { className: 'agent-feed__eyebrow' }, ['I tuoi colleghi digitali']),
                el('h2', { className: 'agent-feed__title' }, ['Cosa e\' successo mentre non c\'eri']),
            ]),
            pendingBadge,
            el('a', { className: 'btn btn--secondary btn--small', href: '/agenti' }, ['Gestisci agenti']),
        ]),
        el('div', { className: 'agent-feed__body' }, [
            list,
            data.agents && data.agents.length ? buildTeam(data.agents) : null,
        ].filter(Boolean)),
        el('p', { className: 'agent-feed__demo' }, ['Ambiente dimostrativo: gli agenti leggono i dati veri ma non inviano nulla all\'esterno.']),
    ]);
}

registerComponent('agent-feed', renderAgentFeed);
