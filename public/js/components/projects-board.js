import { registerComponent } from './registry.js';
import { el } from '../dom.js';

function slugify(text) {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
}

const STATUS_LABELS = {
    queued: 'In coda',
    running: 'In creazione',
    failed: 'Non riuscito',
};

// Copia il link di primo accesso (slug cifrato) da mandare a chi
// amministrera' il progetto: apre il wizard finche' non e' configurato.
function copyButton(url) {
    const button = el('button', { className: 'btn btn--secondary btn--small', type: 'button' }, ['Copia link primo accesso']);
    button.addEventListener('click', () => {
        navigator.clipboard.writeText(url).then(() => {
            button.textContent = 'Link copiato';
            setTimeout(() => { button.textContent = 'Copia link primo accesso'; }, 2000);
        }).catch(() => window.prompt('Link di primo accesso', url));
    });
    return button;
}

function projectRow(project) {
    const meta = [project.preset || 'Da zero', `${project.packages.length} pacchetti`];
    if (project.withDemoData) meta.push('con dati demo');
    const status = project.provisioningStatus || 'ready';
    const ready = status === 'ready';

    const text = [
        el('span', { className: 'project-row__name' }, [
            project.name,
            ready ? null : el('span', { className: `project-row__status project-row__status--${status}` }, [STATUS_LABELS[status] || status]),
        ].filter(Boolean)),
        el('span', { className: 'project-row__meta' }, [meta.join(' · ')]),
    ];
    if (status === 'failed' && project.provisioningLog) {
        text.push(el('span', { className: 'project-row__log' }, [project.provisioningLog]));
    }

    return el('div', { className: 'project-row' }, [
        el('div', { className: 'project-row__text' }, text),
        el('a', { className: 'project-row__url', href: project.url, target: '_blank', rel: 'noopener' }, [project.url.replace(/^https?:\/\//, '')]),
        ready ? copyButton(project.firstAccessUrl) : null,
        ready ? el('a', { className: 'btn btn--secondary btn--small', href: project.url, target: '_blank', rel: 'noopener' }, ['Apri']) : null,
    ].filter(Boolean));
}

function newProjectForm(data, onCreated) {
    const byName = new Map(data.packages.map((p) => [p.name, p]));
    const checks = new Map();
    let slugTouched = false;
    let preset = null;

    const nameInput = el('input', { className: 'field__input', type: 'text', id: 'project-name', placeholder: 'Es. Assilevi', autocomplete: 'off' });
    const slugInput = el('input', { className: 'field__input', type: 'text', id: 'project-slug', placeholder: 'es. assilevi', autocomplete: 'off', spellcheck: 'false' });
    const slugHint = el('span', { className: 'field__help' });
    const updateHint = () => { slugHint.textContent = slugInput.value ? `Sara' raggiungibile su ${(data.hostPattern || '%s').replace('%s', slugInput.value)}` : 'Diventa l\'indirizzo del progetto.'; };
    nameInput.addEventListener('input', () => { if (!slugTouched) slugInput.value = slugify(nameInput.value); updateHint(); });
    slugInput.addEventListener('input', () => { slugTouched = true; updateHint(); });
    updateHint();

    const demo = el('input', { type: 'checkbox', id: 'project-demo' });
    const demoRow = el('label', { className: 'project-form__check', for: 'project-demo' }, [demo, 'Carica i dati demo del preset (contatti, pratiche, documenti...)']);
    demoRow.hidden = true;

    const included = el('p', { className: 'field__help project-form__included' });
    function refreshIncluded() {
        const chosen = [...checks].filter(([, box]) => box.checked).map(([name]) => name);
        const extra = new Set();
        chosen.forEach((name) => (byName.get(name)?.requires || []).forEach((r) => { if (!chosen.includes(r)) extra.add(r); }));
        included.textContent = extra.size ? `Si portano dietro anche: ${[...extra].map((n) => byName.get(n)?.label || n).join(', ')}.` : '';
    }

    const groups = new Map();
    data.packages.forEach((pkg) => {
        if (!groups.has(pkg.categoryLabel)) groups.set(pkg.categoryLabel, []);
        const box = el('input', { type: 'checkbox', id: 'pkg-' + pkg.name, value: pkg.name });
        box.addEventListener('change', () => {
            if (box.checked) (pkg.requires || []).forEach((r) => { const dep = checks.get(r); if (dep) dep.checked = true; });
            refreshIncluded();
        });
        checks.set(pkg.name, box);
        groups.get(pkg.categoryLabel).push(el('label', { className: 'project-pkg', for: 'pkg-' + pkg.name }, [
            box,
            el('span', { className: 'project-pkg__text' }, [
                el('span', { className: 'project-pkg__label' }, [pkg.label]),
                el('span', { className: 'project-pkg__desc' }, [pkg.description]),
            ]),
        ]));
    });

    const presetCards = [];
    function choosePreset(next) {
        preset = next;
        presetCards.forEach((card) => card.classList.toggle('is-active', card.dataset.key === (next?.key || '')));
        checks.forEach((box, name) => { box.checked = !!next && next.packages.includes(name); });
        demoRow.hidden = !(next && next.hasDemo);
        demo.checked = !!(next && next.hasDemo);
        refreshIncluded();
    }
    [...data.presets, { key: '', label: 'Da zero', description: 'Nessun pacchetto preselezionato: scegli tu cosa serve.', packages: [], hasDemo: false }].forEach((p) => {
        const card = el('button', { type: 'button', className: 'project-preset' }, [
            el('span', { className: 'project-preset__label' }, [p.label]),
            el('span', { className: 'project-preset__desc' }, [p.description]),
        ]);
        card.dataset.key = p.key;
        card.addEventListener('click', () => choosePreset(p.key ? p : null));
        presetCards.push(card);
    });

    const error = el('div', { className: 'form__message form__message--error', role: 'alert', hidden: '' });
    const logList = el('ol', { className: 'project-form__log', hidden: '' });
    const submit = el('button', { type: 'submit', className: 'btn btn--primary' }, ['Crea progetto']);

    const form = el('form', { className: 'project-form', onSubmit: async (e) => {
        e.preventDefault();
        error.hidden = true;
        logList.hidden = true;
        logList.textContent = '';
        submit.disabled = true;
        submit.textContent = 'Creazione in corso...';

        const response = await fetch('/progetti/crea', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.UNO_CSRF || '', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                name: nameInput.value,
                slug: slugInput.value,
                preset: preset?.key || null,
                packages: [...checks].filter(([, box]) => box.checked).map(([name]) => name),
                withDemo: !demoRow.hidden && demo.checked,
            }),
        }).catch(() => null);
        const result = response ? await response.json().catch(() => null) : null;

        submit.disabled = false;
        submit.textContent = 'Crea progetto';
        (result?.log || []).forEach((line) => logList.appendChild(el('li', {}, [line])));
        logList.hidden = !(result?.log || []).length;

        if (!result || !result.ok) {
            error.textContent = result?.error || 'Qualcosa non ha funzionato.';
            error.hidden = false;
            return;
        }
        onCreated(result);
        nameInput.value = '';
        slugInput.value = '';
        slugTouched = false;
        updateHint();
    } }, [
        el('div', { className: 'project-form__row' }, [
            el('div', { className: 'field' }, [el('label', { className: 'field__label', for: 'project-name' }, ['Nome del progetto']), nameInput]),
            el('div', { className: 'field' }, [el('label', { className: 'field__label', for: 'project-slug' }, ['Identificativo']), slugInput, slugHint]),
        ]),
        el('h3', { className: 'project-form__step' }, ['Punto di partenza']),
        el('div', { className: 'project-presets' }, presetCards),
        el('h3', { className: 'project-form__step' }, ['Pacchetti']),
        el('div', { className: 'project-pkgs' }, [...groups].map(([label, items]) => el('div', { className: 'project-pkgs__group' }, [
            el('span', { className: 'project-pkgs__title' }, [label]),
            ...items,
        ]))),
        included,
        demoRow,
        error,
        el('div', { className: 'project-form__actions' }, [submit]),
        logList,
    ]);

    choosePreset(data.presets[0] || null);

    return form;
}

function renderProjectsBoard(data) {
    const list = el('div', { className: 'project-list' });
    const success = el('div', { className: 'project-success', role: 'status', hidden: '' });

    function drawList(projects) {
        list.textContent = '';
        if (projects.length === 0) {
            list.appendChild(el('p', { className: 'project-list__empty' }, ['Nessun progetto ancora: creane uno qui sotto.']));
            return;
        }
        projects.forEach((p) => list.appendChild(projectRow(p)));
    }
    drawList(data.projects);

    const children = [
        el('section', { className: 'card' }, [
            el('h2', { className: 'projects-board__title' }, ['Progetti']),
            el('p', { className: 'projects-board__sub' }, ['Ogni progetto e\' un gestionale a se\': codice, database e indirizzo propri, con i soli pacchetti che servono.']),
            success,
            list,
        ]),
    ];

    if (data.canCreate) {
        children.push(el('section', { className: 'card' }, [
            el('h2', { className: 'projects-board__title' }, ['Nuovo progetto']),
            newProjectForm(data, (result) => {
                success.textContent = '';
                if (result.queued) {
                    success.append('Progetto in coda: entro un paio di minuti saranno pronti database, indirizzo e certificato. Poi manda il link di primo accesso a chi lo amministrera\'. ');
                } else {
                    success.append('Progetto pronto. Manda il link di primo accesso a chi lo amministrera\': sceglie lui la password e completa i dati. ');
                    success.appendChild(el('a', { className: 'btn btn--primary btn--small', href: result.firstAccessUrl, target: '_blank', rel: 'noopener' }, ['Apri il primo accesso']));
                }
                success.appendChild(copyButton(result.firstAccessUrl));
                success.hidden = false;
                fetch('/progetti', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then((r) => r.json())
                    .then((fresh) => drawList(fresh.components?.[0]?.projects || []))
                    .catch(() => {});
                success.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }),
        ]));
    }

    return el('div', { className: 'projects-board' }, children);
}

registerComponent('projects-board', renderProjectsBoard);
