import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge, actionButton } from '../accounts-ui.js';

function renderTaskSummary(data) {
    return el('div', { className: 'acc-profile' }, [
        el('section', { className: 'card acc-profile__head' }, [
            el('div', { className: 'acc-profile__identity' }, [
                el('div', { className: 'acc-profile__titles' }, [
                    el('div', { className: 'acc-profile__tags' }, [
                        badge(data.done ? 'Fatta' : 'Da fare', data.done ? 'success' : 'strong'),
                        ...(data.tags || []).map((t) => badge(t.label, t.tone)),
                    ]),
                    el('h1', { className: 'acc-profile__name' + (data.done ? ' wk-done-title' : '') }, [data.title]),
                    data.context ? el('a', { className: 'acc-fact wk-context', href: data.context.href }, [icon('arrow-right'), data.context.label]) : null,
                    el('div', { className: 'acc-profile__facts' }, (data.facts || []).map((f) => el('span', { className: 'acc-fact' }, [icon(f.icon), f.text]))),
                ]),
                el('div', { className: 'acc-profile__actions' }, [
                    actionButton(data.done ? 'Riapri' : 'Fatta', data.toggleAction, { variant: data.done ? 'secondary' : 'primary', iconName: data.done ? null : 'check' }),
                ]),
            ]),
            data.description ? el('p', { className: 'wk-notes' }, [data.description]) : null,
            data.checklist && data.checklist.length ? el('ul', { className: 'wk-checklist' }, data.checklist.map((c) => el('li', { className: c.done ? 'is-done' : '' }, [
                el('span', { className: 'wk-checklist__box' }, [c.done ? icon('check') : null]),
                c.text,
            ]))) : null,
        ]),
    ]);
}

registerComponent('task-summary', renderTaskSummary);
