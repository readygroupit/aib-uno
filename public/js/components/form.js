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

function buildField(field) {
    const control = field.inputType === 'autocomplete'
        ? buildAutocompleteControl(field)
        : field.inputType === 'textarea' ? buildTextareaControl(field) : buildPlainControl(field);

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
    }

    return el('div', { className: 'field field--' + (field.width || 'half') + (field.error ? ' field--error' : '') }, children);
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

    return el('section', { className: 'card form' }, [
        data.title ? el('h2', { className: 'card__title' }, [data.title]) : null,
        form,
    ]);
}

registerComponent('form', renderForm);
