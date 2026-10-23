import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge, toast } from '../accounts-ui.js';
import { actionsRow } from '../work-ui.js';
import { openInPanel } from './hero.js';

// Archivio foto e video: si cerca a parole, si vedono le anteprime con il
// perche' sono state scelte, si apre il file o se ne copia il link (quello
// che oggi si fa a mano, un link alla volta).

function absolute(link) {
    return link && link.startsWith('/') ? window.location.origin + link : link;
}

export function copyLink(link) {
    const button = el('button', { type: 'button', className: 'btn btn--ghost btn--small', title: 'Copia il link' }, [icon('copy'), 'Link']);
    button.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        try {
            await navigator.clipboard.writeText(absolute(link));
            toast('Link copiato.');
        } catch (err) {
            window.prompt('Copia da qui:', absolute(link));
        }
    });
    return button;
}

function card(c) {
    return el('article', { className: 'md-card' }, [
        el('a', { className: 'md-card__thumb', href: c.href }, [
            c.thumb ? el('img', { src: c.thumb, alt: c.description || c.name, loading: 'lazy' }) : el('span', { className: 'md-card__placeholder' }, [icon(c.kind === 'video' ? 'video' : 'image')]),
            c.kind === 'video' ? el('span', { className: 'md-card__video' }, [icon('video'), c.duration || 'video']) : null,
            c.status !== 'analizzato' ? el('span', { className: 'md-card__status' }, [c.status]) : null,
        ]),
        el('div', { className: 'md-card__body' }, [
            c.reason ? el('p', { className: 'md-card__reason' }, [icon('sparkle'), c.reason]) : null,
            el('p', { className: 'md-card__desc' }, [c.description || c.name]),
            el('div', { className: 'md-card__meta' }, [
                c.customer ? badge(c.customer, 'neutral') : null,
                c.people === 0 ? badge('senza persone', 'info') : null,
            ].filter(Boolean)),
            el('div', { className: 'md-card__actions' }, [
                c.openLink ? el('a', { className: 'btn btn--secondary btn--small', href: absolute(c.openLink), target: '_blank', rel: 'noopener' }, [icon('arrow-right'), 'Apri']) : null,
                c.openLink ? copyLink(c.openLink) : null,
            ].filter(Boolean)),
        ]),
    ]);
}

function filterLink(label, params, active) {
    const query = new URLSearchParams(params);
    return el('a', { className: 'md-filter' + (active ? ' is-active' : ''), href: '/materiali' + (query.toString() ? `?${query}` : '') }, [label]);
}

function renderMediaLibrary(data) {
    const s = data.status;
    const input = el('input', { className: 'md-search__input', type: 'search', name: 'q', value: data.query || '', placeholder: 'Es. foto per una comunione senza persone, video del locale di Gino...', 'aria-label': 'Cosa cerchi' });
    const form = el('form', { className: 'md-search', role: 'search' }, [
        icon('search'),
        input,
        el('button', { type: 'submit', className: 'btn btn--primary' }, ['Cerca']),
    ]);
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const q = input.value.trim();
        openInPanel('/materiali' + (q ? `?q=${encodeURIComponent(q)}` : ''));
    });

    const base = data.query ? { q: data.query } : {};
    const f = data.filters || {};
    const filters = el('div', { className: 'md-filters' }, [
        filterLink('Tutto', base, !f.kind && !f.noPeople),
        filterLink('Solo foto', { ...base, tipo: 'immagine' }, f.kind === 'immagine'),
        filterLink('Solo video', { ...base, tipo: 'video' }, f.kind === 'video'),
        filterLink('Senza persone', { ...base, persone: '0' }, !!f.noPeople),
    ]);

    const status = el('p', { className: 'md-status' }, [
        `${s.total} file`,
        s.pending ? ` · ${s.pending} da descrivere` : '',
        s.failed ? ` · ${s.failed} non riusciti` : '',
        s.sources && s.sources.length ? ` · da ${s.sources.join(' e ')}` : '',
        s.syncedAt ? ` · letto ${s.syncedAt}` : '',
    ].join(''));

    return el('div', { className: 'acc-board md-library' }, [
        el('div', { className: 'claim-table__header' }, [
            el('h1', { className: 'claim-table__title' }, [data.title]),
            actionsRow(data.actions),
        ]),
        el('section', { className: 'card md-search-card' }, [form, filters, status]),
        data.understood ? el('p', { className: 'md-understood' }, [icon('sparkle'), `Ho cercato: ${data.understood}`]) : null,
        data.cards.length
            ? el('div', { className: 'md-grid' }, data.cards.map(card))
            : el('section', { className: 'card acc-section' }, [el('p', { className: 'acc-empty' }, [
                data.empty || 'Nessun file.',
                data.setupHref ? el('a', { href: data.setupHref }, [' Vai alla Configurazione']) : null,
            ])]),
    ]);
}

registerComponent('media-library', renderMediaLibrary);
