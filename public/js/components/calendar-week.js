import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { runAction } from '../accounts-ui.js';
import { dateJump } from '../date-jump.js';

// Calendario della settimana. Gli appuntamenti stanno all'altezza della
// loro ora; se si sovrappongono si affiancano; prima e dopo un'uscita c'e'
// il tempo di viaggio (tratteggiato); un conflitto (la stessa persona in
// due posti) si vede con un'icona. Su telefono diventa un elenco.

const HOUR_PX = 48;

function peopleBadges(people) {
    return el('span', { className: 'cal-people' }, people.slice(0, 3).map((p) => el('span', { className: `cal-person cal-person--${p.color}`, title: p.name }, [p.initials])));
}

function eventBlock(ev, fromMin) {
    const minuteToPx = HOUR_PX / 60;
    const top = (ev.startMin - fromMin) * minuteToPx;
    const height = Math.max(22, (ev.endMin - ev.startMin) * minuteToPx - 2);
    const width = 100 / ev.lanes;
    const left = ev.lane * width;
    const pos = `left:calc(${left}% + 2px);width:calc(${width}% - 4px);`;

    const nodes = [];
    if (ev.travel > 0) {
        const travelPx = ev.travel * minuteToPx;
        nodes.push(el('div', { className: 'cal-travel', style: `${pos}top:${top - travelPx}px;height:${travelPx}px`, title: `Viaggio: ${ev.travel} min` }));
        nodes.push(el('div', { className: 'cal-travel', style: `${pos}top:${top + height + 2}px;height:${travelPx}px`, title: `Ritorno: ${ev.travel} min` }));
    }

    const compact = height < 46;
    const block = el('a', {
        className: `cal-event cal-event--${ev.color}` + (ev.tentative ? ' is-tentative' : '') + (ev.cancelled ? ' is-cancelled' : '') + (compact ? ' is-compact' : ''),
        href: ev.href,
        style: `${pos}top:${top}px;height:${height}px`,
        title: [ev.time, ev.title, ev.customer, ev.location, ev.conflict].filter(Boolean).join('\n'),
    }, [
        el('span', { className: 'cal-event__time' }, [
            ev.time,
            ev.online ? icon('video') : null,
            ev.conflict ? el('span', { className: 'cal-event__warn' }, [icon('circle-exclamation')]) : null,
        ]),
        el('span', { className: 'cal-event__title' }, [ev.title]),
        // Il resto solo se c'e' spazio: prima si legge il titolo.
        height >= 62 && (ev.customer || ev.location) ? el('span', { className: 'cal-event__meta' }, [ev.customer || ev.location]) : null,
        height >= 84 && ev.people.length ? peopleBadges(ev.people) : null,
    ]);
    block.dataset.people = ev.people.map((p) => p.id).join(',');
    nodes.push(block);

    return nodes;
}

function agendaRow(ev) {
    const row = el('a', { className: 'cal-row' + (ev.cancelled ? ' is-cancelled' : ''), href: ev.href }, [
        el('span', { className: `cal-row__dot cal-dot--${ev.color}` }),
        el('span', { className: 'cal-row__time' }, [ev.time]),
        el('span', { className: 'cal-row__body' }, [
            el('span', { className: 'cal-row__title' }, [ev.title, ev.tentative ? el('span', { className: 'badge badge--warning' }, ['da confermare']) : null]),
            el('span', { className: 'cal-row__meta' }, [[ev.kindLabel, ev.customer, ev.location, ev.travel ? `${ev.travel} min di viaggio` : null].filter(Boolean).join(' · ')]),
            ev.conflict ? el('span', { className: 'cal-row__warn' }, [icon('circle-exclamation'), ev.conflict]) : null,
        ]),
        ev.online ? el('span', { className: 'cal-row__icon', title: 'Videochiamata' }, [icon('video')]) : null,
        peopleBadges(ev.people),
    ]);
    row.dataset.people = ev.people.map((p) => p.id).join(',');
    return row;
}

function agendaList(days) {
    if (!days.length) {
        return el('p', { className: 'acc-empty cal-empty' }, ['Nessun appuntamento nei prossimi giorni.']);
    }
    return el('div', { className: 'cal-agenda' }, days.map((d) => el('section', { className: 'cal-agenda__day' + (d.isToday ? ' is-today' : '') }, [
        el('div', { className: 'cal-agenda__head' }, [
            el('span', { className: 'cal-agenda__label' }, [d.label]),
            d.when ? el('span', { className: 'acc-muted' }, [d.when]) : null,
        ]),
        ...d.events.map(agendaRow),
    ])));
}

function weekGrid(week) {
    const fromMin = week.hours.from * 60;
    const totalPx = (week.hours.to - week.hours.from) * HOUR_PX;

    const head = el('div', { className: 'cal-grid__head' }, [
        el('span'),
        ...week.days.map((d) => el('div', { className: 'cal-day-head' + (d.isToday ? ' is-today' : '') + (d.isWeekend ? ' is-weekend' : '') }, [
            el('span', { className: 'cal-day-head__label' }, [d.label]),
            d.tasks ? el('a', { className: 'cal-day-head__tasks', href: '/attivita?tutte=1' }, [icon('check'), d.tasks.label]) : null,
        ])),
    ]);

    const hoursCol = el('div', { className: 'cal-hours', style: `height:${totalPx}px` });
    for (let h = week.hours.from; h < week.hours.to; h++) {
        hoursCol.appendChild(el('span', { className: 'cal-hours__label', style: `top:${(h - week.hours.from) * HOUR_PX}px` }, [`${String(h).padStart(2, '0')}:00`]));
    }

    const columns = week.days.map((d) => {
        const col = el('div', { className: 'cal-col' + (d.isToday ? ' is-today' : '') + (d.isWeekend ? ' is-weekend' : ''), style: `height:${totalPx}px` });
        d.events.forEach((ev) => eventBlock(ev, fromMin).forEach((n) => col.appendChild(n)));
        if (d.isToday && week.nowMin >= fromMin && week.nowMin <= week.hours.to * 60) {
            col.appendChild(el('div', { className: 'cal-now', style: `top:${(week.nowMin - fromMin) * HOUR_PX / 60}px` }));
        }
        // Clic su uno spazio libero: nuovo appuntamento a quell'ora (mezz'ora piu' vicina).
        col.addEventListener('click', (e) => {
            if (e.target !== col) return;
            const minutes = fromMin + Math.round((e.offsetY / HOUR_PX) * 2) * 30;
            const hh = String(Math.floor(minutes / 60)).padStart(2, '0');
            const mm = String(minutes % 60).padStart(2, '0');
            const link = el('a', { href: `/appuntamenti/nuovo?start_at=${d.date}T${hh}:${mm}` });
            col.appendChild(link);
            link.click();
            link.remove();
        });
        return col;
    });

    return el('div', { className: 'cal-grid' }, [
        head,
        el('div', { className: 'cal-grid__body' }, [hoursCol, ...columns]),
    ]);
}

function googleChip(google) {
    if (!google || !google.available) return null;
    if (!google.connected) {
        return el('a', { className: 'cal-google', href: '/configurazione' }, [icon('calendar'), 'Collega Google Calendar']);
    }
    const sync = el('button', { type: 'button', className: 'btn btn--ghost btn--small' }, [icon('arrows-rotate'), 'Sincronizza']);
    sync.addEventListener('click', () => runAction('/appuntamenti/sincronizza', { button: sync }));
    return el('span', { className: 'cal-google is-on' + (google.lastError ? ' has-error' : '') }, [
        icon(google.lastError ? 'circle-exclamation' : 'circle-check'),
        el('span', {}, [google.lastError ? `Google Calendar: ${google.lastError}` : `Google Calendar collegato${google.lastSyncAt ? ' · aggiornato alle ' + google.lastSyncAt.slice(11, 16) : ''}`]),
        sync,
    ]);
}

function renderCalendar(data) {
    const week = data.week;
    const isList = data.view === 'elenco';

    const nav = el('div', { className: 'cal-nav' }, isList ? [] : [
        el('a', { className: 'cal-nav__btn', href: `/appuntamenti?settimana=${week.prevWeek}`, title: 'Settimana prima', 'aria-label': 'Settimana prima' }, [icon('chevron-left')]),
        el('a', { className: 'cal-nav__today' + (week.isCurrentWeek ? ' is-current' : ''), href: '/appuntamenti' }, ['Oggi']),
        el('a', { className: 'cal-nav__btn', href: `/appuntamenti?settimana=${week.nextWeek}`, title: 'Settimana dopo', 'aria-label': 'Settimana dopo' }, [icon('chevron-right')]),
        dateJump((date) => `/appuntamenti?settimana=${date}`, { title: 'Vai alla settimana di un giorno' }),
    ]);

    const header = el('div', { className: 'claim-table__header cal-header' }, [
        el('div', { className: 'cal-header__titles' }, [
            el('h1', { className: 'claim-table__title' }, ['Calendario']),
            el('span', { className: 'cal-header__week' }, [isList ? 'Prossimi 14 giorni' : week.title]),
        ]),
        el('div', { className: 'claim-table__actions' }, [
            nav,
            el('div', { className: 'wk-switch' }, [
                el('a', { className: 'wk-switch__item' + (!isList ? ' is-active' : ''), href: '/appuntamenti' }, ['Settimana']),
                el('a', { className: 'wk-switch__item' + (isList ? ' is-active' : ''), href: '/appuntamenti?vista=elenco' }, ['Elenco']),
            ]),
            el('a', { className: 'btn btn--primary', href: data.createHref }, ['Nuovo appuntamento']),
        ]),
    ]);

    // Filtro per persona: si nascondono gli appuntamenti di chi non e' scelto.
    let selected = null;
    const root = el('div', { className: 'cal' });
    const applyFilter = () => {
        root.querySelectorAll('[data-people]').forEach((node) => {
            const ids = node.dataset.people.split(',').filter(Boolean).map(Number);
            node.classList.toggle('is-filtered', selected !== null && !ids.includes(selected));
        });
    };
    const filterChips = el('div', { className: 'cal-filter' });
    const chip = (label, value) => {
        const node = el('button', { type: 'button', className: 'field__choice' + (selected === value ? ' is-on' : '') }, [label]);
        node.addEventListener('click', () => {
            selected = value;
            filterChips.querySelectorAll('.field__choice').forEach((c) => c.classList.remove('is-on'));
            node.classList.add('is-on');
            applyFilter();
        });
        return node;
    };
    filterChips.appendChild(chip('Tutti', null));
    week.people.forEach((p) => filterChips.appendChild(chip(p.id === data.meId ? `${p.name} (io)` : p.name, p.id)));

    const toolbar = el('div', { className: 'wk-toolbar cal-toolbar' }, [
        el('span', { className: 'cal-summary' }, [
            week.summary,
            week.conflicts > 0 ? el('span', { className: 'cal-summary__warn' }, [icon('circle-exclamation'), ` ${week.conflicts} sovrapposizioni`]) : null,
        ]),
        googleChip(data.google),
        filterChips,
    ]);

    // Su telefono la griglia non ci sta: stessi appuntamenti, in elenco.
    const mobileDays = week.days.filter((d) => d.events.length).map((d) => ({ ...d, events: d.events.filter((ev, i, all) => all.findIndex((x) => x.id === ev.id) === i) }));

    root.append(
        header,
        toolbar,
        isList
            ? el('section', { className: 'card cal-card' }, [agendaList(data.agenda)])
            : el('section', { className: 'card cal-card' }, [
                weekGrid(week),
                el('div', { className: 'cal-mobile' }, [agendaList(mobileDays)]),
            ]),
    );
    return root;
}

registerComponent('calendar-week', renderCalendar);
