import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';

// Pagina "Importa dati" (vedi SheetImportComponent / ImportController):
// foglio Google collegato o file Excel, poi il riepilogo scheda per scheda.
function resultView(data) {
    const t = data.totals || {};
    const kpi = (label, value, main) => el('div', { className: 'sheet-import__kpi' + (main ? ' is-main' : '') }, [
        el('span', {}, [label]), el('strong', {}, [String(value ?? 0)]),
    ]);
    const rows = (data.tabs || []).filter((tab) => tab.rows > 0).map((tab) => el('tr', { className: tab.kind === 'Saltata' ? 'is-skipped' : '' }, [
        el('td', {}, [tab.tab]),
        el('td', {}, [tab.kind]),
        el('td', {}, [String(tab.rows)]),
        el('td', {}, [String(tab.nuovi)]),
        el('td', {}, [String(tab.aggiornati)]),
        el('td', {}, [String(tab.invariati)]),
        el('td', {}, [String(tab.scartati)]),
    ]));

    return el('div', { className: 'sheet-import__result' }, [
        el('p', { className: 'sheet-import__done' }, [icon('circle-check'), `Importazione completata dal ${data.source}.`]),
        el('div', { className: 'sheet-import__kpis' }, [
            kpi('Nuovi', t.nuovi, true), kpi('Aggiornati', t.aggiornati), kpi('Già presenti', t.invariati), kpi('Scartati', t.scartati),
        ]),
        el('table', { className: 'sheet-import__table' }, [
            el('thead', {}, [el('tr', {}, ['Scheda', 'Diventa', 'Righe', 'Nuovi', 'Aggiornati', 'Già presenti', 'Scartati'].map((h) => el('th', {}, [h])))]),
            el('tbody', {}, rows),
        ]),
        el('p', { className: 'sheet-import__hint' }, ['Le schede di backup vengono saltate. Scartate: righe di prova o senza dati utili. Puoi rilanciare quando vuoi: aggiorna, non duplica.']),
        el('div', { className: 'sheet-import__links' }, [
            el('a', { className: 'btn btn--secondary btn--small', href: '/contatti' }, ['Apri i contatti']),
            el('a', { className: 'btn btn--secondary btn--small', href: '/pratiche' }, ['Apri le pratiche']),
        ]),
    ]);
}

function renderSheetImport(data) {
    const status = el('div', { className: 'sheet-import__status', hidden: '' });
    const fileInput = el('input', { type: 'file', accept: '.xlsx', className: 'sheet-import__file' });
    const removeDemo = el('input', { type: 'checkbox', id: 'sheet-import-demo' });
    const button = el('button', { type: 'button', className: 'btn btn--primary' }, ['Importa ora']);

    button.addEventListener('click', async () => {
        const file = fileInput.files[0] || null;
        if (!data.connected && !file) {
            status.hidden = false;
            status.textContent = 'Collega prima Google Sheets dalla Configurazione, oppure scegli un file Excel.';
            return;
        }
        if (removeDemo.checked && !window.confirm('Tolgo i dati demo (contatti, pratiche, rimborsi, documenti, messaggi degli agenti) prima di importare. Procedo?')) {
            return;
        }
        const body = new FormData();
        body.append('removeDemo', removeDemo.checked ? '1' : '0');
        if (file) body.append('file', file);

        button.disabled = true;
        status.hidden = false;
        status.className = 'sheet-import__status is-working';
        status.textContent = 'Importazione in corso: leggo le schede e le abbino al gestionale (circa un minuto)...';

        try {
            const res = await fetch(data.url + '/avvia', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '' }, body });
            const result = await res.json();
            status.className = 'sheet-import__status';
            status.textContent = '';
            if (!result.ok) {
                status.textContent = result.error || 'Importazione non riuscita.';
                status.classList.add('is-error');
            } else {
                status.appendChild(resultView(result));
            }
        } catch (e) {
            status.className = 'sheet-import__status is-error';
            status.textContent = 'Il server non ha risposto. Riprova tra poco.';
        }
        button.disabled = false;
    });

    return el('section', { className: 'card sheet-import' }, [
        el('div', { className: 'sheet-import__head' }, [
            el('span', { className: 'sheet-import__eyebrow' }, ['Importazione']),
            el('h2', { className: 'sheet-import__title' }, ['Porta nel gestionale i dati del foglio']),
            el('p', { className: 'sheet-import__text' }, ['Contatti, pratiche legali, rimborsi accettati e documenti caricati: le schede vengono riconosciute da sole anche se cambiano le colonne, i dati vengono puliti e collegati tra loro. Il foglio non viene modificato.']),
        ]),
        el('div', { className: 'sheet-import__source' }, [
            el('div', { className: 'sheet-import__option' }, [
                el('strong', {}, ['Foglio Google collegato']),
                el('span', {}, [data.connected ? (data.sheet || 'Collegato') : 'Non collegato: collegalo da Configurazione']),
            ]),
            el('label', { className: 'sheet-import__option' }, [
                el('strong', {}, ['Oppure un file Excel (.xlsx)']),
                fileInput,
            ]),
        ]),
        el('label', { className: 'sheet-import__check', for: 'sheet-import-demo' }, [removeDemo, 'Togli prima i dati demo (consigliato la prima volta)']),
        el('div', { className: 'sheet-import__actions' }, [button]),
        status,
    ]);
}

registerComponent('sheet-import', renderSheetImport);
