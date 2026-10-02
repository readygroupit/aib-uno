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

function buildMessage(item, canEdit, refresh) {
    const done = item.state === 'done';
    const actions = el('div', { className: 'agent-msg__actions' });

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
    if (item.linkHref) {
        actions.appendChild(el('a', { className: 'btn btn--secondary btn--small', href: item.linkHref }, [item.actionType && !done ? 'Rivedi' : 'Apri']));
    }
    if (!done && canEdit) {
        const dismiss = el('button', { type: 'button', className: 'btn btn--ghost btn--small' }, [item.actionType ? 'Ignora' : 'Ok, letto']);
        dismiss.addEventListener('click', async () => { dismiss.disabled = true; await post(`/agenti/messaggi/${item.id}/ignora`); refresh(); });
        actions.appendChild(dismiss);
    }

    const bubble = [el('p', { className: 'agent-msg__text' }, [item.body])];
    if (item.detail) {
        bubble.push(el('blockquote', { className: 'agent-msg__draft' }, [item.detail]));
    }
    if (done && item.resultText) {
        bubble.push(el('div', { className: 'agent-msg__result' }, [icon('circle-check'), item.resultText]));
    }
    if (actions.children.length) bubble.push(actions);

    return el('div', { className: 'agent-msg' + (done ? ' is-done' : '') }, [
        el('span', { className: 'agent-msg__avatar' }, [agentAvatar(item.agentCode, 40)]),
        el('div', { className: 'agent-msg__main' }, [
            el('div', { className: 'agent-msg__head' }, [
                el('span', { className: 'agent-msg__name' }, [item.agentName]),
                el('span', { className: 'agent-msg__role' }, [item.agentRole]),
                el('span', { className: 'agent-msg__time' }, [whenLabel(item.createdAt)]),
            ]),
            el('div', { className: 'agent-msg__bubble' }, bubble),
        ]),
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
        list,
        el('p', { className: 'agent-feed__demo' }, ['Ambiente dimostrativo: gli agenti leggono i dati veri ma non inviano nulla all\'esterno.']),
    ]);
}

registerComponent('agent-feed', renderAgentFeed);
