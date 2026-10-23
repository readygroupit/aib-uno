import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge } from '../accounts-ui.js';
import { actionsRow } from '../work-ui.js';

const STAGE_TONE = { 'da confermare': 'warning', confermato: 'strong', fatto: 'success', annullato: 'danger' };

// Scheda appuntamento: quando, dove (mappa con un clic), videochiamata,
// chi, cosa portare. In cima l'avviso se qualcuno e' gia' altrove.
function renderAppointment(data) {
    const facts = [
        el('span', { className: 'acc-fact' }, [icon('calendar'), `${data.whenLong} (${data.duration})`]),
        data.location ? el('a', { className: 'acc-fact', href: data.mapsUrl, target: '_blank', rel: 'noopener' }, [icon('location-dot'), data.location]) : null,
        data.travel ? el('span', { className: 'acc-fact' }, [icon('car'), `${data.travel} min di viaggio per andata`]) : null,
        data.customer ? el('a', { className: 'acc-fact', href: data.customerHref }, [icon('user'), data.customer]) : null,
        data.context ? el('a', { className: 'acc-fact', href: data.context.href }, [icon('arrow-right'), data.context.label]) : null,
    ].filter(Boolean);

    const head = el('section', { className: 'card acc-profile__head' }, [
        el('div', { className: 'acc-profile__identity' }, [
            el('span', { className: `cal-kind cal-dot--${data.color}` }, [icon(data.online ? 'video' : (data.away ? 'location-dot' : 'calendar'))]),
            el('div', { className: 'acc-profile__titles' }, [
                el('div', { className: 'acc-profile__tags' }, [
                    badge(data.kindLabel, 'neutral'),
                    badge(data.stage.charAt(0).toUpperCase() + data.stage.slice(1), STAGE_TONE[data.stage] || 'neutral'),
                    el('span', { className: 'acc-muted' }, [data.relative]),
                ]),
                el('h1', { className: 'acc-profile__name' + (data.cancelled ? ' wk-done-title' : '') }, [data.title]),
                el('div', { className: 'acc-profile__facts' }, facts),
            ]),
            actionsRow(data.actions),
        ]),
        data.conflict ? el('div', { className: 'acc-suggestion acc-suggestion--danger' }, [
            el('span', { className: 'acc-suggestion__icon' }, [icon('circle-exclamation')]),
            el('div', {}, [
                el('span', { className: 'acc-suggestion__title' }, ['Sovrapposizione']),
                el('p', { className: 'acc-suggestion__text' }, [data.conflict + ' (viaggio compreso).']),
            ]),
        ]) : null,
        data.online ? el('div', { className: 'cal-meet' }, data.meetUrl
            ? [el('a', { className: 'btn btn--primary', href: data.meetUrl, target: '_blank', rel: 'noopener' }, [icon('video'), 'Entra nella videochiamata']), el('span', { className: 'acc-muted' }, [data.meetUrl])]
            : [icon('video'), el('span', { className: 'acc-muted' }, ['Il link Meet arriva appena l\'appuntamento e\' sul Google Calendar.'])]) : null,
    ]);

    const people = el('section', { className: 'card acc-section' }, [
        el('h2', { className: 'card__title' }, ['Chi c\'e\'']),
        data.people.length
            ? el('div', { className: 'cal-people-list' }, data.people.map((p, i) => el('span', { className: 'cal-people-list__item' }, [
                el('span', { className: `cal-person cal-person--${p.color}` }, [p.initials]),
                p.name,
                i === 0 ? el('span', { className: 'acc-muted' }, [' (responsabile)']) : null,
            ])))
            : el('p', { className: 'acc-empty' }, ['Nessuno assegnato.']),
        data.google ? el('p', { className: 'acc-note' }, [icon('circle-check'), data.google]) : null,
    ]);

    const blocks = [head, people];
    if (data.checklist.length || data.notes) {
        blocks.push(el('section', { className: 'card acc-section' }, [
            el('h2', { className: 'card__title' }, ['Da portare e note']),
            data.checklist.length ? el('ul', { className: 'wk-checklist' }, data.checklist.map((c) => el('li', { className: c.done ? 'is-done' : '' }, [
                el('span', { className: 'wk-checklist__box' }, [c.done ? icon('check') : null]),
                c.text,
            ]))) : null,
            data.notes ? el('p', { className: 'wk-notes' }, [data.notes]) : null,
        ]));
    }

    return el('div', { className: 'acc-profile' }, blocks);
}

registerComponent('appointment-summary', renderAppointment);
