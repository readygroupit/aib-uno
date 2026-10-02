import { el } from './dom.js';
import { icon } from './icons.js';

/**
 * Checklist di configurazione (cosa manca per essere operativi) - unico
 * renderer, montato sia nella scheda "Cosa manca ancora" del tour di
 * benvenuto (onboarding.js) sia nella pagina /configurazione (componente
 * 'setup-checklist'). I dati arrivano da SetupService::payload().
 *
 * Un passaggio-collegamento (kind 'connector') si apre come procedura
 * guidata DENTRO lo stesso contenitore, con "Torna all'elenco": niente
 * secondo modale sopra il primo. Un passaggio-sezione (kind 'link')
 * porta alla pagina dove si fa, che mostra la spiegazione in cima
 * (?setup=chiave, vedi 'setup-hint').
 */

function csrfHeaders() {
    return { 'Content-Type': 'application/json', 'X-CSRF-Token': window.UNO_CSRF || '' };
}

async function post(url, body = {}) {
    const response = await fetch(url, { method: 'POST', headers: csrfHeaders(), body: JSON.stringify(body) });
    const data = await response.json().catch(() => null);
    if (!data) {
        return { ok: false, error: 'Risposta non valida dal server. Riprova.' };
    }
    return data;
}

function progressBlock(progress) {
    const complete = progress.total > 0 && progress.done === progress.total;
    const label = complete
        ? 'Tutto il necessario e\' pronto'
        : `${progress.done} di ${progress.total} passaggi obbligatori completati`;

    const bar = el('div', {
        className: 'setup__bar',
        role: 'progressbar',
        'aria-valuemin': '0',
        'aria-valuemax': '100',
        'aria-valuenow': String(progress.percent),
        'aria-label': 'Avanzamento della configurazione',
    }, [el('div', { className: 'setup__bar-fill' + (complete ? ' is-complete' : ''), style: `width:${progress.percent}%` })]);

    return el('div', { className: 'setup__progress' }, [
        el('div', { className: 'setup__progress-head' }, [
            el('span', { className: 'setup__progress-label' }, [label]),
            el('span', { className: 'setup__progress-percent' }, [`${progress.percent}%`]),
        ]),
        bar,
    ]);
}

function statusMark(item) {
    if (item.done === true) return icon('circle-check', 'setup__mark setup__mark--done');
    if (item.done === false) return icon('circle', 'setup__mark setup__mark--todo');
    return icon('minus', 'setup__mark setup__mark--manual');
}

function itemRow(item, onOpen) {
    const text = [el('span', { className: 'setup__item-label' }, [item.label])];
    text.push(el('span', { className: 'setup__item-desc' }, [item.summary || item.description]));

    return el('button', { type: 'button', className: 'setup__item', onClick: () => onOpen(item) }, [
        statusMark(item),
        el('span', { className: 'setup__item-text' }, text),
        el('span', { className: 'setup__pill setup__pill--' + (item.done === true ? 'done' : item.done === false ? 'todo' : 'manual') }, [item.status]),
        icon('chevron-right', 'setup__chevron'),
    ]);
}

function group(title, items, onOpen) {
    if (items.length === 0) return null;
    return el('div', { className: 'setup__group' }, [
        el('h3', { className: 'setup__group-title' }, [title]),
        el('div', { className: 'setup__items' }, items.map((item) => itemRow(item, onOpen))),
    ]);
}

function fieldControl(field) {
    const common = { id: 'setup-field-' + field.key, name: field.key, className: 'field__input setup__input', autocomplete: 'off', spellcheck: 'false' };
    if (field.placeholder) common.placeholder = field.placeholder;

    if (field.type === 'textarea') {
        return el('textarea', { ...common, className: 'field__input setup__input setup__input--area', rows: '6' });
    }
    if (field.type === 'select') {
        return el('select', common, (field.options || []).map((o) => el('option', { value: o.value }, [o.label])));
    }
    return el('input', { ...common, type: field.type === 'password' ? 'password' : 'text' });
}

function connectorView(item, api) {
    const spec = item.spec;
    const errorBox = el('div', { className: 'form__message form__message--error setup__error', role: 'alert', hidden: '' });
    const controls = new Map();

    const fields = spec.fields.map((field) => {
        const control = fieldControl(field);
        controls.set(field.key, control);
        return el('div', { className: 'field' }, [
            el('label', { className: 'field__label', for: 'setup-field-' + field.key }, [field.label]),
            control,
            field.help ? el('span', { className: 'field__help' }, [field.help]) : null,
        ]);
    });

    const submit = el('button', { type: 'submit', className: 'btn btn--primary' }, [item.done ? 'Verifica di nuovo e aggiorna' : 'Verifica e collega']);

    const form = el('form', { className: 'setup__form', onSubmit: async (e) => {
        e.preventDefault();
        errorBox.hidden = true;
        submit.disabled = true;
        submit.textContent = 'Verifica in corso...';

        const values = {};
        controls.forEach((control, key) => { values[key] = control.value; });
        const result = await post('/configurazione/connettori/' + item.key, values);

        if (result.ok) {
            api.update(result, `${item.label}: ${result.message}.`);
            return;
        }
        submit.disabled = false;
        submit.textContent = item.done ? 'Verifica di nuovo e aggiorna' : 'Verifica e collega';
        errorBox.textContent = result.error || 'Qualcosa non ha funzionato.';
        errorBox.hidden = false;
        errorBox.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    } }, [...fields, errorBox, el('div', { className: 'setup__actions' }, [submit])]);

    const children = [
        el('button', { type: 'button', className: 'setup__back', onClick: api.back }, [icon('arrow-left'), 'Torna all\'elenco']),
        el('h3', { className: 'setup__title' }, [item.label]),
        el('p', { className: 'setup__intro' }, [spec.intro]),
    ];

    if (item.done) {
        const disconnect = el('button', { type: 'button', className: 'btn btn--ghost btn--small setup__disconnect' }, ['Scollega']);
        let armed = false;
        disconnect.addEventListener('click', async () => {
            if (!armed) {
                armed = true;
                disconnect.textContent = 'Conferma: scollega e cancella le chiavi';
                return;
            }
            disconnect.disabled = true;
            const result = await post('/configurazione/connettori/' + item.key + '/scollega');
            if (result.ok) api.update(result, `${item.label} scollegato.`);
        });
        children.push(el('div', { className: 'setup__connected' }, [
            icon('circle-check', 'setup__mark setup__mark--done'),
            el('span', { className: 'setup__connected-text' }, ['Collegato' + (item.summary ? ' - ' + item.summary : '')]),
            disconnect,
        ]));
    }

    children.push(
        el('ol', { className: 'setup__steps' }, spec.steps.map((step) => el('li', {}, [step]))),
        form,
        el('p', { className: 'setup__note' }, [spec.note])
    );

    return el('div', { className: 'setup__connector' }, children);
}

/**
 * @param {HTMLElement} container svuotato e riempito qui
 * @param {{items: object[], progress: object}} initial
 * @param {{onNavigate?: (href: string) => void, onChange?: (payload: object) => void}} [options]
 */
export function mountSetup(container, initial, options = {}) {
    const navigate = options.onNavigate || ((href) => { window.location.href = href; });
    let payload = initial;
    let openKey = null;
    let flash = null;

    function render() {
        container.textContent = '';
        const open = openKey ? payload.items.find((i) => i.key === openKey) : null;

        if (open) {
            container.appendChild(connectorView(open, {
                back: () => { openKey = null; render(); },
                update: (data, message) => {
                    payload = { items: data.items, progress: data.progress };
                    if (options.onChange) options.onChange(payload);
                    flash = message;
                    openKey = null;
                    render();
                },
            }));
            return;
        }

        const onOpen = (item) => {
            if (item.kind === 'connector') {
                openKey = item.key;
                flash = null;
                render();
            } else {
                navigate(item.href);
            }
        };

        if (flash) {
            container.appendChild(el('div', { className: 'form__message form__message--success', role: 'status' }, [flash]));
        }
        container.appendChild(progressBlock(payload.progress));
        [
            group('Obbligatori', payload.items.filter((i) => i.required), onOpen),
            group('Facoltativi', payload.items.filter((i) => !i.required), onOpen),
        ].forEach((node) => node && container.appendChild(node));
    }

    render();
}
