import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';

// Il modello di una commessa: le fasi in ordine. Ogni commessa nuova di
// questo tipo le copia come attivita', con le date calcolate dall'inizio.
function renderTypeSteps(data) {
    return el('div', { className: 'acc-profile' }, [
        el('section', { className: 'card acc-profile__head' }, [
            el('div', { className: 'acc-profile__identity' }, [
                el('div', { className: 'acc-profile__titles' }, [
                    el('h1', { className: 'acc-profile__name' }, [data.name]),
                    el('div', { className: 'acc-profile__facts' }, [
                        el('span', { className: 'acc-fact' }, [icon('check'), `${data.steps.length} fasi`]),
                        data.days ? el('span', { className: 'acc-fact' }, [icon('calendar'), `circa ${data.days} giorni`]) : null,
                        data.total ? el('span', { className: 'acc-fact' }, [icon('clock'), `${data.total} di lavoro`]) : null,
                    ].filter(Boolean)),
                ]),
                el('div', { className: 'acc-profile__actions' }, [
                    el('a', { className: 'btn btn--primary', href: data.newOrderHref }, ['Nuova commessa di questo tipo']),
                ]),
            ]),
            data.description ? el('p', { className: 'wk-notes' }, [data.description]) : null,
        ]),
        el('section', { className: 'card acc-section' }, [
            el('div', { className: 'acc-section__head' }, [
                el('h2', { className: 'card__title' }, ['Fasi del modello']),
                el('a', { className: 'btn btn--ghost btn--small', href: data.addHref }, [icon('plus'), 'Fase']),
            ]),
            data.steps.length
                ? el('ol', { className: 'wk-type-steps' }, data.steps.map((s) => el('li', {}, [
                    el('a', { className: 'wk-type-step', href: s.href }, [
                        el('span', { className: 'wk-type-step__num' }, [String(s.position ?? '')]),
                        el('span', { className: 'wk-type-step__title' }, [s.title]),
                        el('span', { className: 'wk-task__chips' }, [
                            s.day ? el('span', { className: 'wk-chip' }, [icon('calendar'), s.day]) : null,
                            s.estimate ? el('span', { className: 'wk-chip' }, [icon('clock'), s.estimate]) : null,
                            s.checklist ? el('span', { className: 'wk-chip' }, [icon('check'), `${s.checklist} punti`]) : null,
                            s.waits ? el('span', { className: 'wk-chip wk-chip--warning' }, [icon('clock'), 'aspetta il cliente']) : null,
                        ].filter(Boolean)),
                    ]),
                ])))
                : el('p', { className: 'acc-empty' }, ['Nessuna fase ancora. ', el('a', { href: data.addHref }, ['Aggiungi la prima'])]),
        ]),
    ]);
}

registerComponent('type-steps', renderTypeSteps);
