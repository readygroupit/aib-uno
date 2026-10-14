import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { agentAvatar } from '../avatars.js';

// Operato agente per agente (vedi AgentActivityComponent): volto, due barre
// (questa settimana / la precedente), completate e in attesa.
function bar(label, value, max, modifier) {
    return el('div', { className: 'agent-activity__bar' }, [
        el('span', { className: 'agent-activity__bar-label' }, [label]),
        el('span', { className: 'agent-activity__track' }, [
            el('span', { className: `agent-activity__fill agent-activity__fill--${modifier}`, style: `width:${value > 0 ? Math.max(4, Math.round((value / max) * 100)) : 0}%` }),
        ]),
        el('strong', { className: 'agent-activity__bar-value' }, [String(value)]),
    ]);
}

function renderAgent(agent, max) {
    const chips = [];
    if (agent.done) chips.push(el('span', { className: 'agent-activity__chip agent-activity__chip--done' }, [`${agent.done} completate`]));
    if (agent.pending) chips.push(el('span', { className: 'agent-activity__chip agent-activity__chip--pending' }, [`${agent.pending} da gestire`]));
    if (!agent.thisWeek) chips.push(el('span', { className: 'agent-activity__chip' }, ['Niente da segnalare']));

    return el('div', { className: 'agent-activity__item' }, [
        el('div', { className: 'agent-activity__who' }, [
            el('span', { className: 'agent-activity__avatar' }, [agentAvatar(agent.code, 40)]),
            el('div', {}, [
                el('span', { className: 'agent-activity__name' }, [agent.name]),
                el('span', { className: 'agent-activity__role' }, [agent.role]),
            ]),
        ]),
        el('div', { className: 'agent-activity__bars' }, [
            bar('Questa sett.', agent.thisWeek, max, 'now'),
            bar('Sett. scorsa', agent.lastWeek, max, 'before'),
        ]),
        el('div', { className: 'agent-activity__chips' }, chips),
    ]);
}

function renderAgentActivity(data) {
    return el('section', { className: 'card agent-activity' }, [
        el('div', { className: 'agent-activity__intro' }, [
            el('h2', { className: 'agent-activity__heading' }, [data.title]),
            data.subtitle ? el('p', { className: 'agent-activity__subtitle' }, [data.subtitle]) : null,
        ].filter(Boolean)),
        el('div', { className: 'agent-activity__grid' }, (data.agents || []).map((agent) => renderAgent(agent, data.max || 1))),
    ]);
}

registerComponent('agent-activity', renderAgentActivity);
