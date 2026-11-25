import { registerComponent, renderComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';

/**
 * Le schede di una pagina (es. il servizio: "Il servizio", "Vendita",
 * "Processo di lavoro"), ognuna con icona e colore. Cosa c'e' dentro lo
 * dicono i riquadri della scheda (descrizione e guida "i"), non la barra.
 *
 * Una scheda mostra dei componenti ('components'), una sezione del form
 * della pagina ('formSection': indice), o tutte e due (prima il form). Il form e' uno solo, condiviso
 * dalle sue schede: "Salva" salva tutto, e un errore in una sezione apre
 * la scheda giusta. La scheda aperta resta nell'indirizzo (#chiave), cosi'
 * dopo un salvataggio si resta dove si era.
 *
 * data: { tabs: [{ key, label, short?, icon, color, count, formSection?, components? }],
 *         form?, selected? }
 */
function readHash() {
    try {
        return decodeURIComponent((window.location.hash || '').replace(/^#/, ''));
    } catch (e) {
        return '';
    }
}

function renderPageTabs(data) {
    const tabs = data.tabs || [];
    const formNode = data.form ? renderComponent(data.form) : null;

    const buttons = [];
    const panels = tabs.map((tab, i) => {
        // Le schede del form: prima il form (spostato qui quando la scheda
        // si apre), poi gli altri riquadri della scheda.
        const formSlot = el('div', { className: 'ptabs__form' });
        const content = el('div', { className: 'ptabs__content' }, [formSlot]);
        (tab.components || []).forEach((c) => {
            const node = renderComponent(c);
            if (node) content.appendChild(node);
        });
        const panel = el('div', {
            className: 'ptabs__panel',
            role: 'tabpanel',
            id: `ptab-${tab.key}`,
            'aria-labelledby': `ptab-btn-${tab.key}`,
        }, [content]);
        panel.hidden = true;

        const button = el('button', {
            type: 'button',
            role: 'tab',
            id: `ptab-btn-${tab.key}`,
            className: `ptabs__tab ptabs__tab--${tab.color || 'petrol'}`,
            'aria-controls': `ptab-${tab.key}`,
            'aria-selected': 'false',
        }, [
            el('span', { className: 'ptabs__icon' }, [icon(tab.icon || 'circle')]),
            el('span', { className: 'ptabs__label' }, [tab.label]),
            el('span', { className: 'ptabs__label ptabs__label--short', 'aria-hidden': 'true' }, [tab.short || tab.label]),
            tab.count !== undefined && tab.count !== null ? el('span', { className: 'ptabs__count' }, [String(tab.count)]) : null,
        ]);
        button.addEventListener('click', () => select(i, true));
        buttons.push(button);

        return { tab, panel, content, formSlot };
    });

    function select(index, remember) {
        panels.forEach((p, j) => {
            const on = j === index;
            p.panel.hidden = !on;
            buttons[j].classList.toggle('is-active', on);
            buttons[j].setAttribute('aria-selected', on ? 'true' : 'false');
            buttons[j].tabIndex = on ? 0 : -1;
        });
        const current = panels[index];
        if (formNode && current && current.tab.formSection !== undefined && current.tab.formSection !== null) {
            current.formSlot.appendChild(formNode);
            if (formNode.showFormSection) formNode.showFormSection(current.tab.formSection);
        }
        if (remember && current) {
            try {
                history.replaceState(history.state, '', `${window.location.pathname}${window.location.search}#${current.tab.key}`);
            } catch (e) {
                // indirizzo non modificabile (anteprima): la scheda resta comunque aperta
            }
        }
    }

    // Frecce sinistra/destra tra le schede, come vuole il ruolo "tablist".
    const bar = el('div', { className: 'ptabs__bar', role: 'tablist', 'aria-label': data.label || 'Sezioni della pagina' }, buttons);
    bar.addEventListener('keydown', (e) => {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        const at = buttons.indexOf(document.activeElement);
        if (at === -1) return;
        const next = (at + (e.key === 'ArrowRight' ? 1 : buttons.length - 1)) % buttons.length;
        select(next, true);
        buttons[next].focus();
    });

    const nav = el('div', { className: 'card ptabs__nav' }, [bar]);
    const root = el('div', { className: 'ptabs' }, [nav, ...panels.map((p) => p.panel)]);

    // Un campo da correggere in un'altra sezione del form: si apre la sua scheda.
    root.addEventListener('form:section', (e) => {
        const at = panels.findIndex((p) => p.tab.formSection === e.detail.index);
        if (at !== -1) select(at, false);
    });

    const byKey = (key) => panels.findIndex((p) => p.tab.key === key);
    // Un link "#chiave" nella pagina (es. "Scadenza e pagamento" -> #dati) apre quella scheda.
    const onHash = () => {
        if (!root.isConnected) {
            window.removeEventListener('hashchange', onHash);
            return;
        }
        const at = byKey(readHash());
        if (at !== -1) {
            select(at, false);
            root.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };
    window.addEventListener('hashchange', onHash);
    let initial = -1;
    if (formNode && formNode.firstErrorSection !== null && formNode.firstErrorSection !== undefined) {
        initial = panels.findIndex((p) => p.tab.formSection === formNode.firstErrorSection);
    }
    // Prima la scheda dell'indirizzo (#servizio: si salva da li' e si resta
    // li'), poi quella suggerita dal server (es. 'processo' dopo aver
    // salvato una fase dalla sua pagina).
    if (initial === -1) initial = byKey(readHash());
    if (initial === -1 && data.selected) initial = byKey(data.selected);
    select(initial === -1 ? 0 : initial, false);

    return root;
}

registerComponent('page-tabs', renderPageTabs);
