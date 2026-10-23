import { registerComponent } from './registry.js';
import { el } from '../dom.js';
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
function buildAutocompleteControl(field) {
    const hidden = el('input', { type: 'hidden', name: field.key, value: field.value ?? '' });
    const display = el('input', {
        className: 'field__input',
        type: 'text',
        id: 'field-' + field.key,
        autocomplete: 'off',
        placeholder: 'Digita per cercare...',
    });
    const list = el('div', { className: 'field__autocomplete-list' });
    list.hidden = true;

    let debounceTimer = null;

    function closeList() {
        list.hidden = true;
        list.textContent = '';
    }

    function selectItem(item) {
        hidden.value = item.value;
        display.value = item.label;
        closeList();
        // Es. un servizio del listino: prezzo e rinnovo arrivano da li'.
        // I campi di testo solo se vuoti: quello che l'operatore ha gia' scritto resta.
        if (item.fill && hidden.form) {
            Object.entries(item.fill).forEach(([name, value]) => {
                const controls = hidden.form.querySelectorAll(`[name="${name}"]`);
                const control = controls[controls.length - 1];
                // Un menu a tendina ha sempre un valore di partenza: lo si
                // allinea comunque al listino.
                if (control && (!control.value || control.tagName === 'SELECT')) {
                    control.value = value;
                    control.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }
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
            .then((items) => {
                list.textContent = '';
                if (!items || !items.length) {
                    closeList();
                    return;
                }
                items.forEach((item) => {
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
        hidden.value = '';
        clearTimeout(debounceTimer);
        const query = display.value.trim();
        debounceTimer = setTimeout(() => search(query), 250);
    });

    display.addEventListener('blur', () => closeList());

    if (field.value) {
        fetch(field.autocompleteSource + '?id=' + encodeURIComponent(field.value), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => res.json())
            .then((item) => {
                if (item && item.label) display.value = item.label;
            })
            .catch(() => {});
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

function buildField(field) {
    const control = buildControl(field);

    const children = [
        el('label', { className: 'field__label', for: 'field-' + field.key }, [field.label]),
        control,
    ];

    // Errore specifico di QUESTO campo (vedi App\Controller\
    // AbstractEntityController::collectAndValidate()) - oltre a un
    // eventuale avviso generale (vedi il modale in renderForm), cosi'
    // l'operatore vede subito QUALE campo correggere, non solo che
    // "qualcosa" e' andato storto.
    if (field.error) {
        children.push(el('p', { className: 'field__help' }, [field.error]));
    } else if (field.help) {
        children.push(el('p', { className: 'field__hint' }, [field.help]));
    }

    const wrap = el('div', { className: 'field field--' + (field.width || 'half') + (field.error ? ' field--error' : '') }, children);
    wrap.dataset.key = field.key;
    if (field.showIf) {
        wrap.dataset.showIfField = field.showIf.field;
        wrap.dataset.showIfIn = JSON.stringify(field.showIf.in || []);
    }
    return wrap;
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
}

// Tutti i campi con errore, attraversando sezioni/box - serve per il
// modale riassuntivo (vedi renderForm) e per riportare la tab giusta in
// vista se l'errore e' su un campo nascosto in una tab non attiva.
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
        return { sectionsEl: wrap, tabsEl: null };
    }

    const tabsEl = el(
        'div',
        { className: 'tabs form-tabs' },
        sections.map((section, i) =>
            el(
                'button',
                {
                    type: 'button',
                    className: 'tabs__item' + (i === initialIndex ? ' is-active' : ''),
                    onClick: (e) => {
                        tabsEl.querySelectorAll('.tabs__item').forEach((btn) => btn.classList.remove('is-active'));
                        e.currentTarget.classList.add('is-active');
                        contents.forEach((content, j) => {
                            content.style.display = j === i ? '' : 'none';
                        });
                    },
                },
                [section.label || `Sezione ${i + 1}`]
            )
        )
    );

    return { sectionsEl: wrap, tabsEl };
}

function renderForm(data) {
    const { sectionsEl, tabsEl } = buildSections(data.sections || []);

    // Avviso generale in un mini modale, oltre all'evidenziazione dei
    // singoli campi qui sopra - lo stesso errore si vede in due punti
    // apposta: il modale attira l'attenzione subito (soprattutto se il
    // campo sbagliato e' su una tab non visibile), l'evidenziazione sul
    // campo resta li' come riferimento mentre si corregge.
    const fieldErrors = collectFieldErrors(data.sections || []);
    if (fieldErrors.length > 0) {
        openModal({
            title: fieldErrors.length === 1 ? "C'e' un errore" : `Ci sono ${fieldErrors.length} errori`,
            tone: 'error',
            body: el(
                'ul',
                {},
                fieldErrors.map((f) => el('li', {}, [`${f.label}: ${f.error}`]))
            ),
        });
    }

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

    const actionsRow = el('div', { className: 'form__actions' });
    const submitButton = el('button', { type: 'submit', className: 'btn btn--primary' }, [
        data.submitLabel || 'Salva',
    ]);
    actionsRow.appendChild(submitButton);

    // Pulsanti in piu' verso un URL diverso dal submit principale (es.
    // "Converti in cliente"), stesso form/stessi campi: formaction e'
    // l'attributo HTML nativo che dice al browser (e a e.submitter qui
    // sotto) quale action usare per QUESTO pulsante invece di quella del
    // <form> - non serve un secondo <form> annidato.
    (data.secondaryActions || []).forEach((secondary) => {
        actionsRow.appendChild(
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
                },
                [secondary.label]
            )
        );
    });
    children.push(actionsRow);

    const form = el('form', { method: data.method || 'POST', action: data.action }, children);
    applyConditionalFields(form);
    form.addEventListener('change', () => applyConditionalFields(form));

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const submitter = e.submitter || submitButton;
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
        data.title ? el('h2', { className: 'card__title' }, [data.title]) : null,
        form,
    ]);
}

registerComponent('form', renderForm);
