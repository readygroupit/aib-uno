import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge } from '../accounts-ui.js';
import { actionsRow } from '../work-ui.js';
import { copyLink } from './media-library.js';

// Un file dell'archivio: anteprima grande accanto a cosa contiene; per i
// video i momenti principali con il minuto, per montare senza riguardarlo tutto.
function renderMediaSummary(data) {
    const open = data.openLink && data.openLink.startsWith('/') ? window.location.origin + data.openLink : data.openLink;
    return el('div', { className: 'acc-profile' }, [
        el('section', { className: 'card acc-profile__head' }, [
            el('div', { className: 'acc-profile__identity' }, [
                el('div', { className: 'acc-profile__titles' }, [
                    el('div', { className: 'acc-profile__tags' }, [
                        badge(data.kind === 'video' ? 'Video' : 'Foto', 'neutral', data.kind === 'video' ? 'video' : 'image'),
                        data.status !== 'analizzato' ? badge(data.status, data.status === 'errore' ? 'danger' : 'warning') : null,
                    ].filter(Boolean)),
                    el('h1', { className: 'acc-profile__name' }, [data.name]),
                    el('div', { className: 'acc-profile__facts' }, [
                        data.customer ? el('a', { className: 'acc-fact', href: data.customerHref }, [icon('user'), data.customer]) : null,
                        data.folder ? el('span', { className: 'acc-fact' }, [icon('grid'), data.folder]) : null,
                        data.analyzedAt ? el('span', { className: 'acc-fact' }, [icon('sparkle'), `Descritto il ${data.analyzedAt}`]) : null,
                    ].filter(Boolean)),
                ]),
                el('div', { className: 'acc-profile__actions' }, [
                    open ? el('a', { className: 'btn btn--primary btn--small', href: open, target: '_blank', rel: 'noopener' }, [icon('arrow-right'), 'Apri il file']) : null,
                    data.openLink ? copyLink(data.openLink) : null,
                    actionsRow(data.actions),
                ].filter(Boolean)),
            ]),
            data.error ? el('p', { className: 'acc-note' }, [icon('circle-exclamation'), data.error]) : null,
        ]),
        el('section', { className: 'card acc-section md-detail' }, [
            data.thumb ? el('img', { className: 'md-detail__image', src: data.thumb, alt: data.description || data.name }) : el('div', { className: 'md-detail__image md-card__placeholder' }, [icon('image')]),
            el('div', { className: 'md-detail__text' }, [
                el('p', { className: 'md-detail__desc' }, [data.description || 'Non ancora descritto.']),
                data.tags.length ? el('div', { className: 'acc-chips' }, data.tags.map((t) => el('span', { className: 'acc-chip' }, [t]))) : null,
                data.facts.length ? el('dl', { className: 'md-facts' }, data.facts.flatMap((f) => [el('dt', {}, [f.label]), el('dd', {}, [f.value])])) : null,
            ]),
        ]),
        data.moments.length ? el('section', { className: 'card acc-section' }, [
            el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Momenti del video'])]),
            el('div', { className: 'md-moments' }, data.moments.map((m) => el('div', { className: 'md-moment' }, [
                el('span', { className: 'md-moment__time' }, [m.t || '']),
                el('span', {}, [m.descrizione || '']),
            ]))),
        ]) : null,
    ]);
}

registerComponent('media-summary', renderMediaSummary);
