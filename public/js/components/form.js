import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { refreshCurrentEntry } from './hero.js';
import { openModal } from '../modal.js';

// Submit via fetch, non piu' nativo: un <form> vero che si manda da solo
// causava un giro pagina COMPLETO (il browser naviga per davvero), che
// perdeva tutto lo stato del prompt (storico, posizione nella sidebar) -
// esattamente il problema di incoerenza che stiamo evitando dappertutto
// altrove. Il server risponde comunque con lo stesso formato JSON di
// qualunque altra richiesta AJAX (vedi AbstractController::renderPage()),
// quindi basta rimettere il risultato al posto giusto invece di lasciare
// che sia il browser a "cambiare pagina" per una richiesta che in realta'
// resta sulla stessa.

function buildPlainControl(field) {
    return el('input', {
        className: 'field__input',
        type: field.type || 'text',
        id: 'field-' + field.key,
        name: field.key,
        value: field.value ?? '',
        required: field.required ? 'required' : null,
        readonly: field.readonly ? 'readonly' : null,
    });
}

// Testo su piu' righe, opt-in per campo ('input' => 'textarea' nel
// manifest del pacchetto): non tutti i TEXT lo meritano, un corpo di
// messaggio si'.
function buildTextareaControl(field) {
    const control = el('textarea', {
        className: 'field__input field__input--area',
        id: 'field-' + field.key,
        name: field.key,
        rows: '8',
        required: field.required ? 'required' : null,
        readonly: field.readonly ? 'readonly' : null,
    });
    control.value = field.value ?? '';
    return control;
}

// Campo "digita e scegli da un elenco" (come la Dynamic Select di Core):
// un input di testo visibile per cercare/mostrare l'etichetta, un input
// nascosto con l'id vero che finisce nel FormData all'invio - il server
// vede sempre e solo un id (vedi 'format' del campo nel manifest,
// invariato), l'autocomplete e' solo il modo in cui lo si sceglie.
// Con autocompleteFreeText (es. il CAP) il valore e' il testo stesso: i
// suggerimenti aiutano a scriverlo e compilano i campi collegati.
function buildAutocompleteControl(field) {
    const freeText = Boolean(field.autocompleteFreeText);
    const hidden = freeText ? null : el('input', { type: 'hidden', name: field.key, value: field.value ?? '' });
    const display = el('input', {
        className: 'field__input',
        type: 'text',
        id: 'field-' + field.key,
        name: freeText ? field.key : null,
        value: freeText ? field.value ?? '' : null,
        autocomplete: 'off',
        placeholder: freeText ? null : 'Digita per cercare...',
    });
    const list = el('div', { className: 'field__autocomplete-list' });
    list.hidden = true;

    let debounceTimer = null;
    let items = [];
    let activeIndex = -1;

    function closeList() {
        list.hidden = true;
        list.textContent = '';
        items = [];
        activeIndex = -1;
    }

    function selectItem(item) {
        if (hidden) {
            hidden.value = item.value;
            display.value = item.label;
        } else {
            display.value = item.value;
        }
        closeList();
        const form = display.form;
        // Es. un servizio del listino: prezzo e rinnovo arrivano da li'.
        // I campi di testo solo se vuoti: quello che l'operatore ha gia' scritto resta.
        if (item.fill && form) {
            Object.entries(item.fill).forEach(([name, value]) => {
                const controls = form.querySelectorAll(`[name="${name}"]`);
                const control = controls[controls.length - 1];
                // Un menu a tendina ha sempre un valore di partenza: lo si
                // allinea comunque al listino. Un campo a scelta (id
                // nascosto) segue sempre: e' la scelta appena fatta.
                if (control && (!control.value || control.tagName === 'SELECT' || control.type === 'hidden')) {
                    control.value = value;
                    control.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }
        display.dispatchEvent(new CustomEvent('autocomplete:select', { bubbles: true, detail: { key: field.key, value: item.value, label: item.label } }));
    }

    function highlight(index) {
        activeIndex = index;
        Array.from(list.children).forEach((node, i) => node.classList.toggle('is-active', i === index));
        list.children[index]?.scrollIntoView({ block: 'nearest' });
    }

    function search(query) {
        if (!query) {
            closeList();
            return;
        }

        fetch(field.autocompleteSource + '?q=' + encodeURIComponent(query), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => res.json())
            .then((found) => {
                list.textContent = '';
                if (!found || !found.length || document.activeElement !== display) {
                    closeList();
                    return;
                }
                items = found;
                activeIndex = -1;
                found.forEach((item) => {
                    list.appendChild(
                        el(
                            'button',
                            {
                                type: 'button',
                                className: 'field__autocomplete-item',
                                // mousedown, non click: precede il blur del
                                // campo di testo, altrimenti la lista si
                                // chiude (vedi sotto) prima che il click registri.
                                onMousedown: (e) => {
                                    e.preventDefault();
                                    selectItem(item);
                                },
                            },
                            [item.label]
                        )
                    );
                });
                list.hidden = false;
            })
            .catch(() => closeList());
    }

    display.addEventListener('input', () => {
        // Finche' non si sceglie di nuovo un suggerimento, il valore vero
        // resta vuoto: un testo digitato a mano senza selezionare nulla
        // dalla lista non e' un id valido, meglio niente che un id sbagliato.
        if (hidden) hidden.value = '';
        clearTimeout(debounceTimer);
        const query = display.value.trim();
        debounceTimer = setTimeout(() => search(query), 250);
    });

    // Frecce e Invio sui suggerimenti; Invio con la lista aperta sceglie,
    // non manda il form.
    display.addEventListener('keydown', (e) => {
        if (list.hidden || !items.length) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const step = e.key === 'ArrowDown' ? 1 : -1;
            highlight((activeIndex + step + items.length) % items.length);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            selectItem(items[Math.max(0, activeIndex)]);
        } else if (e.key === 'Escape') {
            closeList();
        }
    });

    display.addEventListener('blur', () => closeList());

    function showLabelOf(id) {
        if (!id) return;
        fetch(field.autocompleteSource + '?id=' + encodeURIComponent(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => res.json())
            .then((item) => {
                if (item && item.label) display.value = item.label;
            })
            .catch(() => {});
    }

    if (hidden) {
        // Compilato da un altro campo (es. il comune scelto dal CAP): l'etichetta segue.
        hidden.addEventListener('change', () => showLabelOf(hidden.value));
        showLabelOf(field.value);
    }

    return el('div', { className: 'field__autocomplete' }, [display, list, hidden]);
}

// Valori fissi dal manifest ('options'): si sceglie, non si scrive - niente
// "Ricorrente" e "ricorrente" come due cose diverse.
function buildSelectControl(field) {
    const control = el('select', {
        className: 'field__input field__input--select',
        id: 'field-' + field.key,
        name: field.key,
        required: field.required ? 'required' : null,
    });
    if (!field.required || field.value == null || field.value === '') {
        control.appendChild(el('option', { value: '' }, ['—']));
    }
    // Opzioni come testo (il valore e' anche l'etichetta) o come {value, label} (es. un id e un nome).
    (field.options || []).forEach((option) => {
        const value = typeof option === 'object' ? String(option.value) : option;
        const label = typeof option === 'object' ? option.label : option.charAt(0).toUpperCase() + option.slice(1);
        const node = el('option', { value }, [label]);
        if (String(field.value ?? '') === value) node.selected = true;
        control.appendChild(node);
    });
    return control;
}

// Si/no: il campo nascosto manda "0" quando l'interruttore e' spento (un
// checkbox spento non viaggia nel FormData), il checkbox acceso lo
// sovrascrive con "1" - PHP tiene l'ultimo valore con lo stesso nome.
function buildToggleControl(field) {
    const checkbox = el('input', {
        type: 'checkbox',
        className: 'field__toggle-input',
        id: 'field-' + field.key,
        name: field.key,
        value: '1',
    });
    checkbox.checked = String(field.value ?? '') === '1';
    const state = el('span', { className: 'field__toggle-state' }, [checkbox.checked ? 'Si' : 'No']);
    checkbox.addEventListener('change', () => {
        state.textContent = checkbox.checked ? 'Si' : 'No';
    });
    return el('label', { className: 'field__toggle', for: 'field-' + field.key }, [
        el('input', { type: 'hidden', name: field.key, value: '0' }),
        checkbox,
        el('span', { className: 'field__toggle-track' }, [el('span', { className: 'field__toggle-thumb' })]),
        state,
    ]);
}

// Scelta multipla (es. i colleghi che vengono): chip da accendere, il
// valore vero e' "2,3" nel campo nascosto.
function buildCheckboxesControl(field) {
    const hidden = el('input', { type: 'hidden', name: field.key, value: field.value ?? '' });
    const selected = new Set(String(field.value ?? '').split(',').map((v) => v.trim()).filter(Boolean));
    const sync = () => {
        hidden.value = Array.from(selected).join(',');
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const chips = (field.choices || []).map((choice) => {
        const chip = el('button', {
            type: 'button',
            className: 'field__choice' + (selected.has(choice.value) ? ' is-on' : ''),
            'aria-pressed': selected.has(choice.value) ? 'true' : 'false',
        }, [choice.label]);
        chip.addEventListener('click', () => {
            if (selected.has(choice.value)) selected.delete(choice.value); else selected.add(choice.value);
            chip.classList.toggle('is-on', selected.has(choice.value));
            chip.setAttribute('aria-pressed', selected.has(choice.value) ? 'true' : 'false');
            sync();
        });
        return chip;
    });
    return el('div', { className: 'field__choices', id: 'field-' + field.key }, [...chips, hidden]);
}

function buildControl(field) {
    switch (field.inputType) {
        case 'checkboxes': return buildCheckboxesControl(field);
        case 'autocomplete': return buildAutocompleteControl(field);
        case 'textarea': return buildTextareaControl(field);
        case 'select': return buildSelectControl(field);
        case 'toggle': return buildToggleControl(field);
        default: return buildPlainControl(field);
    }
}

// Colonne (su 12) di partenza per larghezza: nella colonna laterale, stretta,
// solo i campi piccolissimi (orari) stanno in due per riga.
const SPANS = { quarter: 3, third: 4, half: 6, full: 12 };

function baseSpan(width, inSidebar) {
    const span = SPANS[width] || 6;
    if (!inSidebar) return span;
    return span <= 3 ? 6 : 12;
}

// Etichetta sul bordo dell'input (form piu' compatto) per i campi "a
// scatola"; interruttori e chip la tengono sopra.
function isFloating(field) {
    return !['checkboxes', 'toggle'].includes(field.inputType);
}

function buildField(field) {
    const control = buildControl(field);

    const label = el('label', { className: 'field__label', for: 'field-' + field.key }, [
        field.label,
        field.required ? el('span', { className: 'field__required', 'aria-hidden': 'true' }, ['*']) : null,
    ]);
    const children = [label, control];

    // Errore specifico di QUESTO campo (vedi App\Controller\
    // AbstractEntityController::collectAndValidate()), cosi' l'operatore
    // vede subito QUALE campo correggere, non solo che "qualcosa" e'
    // andato storto.
    if (field.error) {
        children.push(el('p', { className: 'field__help field__error' }, [field.error]));
    } else if (field.help) {
        children.push(el('p', { className: 'field__hint' }, [field.help]));
    }

    const classes = ['field', 'field--' + (field.width || 'half')];
    if (isFloating(field)) classes.push('field--floating');
    if (field.error) classes.push('field--error');
    const wrap = el('div', { className: classes.join(' ') }, children);
    wrap.dataset.key = field.key;
    wrap.dataset.width = field.width || 'half';
    if (field.required) wrap.dataset.required = '1';
    wrap.dataset.label = field.label;
    if (field.showIf) {
        wrap.dataset.showIfField = field.showIf.field;
        wrap.dataset.showIfIn = JSON.stringify(field.showIf.in || []);
    }
    return wrap;
}

/**
 * Riempie le righe della griglia: i campi visibili si mettono in fila
 * (12 colonne per riga) e lo spazio avanzato in fondo a una riga si
 * divide tra i campi di quella riga - niente "Cliente" a meta' riga con
 * il vuoto accanto. Rifatto quando un campo compare o sparisce (showIf).
 */
function packGrid(grid) {
    const inSidebar = Boolean(grid.closest('.form-section__sidebar'));
    const fields = Array.from(grid.children).filter((f) => f.classList.contains('field') && !f.hidden);
    let row = [];
    let used = 0;

    const flush = () => {
        const extra = 12 - used;
        if (row.length && extra > 0) {
            let given = 0;
            row.forEach((item) => {
                const share = Math.floor((extra * item.span) / used);
                item.span += share;
                given += share;
            });
            row[0].span += extra - given;
        }
        row.forEach((item) => item.node.style.setProperty('--span', String(item.span)));
        row = [];
        used = 0;
    };

    fields.forEach((node) => {
        const span = baseSpan(node.dataset.width, inSidebar);
        if (used + span > 12) flush();
        row.push({ node, span });
        used += span;
    });
    flush();
}

// Campi che dipendono dal valore di un altro (vedi 'showIf' nel manifest):
// si ricalcola a ogni cambio. Un box rimasto senza campi visibili sparisce.
function applyConditionalFields(form) {
    const valueOf = (name) => {
        const controls = form.querySelectorAll(`[name="${name}"]`);
        const last = controls[controls.length - 1];
        if (!last) return '';
        if (last.type === 'checkbox') return last.checked ? '1' : '0';
        return last.value;
    };

    form.querySelectorAll('[data-show-if-field]').forEach((wrap) => {
        const allowed = JSON.parse(wrap.dataset.showIfIn || '[]');
        wrap.hidden = !allowed.includes(valueOf(wrap.dataset.showIfField));
    });
    form.querySelectorAll('.form-box').forEach((box) => {
        box.hidden = Array.from(box.querySelectorAll('.field')).every((f) => f.hidden);
    });
    form.querySelectorAll('.form-box__grid').forEach(packGrid);
}

// Tutti i campi con errore, attraversando sezioni/box - serve per
// riportare la tab giusta in vista se l'errore e' su un campo nascosto in
// una tab non attiva.
function collectFieldErrors(sections) {
    const errors = [];
    (sections || []).forEach((section) => {
        (section.boxes || []).forEach((box) => {
            (box.fields || []).forEach((field) => {
                if (field.error) errors.push(field);
            });
        });
    });
    return errors;
}

// Un box e' una sotto-card dentro la sezione (es. "Informazioni
// principali", "Contatto") - vedi FormComponent per come i campi ci
// arrivano gia' filtrati (un box senza nessun campo disponibile per
// questo progetto non arriva qui: FormComponent lo scarta prima).
function buildBox(box) {
    const children = [];
    if (box.title) {
        children.push(el('h3', { className: 'form-box__title' }, [box.title]));
    }
    children.push(el('div', { className: 'form-box__grid' }, box.fields.map(buildField)));

    return el('div', { className: 'form-box form-box--' + (box.area || 'main') }, children);
}

// Le colonne 'main'/'sidebar' esistono come contenitore a se' solo se
// la sezione ha davvero box in entrambe le aree - altrimenti resta una
// sola colonna piena, niente spazio vuoto per una sidebar che non c'e'.
function buildSectionContent(section) {
    const mainBoxes = section.boxes.filter((b) => (b.area || 'main') !== 'sidebar').map(buildBox);
    const sidebarBoxes = section.boxes.filter((b) => b.area === 'sidebar').map(buildBox);

    if (sidebarBoxes.length === 0) {
        return el('div', { className: 'form-section' }, mainBoxes);
    }

    return el('div', { className: 'form-section form-section--split' }, [
        el('div', { className: 'form-section__main' }, mainBoxes),
        el('div', { className: 'form-section__sidebar' }, sidebarBoxes),
    ]);
}

// Tutte le sezioni restano nel DOM (i loro campi devono comunque finire
// nella FormData all'invio, a prescindere da quale tab e' in vista) -
// cambiare tab nasconde/mostra, non smonta/rimonta.
function buildSections(sections) {
    // Se il salvataggio precedente ha lasciato un errore su un campo di
    // una tab diversa da quella iniziale, l'evidenziazione (.field--error)
    // sarebbe invisibile perche' la sua sezione resta display:none - si
    // apre di default la PRIMA tab con un errore, non sempre la 0.
    const initialIndex = Math.max(0, sections.findIndex((s) => collectFieldErrors([s]).length > 0));

    const wrap = el('div', { className: 'form-sections' });
    const contents = sections.map(buildSectionContent);
    contents.forEach((content, i) => {
        content.style.display = i === initialIndex ? '' : 'none';
        wrap.appendChild(content);
    });

    if (sections.length <= 1) {
        return { sectionsEl: wrap, tabsEl: null, showSectionOf: () => {} };
    }

    const tabButtons = sections.map((section, i) =>
        el(
            'button',
            {
                type: 'button',
                className: 'tabs__item' + (i === initialIndex ? ' is-active' : ''),
                onClick: () => showSection(i),
            },
            [section.label || `Sezione ${i + 1}`]
        )
    );
    const tabsEl = el('div', { className: 'tabs form-tabs' }, tabButtons);

    function showSection(index) {
        tabButtons.forEach((btn, j) => btn.classList.toggle('is-active', j === index));
        contents.forEach((content, j) => {
            content.style.display = j === index ? '' : 'none';
        });
    }

    // La tab che contiene un campo (es. il primo campo da correggere).
    const showSectionOf = (node) => {
        const index = contents.findIndex((content) => content.contains(node));
        if (index !== -1) showSection(index);
    };

    return { sectionsEl: wrap, tabsEl, showSectionOf };
}

// --- Controllo prima dell'invio ---------------------------------------

function isFilled(wrap) {
    const controls = Array.from(wrap.querySelectorAll('[name]'));
    if (!controls.length) return true;
    // Autocomplete: conta l'id scelto (nascosto), non il testo digitato.
    return controls.some((c) => c.type !== 'checkbox' && String(c.value || '').trim() !== '');
}

function setFieldError(wrap, message) {
    wrap.classList.add('field--error');
    let help = wrap.querySelector('.field__error');
    if (!help) {
        help = el('p', { className: 'field__help field__error' });
        const hint = wrap.querySelector('.field__hint');
        if (hint) hint.hidden = true;
        wrap.appendChild(help);
    }
    help.textContent = message;
}

function clearFieldError(wrap) {
    wrap.classList.remove('field--error');
    wrap.querySelector('.field__error')?.remove();
    const hint = wrap.querySelector('.field__hint');
    if (hint) hint.hidden = false;
}

/** @return {{wrap: HTMLElement, label: string, message: string}[]} */
function validateForm(form) {
    const errors = [];
    form.querySelectorAll('.field').forEach((wrap) => {
        if (wrap.hidden || wrap.closest('.form-box[hidden]')) return;
        const label = wrap.dataset.label || '';
        if (wrap.dataset.required && !isFilled(wrap)) {
            const typed = wrap.querySelector('.field__autocomplete .field__input');
            const message = typed && typed.value.trim() !== '' ? "Scegli una voce dall'elenco." : 'Campo obbligatorio.';
            errors.push({ wrap, label, message });
            return;
        }
        const input = wrap.querySelector('input.field__input');
        if (input && input.value !== '' && !input.checkValidity()) {
            const message = input.type === 'email' ? 'Indirizzo email non valido.' : 'Valore non valido.';
            errors.push({ wrap, label, message });
        }
    });
    return errors;
}

/**
 * Errore evidente dove si e' cliccato: il pulsante diventa rosso con
 * l'icona e una frase breve per un paio di secondi, la riga dei pulsanti
 * dice cosa manca, e il primo campo da sistemare viene portato in vista.
 */
function showErrors(form, errors, submitButton, hintEl, showSectionOf) {
    if (!errors.length) return;
    const first = errors[0];
    const summary = errors.length === 1
        ? `${first.label}: ${first.message.replace(/\.$/, '').toLowerCase()}`
        : `${errors.length} campi da sistemare`;

    hintEl.textContent = '';
    hintEl.classList.add('form__hint--error');
    hintEl.append(icon('circle-exclamation'), ' ', errors.length === 1 ? summary : `${summary}: ${errors.map((e) => e.label).join(', ')}`);

    if (!submitButton.dataset.label) submitButton.dataset.label = submitButton.textContent;
    clearTimeout(submitButton._errorTimer);
    submitButton.classList.remove('btn--error');
    // Riflusso: l'animazione riparte anche a un secondo clic.
    void submitButton.offsetWidth;
    submitButton.classList.add('btn--error');
    submitButton.textContent = '';
    submitButton.append(icon('circle-exclamation'), errors.length === 1 ? `Manca: ${first.label}` : summary);
    submitButton._errorTimer = setTimeout(() => {
        submitButton.classList.remove('btn--error');
        submitButton.textContent = submitButton.dataset.label;
    }, 2600);

    showSectionOf(first.wrap);
    first.wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const focusable = first.wrap.querySelector('input:not([type=hidden]), select, textarea, button');
    if (focusable) setTimeout(() => focusable.focus({ preventScroll: true }), 250);
}

function resetHint(hintEl, hasRequired) {
    hintEl.classList.remove('form__hint--error');
    hintEl.textContent = hasRequired ? '* obbligatorio' : '';
}

// Per chi si sta compilando il form (es. "Per il cliente Rossi Srl"): in
// testa, cosi' si capisce subito su cosa si sta lavorando; segue il campo
// collegato se lo si cambia.
function buildContext(context, form) {
    const name = el('a', { className: 'form__context-name' }, [context.name || '']);
    const setTarget = (id, label) => {
        name.textContent = label || '';
        if (context.hrefPattern && id) name.setAttribute('href', context.hrefPattern.replace('{id}', id));
        else name.removeAttribute('href');
        box.hidden = !label;
    };
    const box = el('div', { className: 'form__context' }, [
        icon('user'),
        el('span', { className: 'form__context-label' }, [context.label]),
        name,
    ]);
    setTarget(context.id, context.name);
    if (context.key) {
        form.addEventListener('autocomplete:select', (e) => {
            if (e.detail.key === context.key) setTarget(e.detail.value, e.detail.label);
        });
    }
    return box;
}

function renderForm(data) {
    const { sectionsEl, tabsEl, showSectionOf } = buildSections(data.sections || []);
    const fieldErrors = collectFieldErrors(data.sections || []);
    const hasRequired = (data.sections || []).some((s) => (s.boxes || []).some((b) => (b.fields || []).some((f) => f.required)));

    const children = [];

    if (data.message) {
        children.push(
            el('p', { className: 'field__help form__message form__message--' + (data.messageType || 'info') }, [
                data.message,
            ])
        );
    }

    if (tabsEl) {
        children.push(tabsEl);
    }
    children.push(sectionsEl);

    (data.hiddenFields || []).forEach((f) => {
        children.push(el('input', { type: 'hidden', name: f.key, value: f.value ?? '' }));
    });

    if (data.csrfToken) {
        children.push(el('input', { type: 'hidden', name: '_csrf', value: data.csrfToken }));
    }

    // Pulsanti a destra, il principale per ultimo; "Elimina" a sinistra,
    // lontano da "Salva". In mezzo la riga che dice cosa manca.
    const actionsRow = el('div', { className: 'form__actions' });
    const submitButton = el('button', { type: 'submit', className: 'btn btn--primary' }, [
        data.submitLabel || 'Salva',
    ]);
    const hintEl = el('p', { className: 'form__hint', 'aria-live': 'polite' });
    resetHint(hintEl, hasRequired);

    // Pulsanti in piu' verso un URL diverso dal submit principale (es.
    // "Converti in cliente"), stesso form/stessi campi: formaction e'
    // l'attributo HTML nativo che dice al browser (e a e.submitter qui
    // sotto) quale action usare per QUESTO pulsante invece di quella del
    // <form> - non serve un secondo <form> annidato.
    const secondaryButtons = (data.secondaryActions || []).map((secondary) =>
        el(
            'button',
            {
                type: 'submit',
                // variant e' opzionale (es. 'delete' per "Elimina",
                // vedi AbstractEditEntityTool::allSecondaryActions) -
                // di default resta il neutro di sempre, cosi' le
                // azioni custom gia' esistenti (es. "Converti in
                // cliente") non cambiano aspetto senza dirlo.
                className: `btn btn--${secondary.variant || 'secondary'}`,
                formaction: secondary.action,
                'data-confirm': secondary.confirm || null,
                'data-variant': secondary.variant || null,
            },
            [secondary.label]
        )
    );
    secondaryButtons.filter((b) => b.dataset.variant === 'delete').forEach((b) => actionsRow.appendChild(b));
    actionsRow.appendChild(hintEl);
    secondaryButtons.filter((b) => b.dataset.variant !== 'delete').forEach((b) => actionsRow.appendChild(b));
    actionsRow.appendChild(submitButton);
    children.push(actionsRow);

    // novalidate: niente fumetti del browser che spariscono da soli, il
    // controllo lo fa validateForm() e l'errore resta in vista.
    const form = el('form', { method: data.method || 'POST', action: data.action, novalidate: '' }, children);
    applyConditionalFields(form);
    form.addEventListener('change', () => applyConditionalFields(form));

    // Si corregge un campo: il suo errore sparisce.
    const clearOnEdit = (e) => {
        const wrap = e.target.closest && e.target.closest('.field--error');
        if (wrap) {
            clearFieldError(wrap);
            if (!form.querySelector('.field--error')) resetHint(hintEl, hasRequired);
        }
    };
    form.addEventListener('input', clearOnEdit);
    form.addEventListener('change', clearOnEdit);

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const submitter = e.submitter || submitButton;
        // "Elimina" e le altre azioni verso un altro indirizzo non
        // chiedono che il form sia completo.
        if (submitter === submitButton) {
            form.querySelectorAll('.field--error').forEach(clearFieldError);
            const errors = validateForm(form);
            if (errors.length) {
                errors.forEach((err) => setFieldError(err.wrap, err.message));
                showErrors(form, errors, submitButton, hintEl, showSectionOf);
                return;
            }
            resetHint(hintEl, hasRequired);
        }

        const confirmMessage = submitter.getAttribute('data-confirm');
        if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
        }

        const targetAction = submitter.getAttribute('formaction') || data.action;
        submitter.disabled = true;

        fetch(targetAction, {
            method: form.method || 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        })
            .then((res) => res.json())
            .then((responseData) => {
                refreshCurrentEntry(responseData.components || []);
            })
            .catch(() => {
                submitter.disabled = false;
                openModal({
                    title: 'Errore di comunicazione',
                    tone: 'error',
                    body: "Non e' stato possibile contattare il server. Riprova.",
                });
            });
    });

    // Errori arrivati dal server (es. formato non valido): stessa
    // evidenza del controllo prima dell'invio, appena il form e' in pagina.
    if (fieldErrors.length > 0) {
        setTimeout(() => {
            const errors = fieldErrors
                .map((f) => ({ wrap: form.querySelector(`.field[data-key="${f.key}"]`), label: f.label, message: f.error }))
                .filter((err) => err.wrap);
            showErrors(form, errors, submitButton, hintEl, showSectionOf);
        }, 200);
    }

    const contextEl = data.context ? buildContext(data.context, form) : null;

    // Sotto una scheda di dettaglio il form parte chiuso: prima si legge,
    // poi, se serve, si apre e si modifica.
    if (data.collapsed) {
        const body = el('div', { className: 'form__collapsible' }, [form]);
        body.hidden = true;
        const toggle = el('button', {
            type: 'button',
            className: 'form__expand',
            'aria-expanded': 'false',
            onClick: () => {
                body.hidden = !body.hidden;
                toggle.setAttribute('aria-expanded', body.hidden ? 'false' : 'true');
                toggle.querySelector('.form__expand-action').textContent = body.hidden ? 'Modifica' : 'Chiudi';
            },
        }, [
            el('span', { className: 'card__title form__expand-title' }, [data.title || 'Dati']),
            el('span', { className: 'form__expand-action' }, ['Modifica']),
        ]);
        return el('section', { className: 'card form form--collapsible' }, [toggle, body]);
    }

    return el('section', { className: 'card form' }, [
        data.title || contextEl
            ? el('div', { className: 'form__header' }, [
                  data.title ? el('h2', { className: 'card__title' }, [data.title]) : null,
                  contextEl,
              ])
            : null,
        form,
    ]);
}

registerComponent('form', renderForm);
