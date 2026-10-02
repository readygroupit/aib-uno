import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { agentAvatar } from '../avatars.js';

const AUTONOMY = [
    ['suggest', 'Suggerisce', 'Ti scrive cosa farebbe, senza fare nulla.'],
    ['approval', 'Chiede approvazione', 'Prepara l\'azione e aspetta il tuo OK.'],
    ['auto', 'Agisce da solo', 'Esegue e poi ti racconta cosa ha fatto.'],
];

function send(url, body = {}) {
    return fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '', 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
        .then((r) => r.json())
        .catch(() => ({ ok: false }));
}

function agentCard(agent, schedules, canEdit) {
    const state = { ...agent };
    const status = el('span', { className: 'agent-card__status', role: 'status' });
    const runResult = el('div', { className: 'agent-card__run-result', role: 'status' });
    let statusTimer = null;

    async function save(fields) {
        const result = await send('/agenti/' + agent.id, fields);
        status.textContent = result.ok ? 'Salvato' : 'Non salvato';
        clearTimeout(statusTimer);
        statusTimer = setTimeout(() => { status.textContent = ''; }, 1800);
    }

    const toggle = el('button', {
        type: 'button',
        className: 'toggle' + (state.enabled ? ' is-on' : ''),
        role: 'switch',
        'aria-checked': String(state.enabled),
        'aria-label': 'Agente attivo',
        disabled: canEdit ? null : '',
    }, [el('span', { className: 'toggle__thumb' })]);
    toggle.addEventListener('click', () => {
        state.enabled = !state.enabled;
        toggle.classList.toggle('is-on', state.enabled);
        toggle.setAttribute('aria-checked', String(state.enabled));
        card.classList.toggle('is-off', !state.enabled);
        save({ is_enabled: state.enabled });
    });

    const scheduleSelect = el('select', { className: 'field__input agent-card__select', disabled: canEdit ? null : '' },
        Object.entries(schedules).map(([value, label]) => el('option', { value, selected: value === state.schedule ? 'selected' : null }, [label])));
    scheduleSelect.addEventListener('change', () => save({ schedule: scheduleSelect.value }));

    const autonomyHint = el('p', { className: 'agent-card__hint' });
    const autonomyButtons = AUTONOMY.map(([value, label]) => {
        const button = el('button', { type: 'button', className: 'agent-card__seg', disabled: canEdit ? null : '' }, [label]);
        button.addEventListener('click', () => { select(value); save({ autonomy: value }); });
        button.dataset.value = value;
        return button;
    });
    function select(value) {
        state.autonomy = value;
        autonomyButtons.forEach((b) => b.classList.toggle('is-active', b.dataset.value === value));
        autonomyHint.textContent = AUTONOMY.find(([v]) => v === value)[2];
    }
    select(state.autonomy);

    const rule = el('textarea', { className: 'field__input field__input--area agent-card__rule', rows: '2', disabled: canEdit ? null : '', placeholder: 'Descrivi in italiano cosa deve fare...' });
    rule.value = state.rule || '';
    rule.addEventListener('blur', () => { if (rule.value !== (state.rule || '')) { state.rule = rule.value; save({ rule_text: rule.value }); } });

    const run = el('button', { type: 'button', className: 'btn btn--primary btn--small', disabled: canEdit ? null : '' }, ['Esegui ora']);
    run.addEventListener('click', async () => {
        run.disabled = true;
        run.textContent = 'Al lavoro...';
        runResult.textContent = '';
        const result = await send(`/agenti/${agent.id}/esegui`);
        run.disabled = false;
        run.textContent = 'Esegui ora';
        if (!result.ok) { runResult.textContent = 'Non sono riuscito a eseguirlo.'; return; }
        runResult.textContent = '';
        if (!state.enabled) { runResult.appendChild(document.createTextNode('E\' spento: accendilo per farlo lavorare.')); return; }
        if (result.created === 0) { runResult.appendChild(document.createTextNode('Nessuna novita\': ha gia\' segnalato tutto.')); return; }
        runResult.appendChild(document.createTextNode(`${state.name} ti ha scritto ${result.created === 1 ? 'un messaggio' : result.created + ' messaggi'}. `));
        runResult.appendChild(el('a', { href: '/' }, ['Vai alla home']));
    });

    const card = el('section', { className: 'card agent-card' + (state.enabled ? '' : ' is-off') }, [
        el('div', { className: 'agent-card__head' }, [
            el('span', { className: 'agent-card__avatar' }, [agentAvatar(agent.code, 56)]),
            el('div', { className: 'agent-card__who' }, [
                el('h3', { className: 'agent-card__name' }, [agent.name]),
                el('span', { className: 'agent-card__role' }, [agent.role]),
            ]),
            toggle,
        ]),
        el('p', { className: 'agent-card__bio' }, [agent.bio]),
        el('label', { className: 'agent-card__label' }, ['Quando agisce']),
        scheduleSelect,
        el('label', { className: 'agent-card__label' }, ['Quanta autonomia ha']),
        el('div', { className: 'agent-card__segs' }, autonomyButtons),
        autonomyHint,
        el('label', { className: 'agent-card__label' }, ['Regola']),
        rule,
        el('div', { className: 'agent-card__foot' }, [run, status]),
        runResult,
    ]);

    return card;
}

function renderAgentsBoard(data) {
    const note = el('span', { className: 'agents-board__note', role: 'status' });
    const restart = el('button', { type: 'button', className: 'btn btn--secondary btn--small', disabled: data.canEdit ? null : '' }, ['Ricomincia la demo']);
    restart.addEventListener('click', async () => {
        restart.disabled = true;
        const result = await send('/agenti/riavvia');
        restart.disabled = false;
        note.textContent = result.ok ? 'Fatto: il flusso in home e\' stato rigenerato.' : 'Non sono riuscito a ricominciare.';
    });

    return el('div', { className: 'agents-board' }, [
        el('section', { className: 'card agents-board__intro' }, [
            el('div', {}, [
                el('h2', { className: 'agents-board__title' }, ['Agenti']),
                el('p', { className: 'agents-board__sub' }, ['Colleghi digitali che lavorano sui tuoi dati. Decidi cosa fanno, quando e quanta autonomia hanno: le azioni delicate restano sempre a te.']),
            ]),
            el('div', { className: 'agents-board__tools' }, [restart, note]),
        ]),
        el('div', { className: 'agents-board__grid' }, data.agents.map((a) => agentCard(a, data.schedules, data.canEdit))),
        el('p', { className: 'agents-board__demo' }, ['Ambiente dimostrativo: gli agenti leggono i dati veri e le azioni vengono registrate nel gestionale, ma non viene inviato nulla all\'esterno. "Quando agisce" e "Regola" sono salvati ma non ancora applicati.']),
    ]);
}

registerComponent('agents-board', renderAgentsBoard);
