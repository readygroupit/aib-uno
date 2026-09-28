import { registerComponent } from './registry.js';
import { el } from '../dom.js';

function buildItem(item) {
    const content = [
        el('span', { className: 'activity-item__dot activity-item__dot--' + (item.dotColor || 'petrol') }),
        el('div', { className: 'activity-item__text' }, [
            el('span', { className: 'activity-item__label' }, [item.label]),
            item.detail ? el('span', { className: 'activity-item__detail' }, [item.detail]) : null,
        ]),
        el('span', { className: 'activity-item__time' }, [item.timestamp]),
    ];

    return item.href
        ? el('a', { className: 'activity-item activity-item--clickable', href: item.href }, content)
        : el('div', { className: 'activity-item' }, content);
}

function renderActivityFeed(data) {
    const header = el('div', { className: 'activity-feed__header' }, [
        el('span', { className: 'activity-feed__title' }, [data.title || '']),
        data.subtitle ? el('div', { className: 'activity-feed__subtitle' }, [data.subtitle]) : null,
    ]);

    const items = data.items || [];
    const body = items.length > 0
        ? items.map(buildItem)
        : [el('p', { className: 'activity-feed__empty' }, [data.emptyMessage])];

    return el('section', { className: 'card activity-feed' }, [header, ...body]);
}

registerComponent('activity-feed', renderActivityFeed);
