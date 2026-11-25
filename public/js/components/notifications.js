import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { runAction } from '../accounts-ui.js';

// Gli avvisi di chi e' collegato: i nuovi in evidenza, un clic apre
// la cosa a cui si riferiscono (e li segna letti).
function renderNotificationList(data) {
    const readAll = el('button', { type: 'button', className: 'btn btn--secondary btn--small' }, [icon('check'), 'Segna tutti letti']);
    readAll.addEventListener('click', () => runAction(data.readAllAction, { button: readAll }));

    return el('section', { className: 'card acc-section nt-list' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h1', { className: 'card__title' }, [data.title, data.unread ? el('span', { className: 'acc-count' }, [String(data.unread)]) : null]),
            el('div', { className: 'nt-list__actions' }, [
                data.unread ? readAll : null,
                el('a', { className: 'btn btn--ghost btn--small', href: data.preferencesHref }, [icon('pen'), 'Quali ricevere']),
            ]),
        ]),
        data.items.length
            ? el('ul', { className: 'nt-items' }, data.items.map((n) => el('li', {}, [
                el('a', { className: 'nt-item' + (n.unread ? ' is-unread' : ''), href: n.href }, [
                    el('span', { className: 'nt-item__dot' }),
                    el('span', { className: 'nt-item__text' }, [
                        el('span', { className: 'nt-item__title' }, [n.title]),
                        n.body ? el('span', { className: 'nt-item__body' }, [n.body]) : null,
                        el('span', { className: 'nt-item__meta' }, [`${n.typeLabel} · ${n.when}`]),
                    ]),
                ]),
            ])))
            : el('p', { className: 'acc-empty' }, ['Nessun avviso, per ora.']),
    ]);
}

// Per ogni tipo di avviso: nel gestionale e/o per email.
function renderNotificationPreferences(data) {
    const state = {};
    const toggle = (row, channel) => {
        const key = `${row.type}__${channel}`;
        state[key] = row[channel] ? '1' : '0';
        const button = el('button', {
            type: 'button',
            className: 'field__choice' + (row[channel] ? ' is-on' : ''),
            'aria-pressed': row[channel] ? 'true' : 'false',
        }, [channel === 'in_app' ? 'Nel gestionale' : 'Per email']);
        button.addEventListener('click', () => {
            state[key] = state[key] === '1' ? '0' : '1';
            button.classList.toggle('is-on', state[key] === '1');
            button.setAttribute('aria-pressed', state[key] === '1' ? 'true' : 'false');
        });
        return button;
    };
    const save = el('button', { type: 'button', className: 'btn btn--primary' }, ['Salva']);
    save.addEventListener('click', () => runAction(data.action, { button: save, body: state }));

    return el('section', { className: 'card acc-section nt-prefs' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h1', { className: 'card__title' }, [data.title]),
            el('a', { className: 'btn btn--ghost btn--small', href: '/avvisi' }, [icon('bell'), 'I miei avvisi']),
        ]),
        el('ul', { className: 'nt-pref-rows' }, data.rows.map((row) => el('li', { className: 'nt-pref' }, [
            el('span', { className: 'nt-pref__text' }, [
                el('span', { className: 'nt-pref__label' }, [row.label]),
                el('span', { className: 'nt-pref__help' }, [row.help]),
            ]),
            el('span', { className: 'nt-pref__choices' }, [toggle(row, 'in_app'), toggle(row, 'email')]),
        ]))),
        el('div', { className: 'nt-prefs__save' }, [save]),
    ]);
}

registerComponent('notification-list', renderNotificationList);
registerComponent('notification-preferences', renderNotificationPreferences);
