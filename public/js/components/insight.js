import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';

// "In sintesi" (vedi InsightComponent): poche osservazioni, in evidenza.
function renderInsight(data) {
    return el('section', { className: 'card insight' }, [
        el('span', { className: 'insight__icon' }, [icon('sparkle')]),
        el('div', { className: 'insight__body' }, [
            el('h2', { className: 'insight__title' }, [data.title]),
            el('ul', { className: 'insight__points' }, (data.points || []).map((p) => el('li', {}, [p]))),
        ]),
    ]);
}

registerComponent('insight', renderInsight);
