import { registerComponent } from './registry.js';
import { el } from '../dom.js';

// Larghezza barra in proporzione al PRIMO stadio (il piu' grande per
// costruzione, vedi ShowHomeDashboardTool): calcolata qui, non in PHP -
// stessa scelta gia' fatta per la sparkline in stat-box.js, il server
// manda solo i valori grezzi.
function renderFunnel(data) {
    const stages = data.stages || [];
    const maxValue = stages.length > 0 ? Math.max(...stages.map((s) => Number(s.value) || 0), 1) : 1;

    const header = el('div', { className: 'funnel__header' }, [
        el('div', {}, [
            el('span', { className: 'funnel__title' }, [data.title || '']),
            data.subtitle ? el('div', { className: 'funnel__subtitle' }, [data.subtitle]) : null,
        ]),
        data.summaryLabel
            ? el('div', { className: 'funnel__summary' }, [
                el('span', { className: 'funnel__summary-label' }, [data.summaryLabel]),
                ' ',
                el('strong', { className: 'funnel__summary-value' }, [data.summaryValue || '']),
            ])
            : null,
    ]);

    const stageNodes = stages.map((stage) => {
        const pct = Math.max(4, Math.round(((Number(stage.value) || 0) / maxValue) * 100));
        const bar = el('div', { className: 'funnel__bar-track' }, [
            el('div', { className: 'funnel__bar-fill', style: `width:${pct}%` }),
        ]);

        const content = [
            el('span', { className: 'funnel__stage-label' }, [stage.label]),
            el('span', { className: 'funnel__stage-value' }, [String(stage.value)]),
            bar,
            stage.caption ? el('span', { className: 'funnel__stage-caption' }, [stage.caption]) : null,
        ];

        return stage.href
            ? el('a', { className: 'funnel__stage funnel__stage--clickable', href: stage.href }, content)
            : el('div', { className: 'funnel__stage' }, content);
    });

    return el('section', { className: 'card funnel' }, [
        header,
        el('div', { className: 'funnel__stages' }, stageNodes),
    ]);
}

registerComponent('funnel', renderFunnel);
