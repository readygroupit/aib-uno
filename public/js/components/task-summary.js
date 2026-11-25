import { registerComponent, renderComponent } from './registry.js';
import { actionsRow } from '../work-ui.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge, actionButton, toast, runAction, reloadCurrentScreen } from '../accounts-ui.js';
import { guideParts } from '../guide.js';
import { personAvatar } from '../avatars.js';

// In testa all'attivita': stato, titolo, a cosa e' collegata, quando e chi.
// Cosa fare sta nella scheda "Da fare" (componente 'task-work').
function renderTaskSummary(data) {
    return el('div', { className: 'acc-profile' }, [
        el('section', { className: 'card acc-profile__head' }, [
            el('div', { className: 'acc-profile__identity' }, [
                el('div', { className: 'acc-profile__titles' }, [
                    el('div', { className: 'acc-profile__tags' }, [
                        badge(data.done ? 'Fatta' : 'Da fare', data.done ? 'success' : 'strong'),
                        ...(data.tags || []).map((t) => badge(t.label, t.tone)),
                    ]),
                    el('h1', { className: 'acc-profile__name' + (data.done ? ' wk-done-title' : '') }, [data.title]),
                    data.context ? el('a', { className: 'acc-fact wk-context', href: data.context.href }, [icon('arrow-right'), data.context.label]) : null,
                    el('div', { className: 'acc-profile__facts' }, [
                        // "Chi la fa": con il permesso, un menu per passarla a un altro.
                        ...(data.facts || []).filter((f) => !(data.assign && f.icon === 'user')).map((f) => el('span', { className: 'acc-fact' }, [icon(f.icon), f.text])),
                        data.assign ? assignPicker(data.assign) : null,
                    ]),
                ]),
                el('div', { className: 'acc-profile__actions' }, [
                    data.hideToggle ? null : actionButton(data.done ? 'Riapri' : 'Fatta', data.toggleAction, { variant: data.done || data.primaryElsewhere ? 'secondary' : 'primary', iconName: data.done ? null : 'check' }),
                ]),
            ]),
            data.description ? el('p', { className: 'wk-notes' }, [data.description]) : null,
        ]),
    ]);
}

// A chi tocca l'attivita': un menu con le persone; scelta, passa a lei (con l'avviso).
function assignPicker(a) {
    const select = el('select', { className: 'tw-assign', 'aria-label': 'Chi la fa' }, [
        a.current ? null : el('option', { value: '' }, ['Nessuno: scegli chi']),
        ...a.users.map((u) => el('option', { value: String(u.id), selected: u.id === a.current ? '' : null }, [u.name])),
    ]);
    select.addEventListener('change', () => {
        if (!select.value) return;
        select.disabled = true;
        runAction(a.action, { body: { user_id: select.value } }).finally(() => { select.disabled = false; });
    });
    return el('span', { className: 'acc-fact tw-assign-wrap' }, [
        icon('user'),
        select,
        a.role ? el('span', { className: 'tw-line-desc' }, [`ruolo: ${a.role}`]) : null,
    ]);
}

async function post(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '' },
        body: new URLSearchParams(body),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || '');
    return data;
}

function autosize(area) {
    area.style.height = 'auto';
    area.style.height = `${Math.max(area.scrollHeight, 44)}px`;
}

/**
 * Cosa fare in un'attivita': una lista sola. Ogni voce ha la spunta a
 * sinistra, il testo a destra e, nelle fasi guidate (briefing, raccolta
 * informazioni), sotto lo spazio per la risposta. Le risposte si salvano
 * da sole mentre si scrive; la prima risposta spunta la voce. In fondo un
 * solo pulsante chiude l'attivita'.
 */
function renderTaskWork(data) {
    const g = guideParts(data.guide, data.title);
    const status = el('span', { className: 'tw-status', 'aria-live': 'polite' });
    const progress = el('span', { className: 'tw-progress' });
    const rows = [];

    const paintProgress = () => {
        const total = rows.filter((r) => r.checkable).length;
        const done = rows.filter((r) => r.checkable && r.done).length;
        progress.textContent = total ? `${done} di ${total}` : '';
        progress.classList.toggle('is-complete', total > 0 && done === total);
    };

    const setStatus = (text, tone = '') => {
        status.textContent = text;
        status.className = `tw-status${tone ? ` tw-status--${tone}` : ''}`;
    };

    // Salvataggio automatico delle risposte (le fasi guidate).
    let timer = null;
    const answers = () => {
        const body = {};
        rows.forEach((r) => { if (r.area) body[`q${r.index}`] = r.area.value; });
        return body;
    };
    const saveNow = async () => {
        clearTimeout(timer);
        if (!data.saveAction) return;
        setStatus('Salvataggio…');
        try {
            await post(data.saveAction, { mode: 'autosave', ...answers() });
            setStatus('Salvato', 'ok');
        } catch (e) {
            setStatus('Non salvato: riprova', 'error');
        }
    };
    const saveSoon = () => {
        clearTimeout(timer);
        setStatus('Modifiche da salvare…');
        timer = setTimeout(saveNow, 900);
    };

    const setDone = async (row, done) => {
        const before = row.done;
        row.done = done;
        row.paint();
        paintProgress();
        try {
            const res = await post(data.checkAction, { index: String(row.index) });
            row.done = Boolean(res.done);
        } catch (e) {
            row.done = before;
            toast('Non sono riuscito a salvare la spunta. Riprova.', 'error');
        }
        row.paint();
        paintProgress();
    };

    const makeRow = (item, checkable) => {
        const row = { index: item.index, done: Boolean(item.done), checkable, area: null, paint: () => {} };
        const textId = `tw-text-${item.index}`;
        const text = el('span', { className: 'tw-text', id: textId }, [item.text]);
        let check = null;
        if (checkable) {
            check = el('button', { type: 'button', className: 'tw-check', role: 'checkbox', 'aria-labelledby': textId, disabled: data.done ? '' : null }, [icon('check')]);
            check.addEventListener('click', () => setDone(row, !row.done));
            text.addEventListener('click', () => { if (!data.done) setDone(row, !row.done); });
        }
        if (data.withAnswers) {
            row.area = el('textarea', { className: 'tw-answer', rows: '1', name: `q${item.index}`, placeholder: data.answerPlaceholder || 'Note', 'aria-labelledby': textId });
            row.area.value = item.answer || '';
            row.area.addEventListener('input', () => {
                autosize(row.area);
                // La prima risposta spunta la voce.
                if (checkable && !row.done && row.area.value.trim() !== '' && !row.autoTicked) {
                    row.autoTicked = true;
                    setDone(row, true);
                }
                saveSoon();
            });
            row.area.addEventListener('blur', () => { if (timer) saveNow(); });
            requestAnimationFrame(() => autosize(row.area));
        }
        // Una nota per voce (es. i font scelti per le bozze): "+ nota" la apre.
        let noteBox = null;
        if (item.note !== null && item.note !== undefined && data.itemNoteAction) {
            const noteArea = el('textarea', { className: 'tw-answer tw-note', rows: '1', placeholder: 'Nota su questa voce (es. Inter e Playfair, #12333F)', 'aria-labelledby': textId });
            noteArea.value = item.note || '';
            let noteTimer = null;
            const saveNote = async () => {
                clearTimeout(noteTimer);
                setStatus('Salvataggio\u2026');
                try {
                    await post(data.itemNoteAction, { index: String(item.index), text: noteArea.value });
                    setStatus('Salvato', 'ok');
                } catch (e) {
                    setStatus('Non salvato: riprova', 'error');
                }
            };
            noteArea.addEventListener('input', () => { autosize(noteArea); clearTimeout(noteTimer); noteTimer = setTimeout(saveNote, 900); });
            noteArea.addEventListener('blur', () => { if (noteTimer) saveNote(); });
            const opener = el('button', { type: 'button', className: 'tw-note-open' }, [icon('plus'), 'nota']);
            noteArea.hidden = !(item.note || '').trim();
            opener.hidden = !noteArea.hidden;
            opener.addEventListener('click', () => { opener.hidden = true; noteArea.hidden = false; autosize(noteArea); noteArea.focus(); });
            requestAnimationFrame(() => autosize(noteArea));
            noteBox = el('div', { className: 'tw-note-box' }, [opener, noteArea]);
        }
        const li = el('li', { className: 'tw-item' + (checkable ? '' : ' tw-item--extra') }, [
            checkable ? check : el('span', { className: 'tw-bullet', 'aria-hidden': 'true' }, [icon('plus')]),
            el('div', { className: 'tw-body' }, [text, row.area, noteBox]),
        ]);
        row.paint = () => {
            li.classList.toggle('is-done', row.done);
            if (check) check.setAttribute('aria-checked', row.done ? 'true' : 'false');
        };
        row.paint();
        rows.push(row);
        return li;
    };

    const list = el('ul', { className: 'tw-list' }, [
        ...(data.items || []).map((i) => makeRow(i, true)),
        ...(data.extras || []).map((i) => makeRow(i, false)),
    ]);
    paintProgress();

    // Il lavoro si fa da un'altra parte (es. il preventivo): il pulsante per
    // andarci e, se la fase si chiude da sola, niente "Segna fatta".
    const actionButtons = (data.actions || []).map((a) => {
        if (a.href) {
            return el('a', { className: 'btn btn--primary', href: a.href }, [icon(a.icon || 'arrow-right'), a.label]);
        }
        const b = el('button', { type: 'button', className: 'btn btn--primary' }, [icon(a.icon || 'arrow-right'), a.label]);
        b.addEventListener('click', () => runAction(a.action, { button: b }));
        return b;
    });

    // "Comporre la squadra": ogni ruolo con le sue persone; si aggiunge e si toglie qui.
    const teamBlock = data.team ? teamEditor(data.team, data.done) : null;
    // Data chiave: cosa e' stato fissato (data, email mandata) e il form
    // della data (chiuso se c'e' gia': "Cambia la data"); salva e resta qui.
    const formBlock = data.embedForm || data.milestone ? el('div', { className: 'tw-embed' }, [
        data.milestone ? milestoneDone(data.milestone) : null,
        data.embedForm ? renderComponent(data.embedForm) : null,
    ]) : null;
    // Consegna: le cose da consegnare, da spuntare qui.
    const deliveryBlock = data.deliverables ? deliveryList(data.deliverables, data.done) : null;
    // Appuntamento: quelli del progetto e "Fissa l'appuntamento" (nel pannello).
    const meetingBlock = data.appointments ? meetingList(data.appointments) : null;
    // Esito e note della fase (si salvano mentre si scrive).
    const outcomeBlock = data.outcome ? outcomeEditor(data.outcome, setStatus) : null;
    // I file della fase: sul Drive nella cartella della fase, o in attesa.
    const filesBlock = data.files ? filesPanel(data.files) : null;
    // Approvazione finale del cliente: data ed email, poi la fase si chiude.
    const approvalBlock = data.clientApproval ? clientApprovalPanel(data.clientApproval) : null;
    // Approva / rimanda indietro, modifiche chieste, "Non la facciamo".
    const stepButtons = data.stepActions && data.stepActions.length ? actionsRow(data.stepActions) : null;

    let footer = null;
    if (!data.done && data.autoClose) {
        footer = el('div', { className: 'tw-footer' }, [
            el('span', { className: 'tw-status tw-status--auto' }, [icon('arrows-rotate'), data.autoClose]),
            ...actionButtons,
            stepButtons,
        ]);
    } else if (!data.done && data.clientApproval) {
        // L'approvazione del cliente si chiude dal suo pulsante, qui sopra.
        footer = el('div', { className: 'tw-footer' }, [status, stepButtons]);
    } else if (!data.done) {
        const complete = el('button', { type: 'button', className: 'btn btn--primary' }, [icon('check'), data.completeLabel || 'Segna fatta']);
        complete.addEventListener('click', () => {
            clearTimeout(timer);
            if (data.saveAction) {
                runAction(data.saveAction, { button: complete, body: { complete: '1', ...answers() } });
            } else {
                runAction(data.toggleAction, { button: complete, confirm: data.completeConfirm || null });
            }
        });
        footer = el('div', { className: 'tw-footer' }, [status, ...actionButtons.map((b) => { b.className = 'btn btn--secondary'; return b; }), stepButtons, complete]);
    } else if (data.withAnswers) {
        footer = el('div', { className: 'tw-footer' }, [status]);
    } else if (actionButtons.length) {
        // A fase fatta resta il pulsante per andare dove si e' lavorato (es. il preventivo).
        footer = el('div', { className: 'tw-footer' }, actionButtons.map((b) => { b.className = 'btn btn--secondary'; return b; }));
    }

    return el('section', { className: 'card acc-section tw' }, [
        el('div', { className: 'tw-head' }, [
            el('h2', { className: 'card__title' }, [data.title || 'Da fare', g.button]),
            progress,
        ]),
        data.hint ? el('p', { className: 'tw-hint' }, [data.hint]) : null,
        data.note ? el('p', { className: 'kn-hint' }, [data.note]) : null,
        teamBlock,
        formBlock,
        deliveryBlock,
        meetingBlock,
        approvalBlock,
        rows.length ? list : (data.outcome || data.files || data.clientApproval || data.done || data.autoClose || data.team || data.deliverables || data.appointments || data.embedForm || data.milestone ? null : el('p', { className: 'acc-empty' }, ['Nessuna voce: quando hai finito, segna l’attivita’ come fatta.'])),
        outcomeBlock,
        filesBlock,
        footer,
    ]);
}

// L'esito della fase: un titolo che dice cosa scrivere e il testo, salvato da solo.
function outcomeEditor(o, setStatus) {
    const area = el('textarea', { className: 'tw-answer', rows: '3', placeholder: o.placeholder || '', 'aria-label': o.label });
    area.value = o.value || '';
    let timer = null;
    const save = async () => {
        clearTimeout(timer);
        setStatus('Salvataggio\u2026');
        try {
            await post(o.action, { text: area.value });
            setStatus('Salvato', 'ok');
        } catch (e) {
            setStatus('Non salvato: riprova', 'error');
        }
    };
    area.addEventListener('input', () => { autosize(area); clearTimeout(timer); timer = setTimeout(save, 900); });
    area.addEventListener('blur', () => { if (timer) save(); });
    requestAnimationFrame(() => autosize(area));
    return el('div', { className: 'tw-block' }, [
        el('h3', { className: 'tw-block__title' }, [icon('pen'), o.label]),
        area,
    ]);
}

// I file della fase: dove vanno (cartella del Drive, o in attesa), l'elenco
// e "Carica file" (anche piu' di uno). Tutto resta collegato alla fase.
function filesPanel(f) {
    const input = el('input', { type: 'file', multiple: '', className: 'tw-file-input', 'aria-label': 'Scegli i file' });
    const button = el('button', { type: 'button', className: 'btn btn--secondary btn--small' }, [icon('plus'), 'Carica file']);
    button.addEventListener('click', () => input.click());
    input.addEventListener('change', async () => {
        const files = Array.from(input.files || []);
        if (!files.length) return;
        button.disabled = true;
        let ok = 0;
        for (const file of files) {
            const body = new FormData();
            body.append('file', file);
            try {
                const res = await fetch(f.uploadAction, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '' }, body });
                const out = await res.json();
                if (!out.ok) throw new Error(out.message || '');
                ok++;
            } catch (e) {
                toast(`Non caricato: ${file.name}. ${e.message || ''}`.trim(), 'error');
            }
        }
        button.disabled = false;
        if (ok) {
            toast(ok === 1 ? 'File caricato.' : `${ok} file caricati.`);
            reloadCurrentScreen();
        }
    });
    const where = f.driveReady
        ? `Vanno sul Drive, nella cartella del progetto${f.folder ? ` \u203a ${f.folder}` : ''}.`
        : 'Il Drive non e\u2019 collegato: i file restano qui e partono da soli quando lo colleghi.';
    return el('div', { className: 'tw-block' }, [
        el('div', { className: 'tw-block__head' }, [
            el('h3', { className: 'tw-block__title' }, [icon('folder'), 'File', f.items.length ? el('span', { className: 'tw-progress' }, [String(f.items.length)]) : null]),
            button,
            input,
        ]),
        el('p', { className: 'tw-line-desc' }, [where]),
        f.items.length ? el('ul', { className: 'tw-files' }, f.items.map((d) => el('li', {}, [
            el('a', { className: 'tw-file', href: d.href, target: '_blank', rel: 'noopener' }, [
                el('span', { className: 'tw-file__icon' }, [icon(d.image ? 'image' : 'file-lines')]),
                el('span', { className: 'tw-file__text' }, [
                    el('span', { className: 'tw-file__name' }, [d.name]),
                    el('span', { className: 'tw-line-desc' }, [[d.meta, d.description].filter(Boolean).join(' \u00b7 ')]),
                ]),
                d.pending ? el('span', { className: 'wk-chip wk-chip--warning' }, ['in attesa del Drive']) : null,
            ]),
        ]))) : el('p', { className: 'acc-empty' }, ['Nessun file ancora: bozze, foto, PDF... caricali qui, cosi\u2019 si ritrovano dal progetto.']),
    ]);
}

// Approvazione finale del cliente: la data e l'email (o come ha approvato).
function clientApprovalPanel(a) {
    if (a.done) {
        return el('div', { className: 'tw-done' }, [
            el('div', { className: 'tw-done__row' }, [
                el('span', { className: 'tw-done__icon' }, [icon('thumbs-up')]),
                el('div', { className: 'tw-done__mail' }, [
                    el('div', { className: 'tw-done__title' }, [a.when ? `Il cliente ha approvato il ${a.when}` : 'Il cliente ha approvato']),
                    a.how ? el('p', { className: 'tw-done__body' }, [a.how]) : null,
                ]),
            ]),
        ]);
    }
    const date = el('input', { type: 'date', className: 'field__input', value: a.date || '', max: new Date().toISOString().slice(0, 10), 'aria-label': 'Data dell\u2019approvazione' });
    const how = el('textarea', { className: 'tw-answer', rows: '4', placeholder: 'Incolla qui l\u2019email con cui il cliente approva (o scrivi come ha approvato: telefono, di persona...)', 'aria-label': 'Email del cliente' });
    how.value = a.how || '';
    const go = el('button', { type: 'button', className: 'btn btn--primary' }, [icon('thumbs-up'), 'Il cliente ha approvato']);
    go.addEventListener('click', () => {
        if (!date.value || !how.value.trim()) {
            toast(!date.value ? 'Scrivi la data dell\u2019approvazione.' : 'Incolla l\u2019email del cliente (o scrivi come ha approvato).', 'error');
            (!date.value ? date : how).focus();
            return;
        }
        runAction(a.action, { button: go, body: { date: date.value, how: how.value } });
    });
    return el('div', { className: 'tw-block' }, [
        el('h3', { className: 'tw-block__title' }, [icon('thumbs-up'), 'Approvazione del cliente']),
        el('label', { className: 'tw-field' }, [el('span', { className: 'tw-field__label' }, ['Data dell\u2019approvazione *']), date]),
        el('label', { className: 'tw-field' }, [el('span', { className: 'tw-field__label' }, ['Email del cliente *']), how]),
        el('p', { className: 'tw-line-desc' }, ['Carica qui sotto anche l\u2019email in PDF, se vuoi tenerla come file.']),
        el('div', { className: 'tw-block__actions' }, [go]),
    ]);
}

// La data chiave fissata: quando, l'appuntamento in calendario e l'email
// mandata al cliente (a chi, quando, oggetto e testo, che si apre).
function milestoneDone(m) {
    return el('div', { className: 'tw-done' }, [
        el('div', { className: 'tw-done__row' }, [
            el('span', { className: 'tw-done__icon' }, [icon('calendar-days')]),
            el('div', {}, [
                el('div', { className: 'tw-done__title' }, [`${m.label}: ${m.when}`]),
                m.appointmentHref ? el('a', { className: 'tw-line-desc', href: m.appointmentHref }, ['In calendario, con la squadra']) : null,
            ]),
        ]),
        m.email ? el('div', { className: 'tw-done__row' }, [
            el('span', { className: 'tw-done__icon tw-done__icon--mail' }, [icon('envelope')]),
            el('div', { className: 'tw-done__mail' }, [
                el('div', { className: 'tw-done__title' }, [`Email mandata a ${m.email.to}`]),
                el('div', { className: 'tw-line-desc' }, [`il ${m.email.sentAt}`]),
                el('details', { className: 'tw-done__details' }, [
                    el('summary', {}, [`Oggetto: ${m.email.subject}`]),
                    el('p', { className: 'tw-done__body' }, [m.email.body]),
                ]),
            ]),
        ]) : null,
        m.emailOff ? el('p', { className: 'tw-line-desc' }, ['Nessuna email al cliente (invio spento).']) : null,
    ]);
}

// Le cose da consegnare: spunta a sinistra, si salva subito.
function deliveryList(d, readOnly) {
    if (!d.items.length) {
        return el('p', { className: 'acc-empty' }, ['Nessuna voce da consegnare. ', el('a', { href: d.addHref }, ['Aggiungi'])]);
    }
    return el('ul', { className: 'tw-list' }, d.items.map((item) => {
        const check = el('button', { type: 'button', className: 'tw-check', role: 'checkbox', 'aria-checked': item.done ? 'true' : 'false', 'aria-label': item.label, disabled: readOnly ? '' : null }, [icon('check')]);
        check.addEventListener('click', () => runAction(item.toggleAction, { button: check }));
        return el('li', { className: 'tw-item' + (item.done ? ' is-done' : '') }, [
            check,
            el('div', { className: 'tw-body' }, [
                el('span', { className: 'tw-text' }, [item.label]),
                item.description ? el('span', { className: 'tw-line-desc' }, [item.description]) : null,
            ]),
        ]);
    }));
}

// Gli appuntamenti del progetto e il pulsante per fissarne uno.
function meetingList(a) {
    return el('div', { className: 'tw-embed' }, [
        a.items.length
            ? el('ul', { className: 'tw-list' }, a.items.map((x) => el('li', { className: 'tw-item' }, [
                el('span', { className: `cal-row__dot cal-dot--${x.color}`, 'aria-hidden': 'true' }),
                el('a', { className: 'tw-body', href: x.href }, [
                    el('span', { className: 'tw-text' }, [x.title]),
                    el('span', { className: 'tw-line-desc' }, [[x.dayLabel, x.time, x.location].filter(Boolean).join(' \u00b7 ')]),
                ]),
            ])))
            : el('p', { className: 'acc-empty' }, ['Nessun appuntamento ancora.']),
        el('a', { className: 'btn btn--primary', href: a.addHref }, [icon('calendar'), 'Fissa l\u2019appuntamento']),
    ]);
}

// La squadra del progetto, ruolo per ruolo: chi c'e' (con la x per
// toglierlo) e il menu per aggiungere qualcuno. Ogni cambio si salva subito.
function teamEditor(team, readOnly) {
    const row = (r) => {
        const people = r.people.map((p) => {
            const chip = el('span', { className: 'tw-person' }, [
                personAvatar({ avatar: p.avatar, initials: p.initials, name: p.name }, 24),
                el('span', {}, [p.name]),
            ]);
            if (!readOnly) {
                const x = el('button', { type: 'button', className: 'tw-person__remove', 'aria-label': `Togli ${p.name}` }, [icon('close')]);
                x.addEventListener('click', () => runAction(p.removeAction, { button: x, confirm: `Togliere ${p.name} da \u00ab${r.label}\u00bb?` }));
                chip.appendChild(x);
            }
            return chip;
        });
        let picker = null;
        if (!readOnly) {
            // Scelta la persona, si aggiunge subito (niente pulsante in piu').
            const select = el('select', { className: 'tw-pick' + (r.people.length ? '' : ' tw-pick--empty'), 'aria-label': `Aggiungi a ${r.label}` }, [
                el('option', { value: '' }, [r.people.length ? '+ Aggiungi' : '+ Scegli chi']),
                ...team.users.filter((u) => !r.people.some((p) => p.name === u.name)).map((u) => el('option', { value: String(u.id) }, [u.name + (r.usual === u.name ? ' (di solito)' : '')])),
            ]);
            select.addEventListener('change', () => {
                if (!select.value) return;
                select.disabled = true;
                runAction(team.assignAction, { body: { role_id: String(r.roleId), user_id: select.value } }).finally(() => { select.disabled = false; });
            });
            picker = select;
        }
        const missing = r.required && !r.people.length;
        return el('li', { className: 'tw-role' + (missing ? ' tw-role--missing' : '') }, [
            el('div', { className: 'tw-role__head' }, [
                el('span', { className: 'tw-role__label' }, [r.label]),
                missing ? el('span', { className: 'wk-chip wk-chip--warning' }, ['da scegliere']) : null,
                !r.required ? el('span', { className: 'wk-chip' }, ['facoltativo']) : null,
            ]),
            el('div', { className: 'tw-people' }, [
                ...people,
                picker,
                r.usual && !r.people.length ? el('span', { className: 'tw-role__usual' }, [`di solito ${r.usual}`]) : null,
            ]),
        ]);
    };
    return el('ul', { className: 'tw-team' }, team.roles.map(row));
}

registerComponent('task-summary', renderTaskSummary);
registerComponent('task-work', renderTaskWork);
