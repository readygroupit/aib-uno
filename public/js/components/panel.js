import { registerComponent, renderComponent } from './registry.js';
import { el } from '../dom.js';

// Riquadro che tiene insieme elementi affini (vedi PanelComponent): i
// contatori in griglia, il resto affiancato.
function renderPanel(data) {
    const body = el('div', { className: 'panel__body' });
    let stats = null;
    let grid = null;
    (data.children || []).forEach((child) => {
        const node = renderComponent(child);
        if (!node) return;
        if (child.type === 'stat-box') {
            if (!stats) { stats = el('div', { className: 'stat-grid' }); body.appendChild(stats); }
            stats.appendChild(node);
            grid = null;
            return;
        }
        if (!grid) { grid = el('div', { className: 'panel__grid' }); body.appendChild(grid); }
        grid.appendChild(node);
        stats = null;
    });

    return el('section', { className: 'card panel' }, [
        el('div', { className: 'panel__head' }, [
            data.eyebrow ? el('span', { className: 'panel__eyebrow' }, [data.eyebrow]) : null,
            data.title ? el('h2', { className: 'panel__title' }, [data.title]) : null,
            data.subtitle ? el('p', { className: 'panel__subtitle' }, [data.subtitle]) : null,
        ].filter(Boolean)),
        body,
    ]);
}

registerComponent('panel', renderPanel);
