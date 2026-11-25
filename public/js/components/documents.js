import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { runAction, toast, reloadCurrentScreen } from '../accounts-ui.js';
import { askText } from '../work-ui.js';

// I Documenti: le cartelle e i file del Drive dal gestionale. Il file vero
// si apre sul Drive; qui si naviga, si crea, si carica, si cerca.
const KIND_ICON = { immagine: 'image', video: 'video', pdf: 'file-invoice', documento: 'file-invoice', foglio: 'file-invoice', presentazione: 'file-invoice', archivio: 'download', file: 'file-invoice' };

function uploadButton(action, label = 'Carica file') {
    const input = el('input', { type: 'file', multiple: true, className: 'dc-file-input' });
    const button = el('button', { type: 'button', className: 'btn btn--primary btn--small' }, [icon('download'), label]);
    button.addEventListener('click', () => input.click());
    input.addEventListener('change', async () => {
        button.disabled = true;
        let ok = 0;
        for (const file of [...input.files]) {
            const body = new FormData();
            body.append('file', file);
            try {
                const response = await fetch(action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '' }, body });
                const result = await response.json();
                if (result.ok) ok++; else toast(`${file.name}: ${result.message}`, 'error');
            } catch (e) {
                toast(`${file.name}: non caricato (troppo grande o connessione interrotta).`, 'error');
            }
        }
        button.disabled = false;
        if (ok) toast(ok === 1 ? 'Caricato sul Drive.' : `${ok} file caricati sul Drive.`);
        await reloadCurrentScreen();
    });
    return el('span', {}, [button, input]);
}

function searchBox(action, value = '') {
    const input = el('input', { type: 'search', className: 'field__input dc-search__input', placeholder: 'Cerca nei documenti: "preventivo firmato", "logo"...', value });
    const form = el('form', { className: 'dc-search', action, method: 'get' }, [input, el('button', { type: 'submit', className: 'btn btn--secondary btn--small' }, [icon('search'), 'Cerca'])]);
    input.name = 'q';
    return form;
}

function fileRow(f, extra = null) {
    const actions = [];
    if (f.open) actions.push(el('a', { className: 'btn btn--secondary btn--small', href: f.open, target: '_blank', rel: 'noopener' }, ['Apri']));
    if (f.trash) {
        const trash = el('button', { type: 'button', className: 'btn btn--ghost btn--small', title: 'Nel cestino del Drive' }, ['Elimina']);
        trash.addEventListener('click', () => runAction(f.trash, { button: trash, confirm: `Mettere "${f.name}" nel cestino del Drive? Si recupera da li' per 30 giorni.` }));
        actions.push(trash);
    }
    return el('li', { className: 'dc-row' }, [
        icon(KIND_ICON[f.kind] || 'file-invoice'),
        el('div', { className: 'dc-row__text' }, [
            el('a', { className: 'dc-row__name', href: f.href }, [f.name]),
            extra ? el('span', { className: 'dc-row__path' }, [extra]) : null,
            f.description ? el('span', { className: 'dc-row__desc' }, [f.description]) : null,
            el('span', { className: 'dc-row__meta' }, [[f.kind, f.size, f.when, f.who].filter(Boolean).join(' · ')]),
        ]),
        el('div', { className: 'dc-row__actions' }, actions),
    ]);
}

function renderBrowser(data) {
    if (!data.ready) {
        return el('section', { className: 'card acc-section' }, [
            el('h1', { className: 'card__title' }, ['Documenti']),
            el('p', { className: 'acc-empty' }, ['Collega Google Drive dalla Configurazione (con la cartella principale): da li\' arrivano cartelle e file.']),
        ]);
    }
    const head = [];
    if (data.newFolderAction) {
        const b = el('button', { type: 'button', className: 'btn btn--secondary btn--small' }, [icon('plus'), 'Cartella']);
        b.addEventListener('click', () => askText({ title: 'Nuova cartella', label: 'Nome della cartella', placeholder: 'Es. 06 Foto del cliente', confirmLabel: 'Crea', onSubmit: (name) => name && runAction(data.newFolderAction, { body: { reason: name } }) }));
        head.push(b);
    }
    if (data.uploadAction) head.push(uploadButton(data.uploadAction));
    if (data.renameAction) {
        const b = el('button', { type: 'button', className: 'btn btn--ghost btn--small' }, [icon('pen'), 'Rinomina']);
        b.addEventListener('click', () => askText({ title: 'Rinomina la cartella', label: 'Nuovo nome', placeholder: data.title, confirmLabel: 'Rinomina', onSubmit: (name) => name && runAction(data.renameAction, { body: { reason: name } }) }));
        head.push(b);
    }
    if (data.trashAction) {
        const b = el('button', { type: 'button', className: 'btn btn--ghost btn--small' }, ['Elimina']);
        b.addEventListener('click', () => runAction(data.trashAction, { button: b, confirm: `Mettere "${data.title}" nel cestino del Drive, con tutto quello che contiene? Si recupera da li' per 30 giorni.` }));
        head.push(b);
    }
    if (data.webLink) head.push(el('a', { className: 'btn btn--ghost btn--small', href: data.webLink, target: '_blank', rel: 'noopener' }, ['Apri sul Drive']));
    if (data.syncAction) {
        const b = el('button', { type: 'button', className: 'btn btn--ghost btn--small', title: 'Leggi subito le novita\' del Drive' }, [icon('arrows-rotate')]);
        b.addEventListener('click', () => runAction(data.syncAction, { button: b }));
        head.push(b);
    }

    return el('div', { className: 'dc-browser' }, [
        el('section', { className: 'card acc-section' }, [
            el('nav', { className: 'dc-crumbs' }, data.crumbs.flatMap((c, i) => [i ? el('span', { className: 'dc-crumbs__sep' }, ['/']) : null, el('a', { href: c.href }, [c.name])]).filter(Boolean)),
            el('div', { className: 'acc-section__head' }, [el('h1', { className: 'card__title' }, [data.title]), el('div', { className: 'dc-actions' }, head)]),
            data.readOnlyNote ? el('p', { className: 'acc-share__test' }, [icon('circle-exclamation'), data.readOnlyNote]) : null,
            searchBox(data.searchAction),
        ]),
        el('section', { className: 'card acc-section' }, [
            data.folders.length ? el('div', { className: 'dc-folders' }, data.folders.map((f) => el('a', { className: 'dc-folder', href: f.href }, [
                el('span', { className: 'dc-folder__name' }, [f.name]),
                el('span', { className: 'dc-folder__count' }, [f.count === 1 ? '1 file' : `${f.count} file`]),
            ]))) : null,
            data.files.length
                ? el('ul', { className: 'dc-rows' }, data.files.map((f) => fileRow(f)))
                : (!data.folders.length ? el('p', { className: 'acc-empty' }, ['Cartella vuota.']) : null),
        ]),
    ]);
}

function renderSummary(data) {
    let create = null;
    if (data.createAction) {
        create = el('button', { type: 'button', className: 'btn btn--primary btn--small' }, [icon('plus'), data.createLabel]);
        create.addEventListener('click', () => runAction(data.createAction, { button: create }));
    }
    return el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, [data.title]),
            el('div', { className: 'dc-actions' }, [
                data.folderHref ? el('a', { className: 'btn btn--secondary btn--small', href: data.folderHref }, ['Apri']) : null,
                data.webLink ? el('a', { className: 'btn btn--ghost btn--small', href: data.webLink, target: '_blank', rel: 'noopener' }, ['Sul Drive']) : null,
                create,
            ]),
        ]),
        data.folders.length
            ? el('div', { className: 'dc-folders' }, data.folders.map((f) => el('a', { className: 'dc-folder', href: f.href }, [
                el('span', { className: 'dc-folder__name' }, [f.name]),
                el('span', { className: 'dc-folder__count' }, [f.count === 1 ? '1 file' : `${f.count} file`]),
            ])))
            : el('p', { className: 'acc-empty' }, [data.folderHref ? (data.files ? `${data.files} file nella cartella.` : 'Cartella vuota.') : 'Nessuna cartella sul Drive per ora.']),
    ]);
}

function renderResults(data) {
    return el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h1', { className: 'card__title' }, [`Documenti: ${data.query}`]),
            el('a', { className: 'btn btn--ghost btn--small', href: data.browseHref }, ['Tutte le cartelle']),
        ]),
        searchBox('/documenti/cerca', data.query),
        data.results.length
            ? el('ul', { className: 'dc-rows' }, data.results.map((f) => fileRow(f, f.path)))
            : el('p', { className: 'acc-empty' }, ['Niente con queste parole. Le descrizioni e le parole chiave dei file aiutano a ritrovarli.']),
    ]);
}

function renderFile(data) {
    return el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h1', { className: 'card__title' }, [icon(KIND_ICON[data.kind] || 'file-invoice'), ' ', data.name]),
            el('div', { className: 'dc-actions' }, [
                data.open ? el('a', { className: 'btn btn--primary btn--small', href: data.open, target: '_blank', rel: 'noopener' }, ['Apri sul Drive']) : null,
                data.folderHref ? el('a', { className: 'btn btn--ghost btn--small', href: data.folderHref }, ['La cartella']) : null,
            ]),
        ]),
        el('p', { className: 'kn-hint' }, [[data.path, data.kind, data.size, data.origin].filter(Boolean).join(' · ')]),
        el('p', { className: 'kn-hint' }, ['Il file sta sul Drive; qui le informazioni per ritrovarlo (anche dall\'assistente).']),
    ]);
}

registerComponent('document-browser', renderBrowser);
registerComponent('document-summary', renderSummary);
registerComponent('document-results', renderResults);
registerComponent('document-file', renderFile);
