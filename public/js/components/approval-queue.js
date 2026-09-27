import { registerComponent } from './registry.js';
import { el } from '../dom.js';

// 'Approva' e' un fetch reale (non un link): l'azione scrive davvero
// qualcosa (vedi LeadsController::markContactedAction()/
// DocumentRequestsController::remindAction()) e la riga sparisce dalla
// coda solo se il server conferma - stesso schema CSRF via header gia'
// in uso altrove (window.UNO_CSRF, vedi layout.phtml).
function approve(url, cardEl, button) {
    button.disabled = true;
    button.textContent = '...';

    fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Csrf-Token': window.UNO_CSRF || '' },
    })
        .then((res) => res.json())
        .then((data) => {
            if (data && data.ok) {
                cardEl.classList.add('is-approved');
                setTimeout(() => cardEl.remove(), 250);
            } else {
                button.disabled = false;
                button.textContent = 'Approva';
            }
        })
        .catch(() => {
            button.disabled = false;
            button.textContent = 'Approva';
        });
}

function buildItem(item) {
    const avatar = el('span', {
        className: 'approval-item__avatar',
        style: item.avatarColor ? `background:${item.avatarColor}` : null,
    }, [item.avatarInitials || '']);

    const titleRow = el('div', { className: 'approval-item__title-row' }, [
        el('span', { className: 'approval-item__title' }, [item.title]),
        item.tag ? el('span', { className: 'approval-item__tag approval-item__tag--' + (item.tagColor || 'neutral') }, [item.tag]) : null,
    ]);

    const body = el('div', { className: 'approval-item__body' }, [
        titleRow,
        el('div', { className: 'approval-item__description' }, [item.description || '']),
        item.agentLabel ? el('div', { className: 'approval-item__agent' }, [item.agentLabel]) : null,
    ]);

    const actions = el('div', { className: 'approval-item__actions' });
    if (item.reviewHref) {
        actions.appendChild(el('a', { className: 'btn btn--secondary btn--small', href: item.reviewHref }, ['Rivedi']));
    }

    const card = el('div', { className: 'approval-item' }, [avatar, body, actions]);

    if (item.approveUrl) {
        const approveBtn = el('button', { type: 'button', className: 'btn btn--primary btn--small' }, ['Approva']);
        approveBtn.addEventListener('click', () => approve(item.approveUrl, card, approveBtn));
        actions.appendChild(approveBtn);
    }

    return card;
}

function renderApprovalQueue(data) {
    const header = el('div', { className: 'approval-queue__header' }, [
        el('div', {}, [
            el('span', { className: 'approval-queue__title' }, [data.title || '']),
            data.subtitle ? el('div', { className: 'approval-queue__subtitle' }, [data.subtitle]) : null,
        ]),
        el('span', { className: 'approval-queue__count' }, [`${(data.items || []).length} in attesa`]),
    ]);

    const items = data.items || [];
    const body = items.length > 0
        ? items.map(buildItem)
        : [el('p', { className: 'approval-queue__empty' }, [data.emptyMessage])];

    return el('section', { className: 'card approval-queue' }, [header, ...body]);
}

registerComponent('approval-queue', renderApprovalQueue);
