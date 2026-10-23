import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';

function renderNotice(data) {
    return el('div', { className: `acc-notice acc-notice--${data.tone || 'info'}`, role: 'status' }, [
        icon(data.tone === 'error' || data.tone === 'warning' ? 'circle-exclamation' : 'circle-check'),
        el('span', {}, [data.text || '']),
    ]);
}

registerComponent('notice', renderNotice);
