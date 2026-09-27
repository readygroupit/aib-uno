import { registerComponent } from './registry.js';
import { el } from '../dom.js';

const LEVEL_LABELS = { autonomo: 'Autonomo', assistito: 'Assistito', manuale: 'Manuale' };
const LEVEL_DOT = { autonomo: 'moss', assistito: 'brass', manuale: 'petrol' };

function buildAgentRow(agent) {
    const level = agent.level || 'manuale';

    return el('div', { className: 'agent-row' }, [
        el('span', { className: 'agent-row__dot agent-row__dot--' + (LEVEL_DOT[level] || 'petrol') }),
        el('div', { className: 'agent-row__text' }, [
            el('span', { className: 'agent-row__name' }, [agent.name]),
            el('span', { className: 'agent-row__summary' }, [agent.summary || '']),
        ]),
        el('span', { className: 'agent-row__level agent-row__level--' + level }, [LEVEL_LABELS[level] || level]),
    ]);
}

function renderAgentPanel(data) {
    const header = el('div', { className: 'agent-panel__header' }, [
        el('div', {}, [
            el('span', { className: 'agent-panel__title' }, [data.title || '']),
            data.subtitle ? el('div', { className: 'agent-panel__subtitle' }, [data.subtitle]) : null,
        ]),
    ]);

    const rows = (data.agents || []).map(buildAgentRow);
    const children = [header, ...rows];

    if (data.note) {
        children.push(el('div', { className: 'agent-panel__note' }, [data.note]));
    }

    return el('section', { className: 'card agent-panel' }, children);
}

registerComponent('agent-panel', renderAgentPanel);
