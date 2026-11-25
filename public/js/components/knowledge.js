import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { guideParts } from '../guide.js';
import { icon } from '../icons.js';
import { badge, runAction } from '../accounts-ui.js';
import { openModal, closeModal } from '../modal.js';

// Quello che sappiamo del cliente: il profilo (lo usa l'assistente), la
// roadmap interna con lo stato di ogni idea, il diario.
const STATUS_TONE = { 'da valutare': 'neutral', 'piaciuta al cliente': 'info', approvata: 'success', 'in corso': 'info', fatta: 'success' };

// "Diventa un progetto": si sceglie il servizio (il processo) e nasce il progetto.
function toProjectModal(n, types) {
    const select = el('select', { className: 'field__input field__input--select', 'aria-label': 'Servizio del progetto' }, [
        el('option', { value: '' }, ['Scegli il servizio\u2026']),
        ...types.map((t) => el('option', { value: String(t.id) }, [t.name])),
    ]);
    const go = el('button', { type: 'button', className: 'btn btn--primary' }, [icon('plus'), 'Crea il progetto']);
    go.addEventListener('click', () => {
        if (!select.value) { select.focus(); return; }
        closeModal();
        runAction(n.toProject, { body: { type_id: select.value } });
    });
    openModal({
        title: 'Diventa un progetto',
        tone: 'info',
        body: [
            el('p', { className: 'modal__text' }, [`\u00ab${n.title}\u00bb diventa un progetto con le fasi del servizio scelto. L'idea resta in roadmap, collegata: diventa "fatta" alla consegna.`]),
            el('div', { className: 'modal__actions' }, [select, go]),
        ],
    });
    setTimeout(() => select.focus(), 30);
}

function noteRow(n, withStatus, types = []) {
    let toProject = null;
    if (n.toProject && types.length) {
        toProject = el('button', { type: 'button', className: 'btn btn--ghost btn--small' }, [icon('arrow-right'), 'Diventa un progetto']);
        toProject.addEventListener('click', () => toProjectModal(n, types));
    }
    return el('li', { className: 'kn-note' }, [
        el('div', { className: 'kn-note__text' }, [
            el('a', { className: 'kn-note__title', href: n.href }, [n.title]),
            n.body ? el('span', { className: 'kn-note__body' }, [n.body.length > 220 ? n.body.slice(0, 220) + '…' : n.body]) : null,
            el('span', { className: 'kn-note__meta' }, [[n.kind, n.when, n.who, n.people.length ? `con ${n.people.join(', ')}` : null, n.due ? `entro ${n.due}` : null].filter(Boolean).join(' · ')]),
            // Il progetto in cui e' diventata l'idea, con il suo stato.
            n.project ? el('a', { className: 'kn-note__project', href: n.project.href }, [icon('route'), `Progetto: ${n.project.label}`, el('span', { className: 'kn-note__stage' }, [n.project.stage])]) : null,
        ]),
        el('div', { className: 'kn-note__side' }, [
            withStatus && n.status ? badge(n.status, STATUS_TONE[n.status] || 'neutral') : null,
            toProject,
        ]),
    ]);
}

function renderKnowledge(data) {
    const blocks = [];
    blocks.push(el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, ['Roadmap', data.roadmap.length ? el('span', { className: 'acc-count' }, [String(data.roadmap.length)]) : null]),
            el('a', { className: 'btn btn--ghost btn--small', href: data.addIdeaHref }, [icon('plus'), 'Idea']),
        ]),
        el('p', { className: 'kn-hint' }, [`Interna: la vede la squadra del ${data.scope}, non il cliente.`]),
        data.roadmap.length
            ? el('ul', { className: 'kn-notes' }, data.roadmap.map((n) => noteRow(n, true, data.projectTypes || [])))
            : el('p', { className: 'acc-empty' }, ['Nessuna idea in roadmap. ', el('a', { href: data.addIdeaHref }, ['Aggiungi la prima'])]),
    ]));

    blocks.push(el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, ['Cosa sappiamo del cliente']),
            el('a', { className: 'btn btn--ghost btn--small', href: data.profileHref }, [icon('pen'), data.profile.length ? 'Modifica' : 'Compila']),
        ]),
        data.profile.length
            ? el('dl', { className: 'kn-profile' }, data.profile.flatMap((p) => [el('dt', {}, [p.label]), el('dd', {}, [p.text])]))
            : el('p', { className: 'acc-empty' }, ["Ancora niente: si riempie da solo con il briefing, o ", el('a', { href: data.profileHref }, ['compilalo ora']), ". L'assistente lo usa per scrivere e proporre le cose giuste."]),
        data.profileUpdated ? el('p', { className: 'kn-hint' }, [`Aggiornato da ${data.profileUpdated}.`]) : null,
    ]));

    blocks.push(el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, ['Diario']),
            el('a', { className: 'btn btn--ghost btn--small', href: data.addNoteHref }, [icon('plus'), 'Nota']),
        ]),
        data.diary.length
            ? el('ul', { className: 'kn-notes' }, data.diary.map((n) => noteRow(n, false)))
            : el('p', { className: 'acc-empty' }, ['Idee, decisioni, preferenze del cliente: scrivile qui, o dillo al prompt.']),
    ]));

    return el('div', { className: 'kn-knowledge' }, blocks);
}

registerComponent('customer-knowledge', renderKnowledge);

// Cosa ha chiesto il cliente: le informazioni raccolte per il preventivo,
// per chi lo prepara (nella sua fase e nella pagina del preventivo).
function renderQuoteBrief(data) {
    const g = guideParts(data.guide, data.title);
    return el('section', { className: 'card acc-section kn-quote-brief' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, [icon('comment'), data.title, g.button]),
            data.project ? el('a', { className: 'btn btn--ghost btn--small', href: data.project.href }, [icon('arrow-right'), data.project.label]) : null,
        ]),
        data.subtitle ? el('p', { className: 'kn-hint' }, [data.subtitle]) : null,
        el('dl', { className: 'kn-profile' }, (data.items || []).flatMap((p) => [el('dt', {}, [p.label]), el('dd', {}, [p.text])])),
    ]);
}

registerComponent('quote-brief', renderQuoteBrief);
