import { registerComponent } from './registry.js';
import { el } from '../dom.js';

const CATEGORY_LABELS = { view: 'Lettura', create: 'Creazione', edit: 'Modifica', delete: 'Eliminazione' };
const CATEGORY_VARIANTS = { view: 'info', create: 'success', edit: 'warning', delete: 'danger' };

function buildGroup(group) {
    const badges = group.permissions.map((p) =>
        el('span', {
            className: 'badge badge--' + (CATEGORY_VARIANTS[p.category] || 'neutral'),
            title: p.name,
        }, [CATEGORY_LABELS[p.category] || p.name])
    );

    return el('div', { className: 'permission-summary__group' }, [
        el('span', { className: 'permission-summary__domain' }, [group.domain]),
        el('div', { className: 'permission-summary__badges' }, badges),
    ]);
}

function renderPermissionSummary(data) {
    const header = el('div', { className: 'permission-summary__header' }, [
        el('span', { className: 'permission-summary__title' }, [data.title || '']),
        data.subtitle ? el('div', { className: 'permission-summary__subtitle' }, [data.subtitle]) : null,
    ]);

    const groups = data.groups || [];
    const body = groups.length > 0
        ? groups.map(buildGroup)
        : [el('p', { className: 'permission-summary__empty' }, ['Nessun permesso assegnato.'])];

    return el('section', { className: 'card permission-summary' }, [header, ...body]);
}

registerComponent('permission-summary', renderPermissionSummary);
