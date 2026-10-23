import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { refreshCurrentEntry } from './hero.js';
import { toast } from '../accounts-ui.js';
import { openModal } from '../modal.js';

// "Cosa hai fatto?": per ogni riga un tocco (fatto, in parte, no) e i
// minuti da correggere con - e +. In fondo le cose fuori programma.

const STATES = [
    { value: 'fatto', label: 'Fatto' },
    { value: 'in parte', label: 'In parte' },
    { value: 'no', label: 'No' },
];

function formatMinutes(m) {
    const h = Math.floor(m / 60);
    const r = m % 60;
    if (!h) return `${r} min`;
    return r ? `${h} h ${r}` : `${h} h`;
}

function minutesControl(state) {
    const value = el('span', { className: 'ci-minutes__value' }, [formatMinutes(state.minutes)]);
    const change = (delta) => {
        state.minutes = Math.max(0, Math.min(720, state.minutes + delta));
        value.textContent = formatMinutes(state.minutes);
    };
    return el('div', { className: 'ci-minutes' }, [
        el('button', { type: 'button', className: 'ci-minutes__btn', 'aria-label': 'Meno 15 minuti', onClick: () => change(-15) }, [icon('minus')]),
        value,
        el('button', { type: 'button', className: 'ci-minutes__btn', 'aria-label': 'Piu 15 minuti', onClick: () => change(15) }, [icon('plus')]),
    ]);
}

function rowNode(row, state) {
    const node = el('div', { className: 'ci-row' + (state.state ? ` is-${state.state.replace(' ', '-')}` : '') });
    const choices = el('div', { className: 'ci-states' }, STATES.map((s) => {
        const button = el('button', { type: 'button', className: 'ci-state' + (state.state === s.value ? ' is-on' : '') }, [s.label]);
        button.addEventListener('click', () => {
            state.state = s.value;
            choices.querySelectorAll('.ci-state').forEach((b) => b.classList.remove('is-on'));
            button.classList.add('is-on');
            node.className = `ci-row is-${s.value.replace(' ', '-')}`;
        });
        return button;
    }));

    node.append(
        el('div', { className: 'ci-row__text' }, [
            el('span', { className: 'ci-row__title' }, [row.kind === 'appointment' ? icon('calendar') : null, row.title]),
            el('span', { className: 'ci-row__meta' }, [[row.context, row.estimate ? `previsto ${row.estimate}` : null].filter(Boolean).join(' · ')]),
        ]),
        choices,
        minutesControl(state),
    );
    return node;
}

function renderCheckin(data) {
    const states = data.rows.map((row) => ({ ...row, state: row.state }));
    const extras = [];

    const list = el('div', { className: 'ci-list' }, data.rows.length
        ? data.rows.map((row, i) => rowNode(row, states[i]))
        : [el('p', { className: 'acc-empty' }, ['Niente in programma per questo pezzo di giornata: aggiungi qui sotto quello che hai fatto.'])]);

    const extraList = el('div', { className: 'ci-list' });
    const addExtra = () => {
        const state = { title: '', minutes: 30, state: 'fatto', taskId: null, customerId: null };
        extras.push(state);
        const input = el('input', { className: 'field__input', type: 'text', placeholder: 'Es. telefonata con un cliente, preventivo, imprevisto...' });
        input.addEventListener('input', () => { state.title = input.value; });
        extraList.appendChild(el('div', { className: 'ci-row ci-row--extra is-fatto' }, [input, minutesControl(state)]));
        input.focus();
    };

    const save = el('button', { type: 'button', className: 'btn btn--primary' }, ['Salva']);
    save.addEventListener('click', async () => {
        const rows = [...states, ...extras.filter((x) => x.title.trim() !== '')]
            .filter((x) => x.state === 'fatto' || x.state === 'in parte')
            .map((x) => ({ taskId: x.taskId, title: x.title, minutes: x.minutes, state: x.state, customerId: x.customerId }));
        save.disabled = true;
        try {
            const response = await fetch(data.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '' },
                body: JSON.stringify({ date: data.date, period: data.period, rows }),
            });
            const result = await response.json();
            if (!result.ok) {
                save.disabled = false;
                toast(result.message || 'Non salvato.', 'error');
                return;
            }
            document.querySelector('.wl-nudge')?.remove();
            refreshCurrentEntry(result.components || []);
        } catch (e) {
            save.disabled = false;
            openModal({ title: 'Errore di comunicazione', tone: 'error', body: "Non e' stato possibile contattare il server. Riprova." });
        }
    });

    return el('div', { className: 'pl' }, [
        el('section', { className: 'card pl-hero ci-hero' }, [
            el('div', { className: 'pl-hero__text' }, [
                el('h1', { className: 'pl-hero__title' }, [data.title]),
                el('p', { className: 'pl-hero__lead' }, [data.lead]),
            ]),
        ]),
        el('section', { className: 'card acc-section' }, [
            el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Dal programma'])]),
            list,
        ]),
        el('section', { className: 'card acc-section' }, [
            el('div', { className: 'acc-section__head' }, [
                el('h2', { className: 'card__title' }, ['Fuori programma']),
                el('button', { type: 'button', className: 'btn btn--ghost btn--small', onClick: addExtra }, [icon('plus'), 'Aggiungi']),
            ]),
            extraList,
            el('p', { className: 'acc-muted' }, ['Telefonate lunghe, imprevisti, richieste arrivate in giornata: segnarle spiega dove e\' andato il tempo.']),
        ]),
        el('div', { className: 'ci-actions' }, [
            save,
            el('span', { className: 'acc-muted' }, [data.privacy]),
        ]),
    ]);
}

registerComponent('check-in', renderCheckin);
