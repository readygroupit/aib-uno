import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge, toast, reloadCurrentScreen, runAction } from '../accounts-ui.js';
import { actionsRow } from '../work-ui.js';

// Un contenuto come lo vedra' il pubblico (anteprima tipo telefono) e
// accanto a che punto e', cosa ha detto il cliente e il passo successivo.
function phone(data) {
    return el('div', { className: 'ed-phone' }, [
        el('div', { className: 'ed-phone__top' }, [
            el('span', { className: 'ed-phone__avatar' }, [(data.customer || '?').slice(0, 1).toUpperCase()]),
            el('span', { className: 'ed-phone__handle' }, [data.handle]),
            el('span', { className: 'ed-phone__channel' }, [data.channel]),
        ]),
        el('div', { className: `ed-phone__media ed-phone__media--${data.format}` }, [
            icon(data.format === 'reel' || data.format === 'video' ? 'video' : 'image'),
            el('span', {}, [data.mediaNotes || data.format]),
        ]),
        el('div', { className: 'ed-phone__caption' }, data.caption
            ? [el('strong', {}, [data.handle.replace('@', '') + ' ']), data.caption]
            : [el('span', { className: 'acc-muted' }, ['Testo ancora da scrivere.'])]),
    ]);
}

// Dove esce (pagina o profilo del cliente), i file da pubblicare (si
// caricano e si tolgono qui) e cosa manca per pubblicare: vedi SocialPublisher.
function socialPanel(social) {
    if (!social || !social.supported) return null;
    const input = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,video/mp4,video/quicktime', multiple: true, className: 'ed-social__file' });
    const upload = social.uploadAction
        ? el('button', { type: 'button', className: 'btn btn--secondary btn--small' }, [icon('image'), 'Carica foto o video'])
        : null;
    if (upload) {
        upload.addEventListener('click', () => input.click());
        input.addEventListener('change', async () => {
            upload.disabled = true;
            for (const file of [...input.files]) {
                const body = new FormData();
                body.append('file', file);
                try {
                    const response = await fetch(social.uploadAction, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '' }, body });
                    const result = await response.json();
                    if (!result.ok) toast(`${file.name}: ${result.message}`, 'error');
                } catch (e) {
                    toast(`${file.name}: non caricato (troppo grande o connessione interrotta).`, 'error');
                }
            }
            upload.disabled = false;
            await reloadCurrentScreen();
        });
    }

    const status = social.publishedAt
        ? el('p', { className: 'ed-social__status ed-social__status--done' }, [icon('circle-check'), `Pubblicato dal gestionale il ${social.publishedAt.slice(8, 10)}/${social.publishedAt.slice(5, 7)} alle ${social.publishedAt.slice(11, 16)}`])
        : social.scheduled
            ? el('p', { className: 'ed-social__status ed-social__status--scheduled' }, [icon('calendar'), 'Programmato: esce da solo all\'ora di uscita.'])
            : null;

    return el('section', { className: 'card acc-section ed-social' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, ['Da pubblicare']),
            social.account ? el('span', { className: 'acc-group__total' }, [social.account]) : null,
        ]),
        social.test ? el('p', { className: 'acc-share__test' }, [icon('circle-exclamation'), 'Modalita\' prova: la pubblicazione e\' simulata, sul social non esce niente.']) : null,
        status,
        social.error ? el('p', { className: 'ed-social__error' }, [icon('circle-exclamation'), `Non pubblicato: ${social.error}`]) : null,
        social.blocker ? el('p', { className: 'acc-muted ed-social__blocker' }, [social.blocker]) : null,
        social.files.length
            ? el('div', { className: 'ed-social__files' }, social.files.map((f) => el('div', { className: 'ed-social__item' }, [
                f.video
                    ? el('video', { src: f.url, className: 'ed-social__thumb', muted: true, preload: 'metadata' })
                    : el('img', { src: f.url, alt: f.name, className: 'ed-social__thumb', loading: 'lazy' }),
                social.uploadAction ? el('button', { type: 'button', className: 'ed-social__remove', title: 'Togli', onClick: (e) => runAction(f.remove, { button: e.currentTarget }) }, ['×']) : null,
            ])))
            : null,
        upload ? el('div', { className: 'ed-social__actions' }, [upload, input, el('span', { className: 'acc-muted' }, ['Foto JPG/PNG (max 8 MB) o video MP4 (max 300 MB), nell\'ordine di uscita.'])]) : null,
    ]);
}

function renderPostSummary(data) {
    const facts = [
        data.customer ? el('a', { className: 'acc-fact', href: data.customerHref }, [icon('user'), data.customer]) : null,
        el('span', { className: 'acc-fact' }, [icon('calendar'), data.when]),
        el('span', { className: 'acc-fact' }, [icon('hashtag'), `${data.channel} · ${data.format}`]),
        data.writer ? el('span', { className: 'acc-fact' }, [icon('pen'), `Testo: ${data.writer}`]) : null,
        data.who ? el('span', { className: 'acc-fact' }, [icon('image'), `${data.writer ? 'Lo realizza' : 'Lo prepara'} ${data.who}`]) : null,
        data.revisions > 0 ? el('span', { className: 'acc-fact' }, [icon('arrows-rotate'), `${data.revisions} ${data.revisions === 1 ? 'giro' : 'giri'} di modifiche`]) : null,
        data.parent ? el('a', { className: 'acc-fact', href: data.parent.href }, [icon('link'), `Dal post: ${data.parent.title}`]) : null,
        ...(data.children || []).map((c) => el('a', { className: 'acc-fact', href: c.href }, [icon('link'), `${c.format}: ${c.title}`])),
        data.sourceLink ? el('a', { className: 'acc-fact', href: data.sourceLink, target: '_blank', rel: 'noopener' }, [icon('download'), 'Materiale']) : null,
        data.outputLink ? el('a', { className: 'acc-fact', href: data.outputLink, target: '_blank', rel: 'noopener' }, [icon('file-invoice'), 'File finito']) : null,
        data.publishedUrl ? el('a', { className: 'acc-fact', href: data.publishedUrl, target: '_blank', rel: 'noopener' }, [icon('link'), 'Vedi pubblicato']) : null,
    ].filter(Boolean);

    return el('div', { className: 'acc-profile' }, [
        el('section', { className: 'card acc-profile__head' }, [
            el('div', { className: 'acc-profile__identity' }, [
                el('div', { className: 'acc-profile__titles' }, [
                    el('div', { className: 'acc-profile__tags' }, [badge(data.stage.charAt(0).toUpperCase() + data.stage.slice(1), data.stageTone)]),
                    el('h1', { className: 'acc-profile__name' }, [data.title]),
                    el('div', { className: 'acc-profile__facts' }, facts),
                ]),
                actionsRow(data.actions.filter((a) => a.variant !== 'ghost')),
            ]),
            // Le azioni di contorno (link, IA, scarta) sotto: il passo successivo resta in vista da solo.
            data.actions.some((a) => a.variant === 'ghost') ? el('div', { className: 'ed-more' }, [actionsRow(data.actions.filter((a) => a.variant === 'ghost'))]) : null,
            // Le due approvazioni, a parte, con chi e quando.
            data.internalApproval || data.clientApproval
                ? el('div', { className: 'ed-approvals' }, [
                    data.internalApproval ? el('span', { className: 'ed-approvals__item' }, [icon('circle-check'), data.internalApproval]) : null,
                    data.clientApproval ? el('span', { className: 'ed-approvals__item' }, [icon('thumbs-up'), data.clientApproval]) : null,
                ].filter(Boolean))
                : null,
            data.reviewNote && data.stage === 'da modificare' && !data.feedback
                ? el('div', { className: 'acc-suggestion acc-suggestion--warning' }, [
                    el('span', { className: 'acc-suggestion__icon' }, [icon('comment')]),
                    el('div', {}, [
                        el('span', { className: 'acc-suggestion__title' }, ['Dalla revisione interna']),
                        el('p', { className: 'acc-suggestion__text' }, [data.reviewNote]),
                    ]),
                ])
                : null,
            data.feedback && data.stage === 'da modificare'
                ? el('div', { className: 'acc-suggestion acc-suggestion--danger' }, [
                    el('span', { className: 'acc-suggestion__icon' }, [icon('comment')]),
                    el('div', {}, [
                        el('span', { className: 'acc-suggestion__title' }, ['Il cliente chiede']),
                        el('p', { className: 'acc-suggestion__text' }, [data.feedback]),
                    ]),
                ])
                : null,
        ]),
        socialPanel(data.social),
        el('section', { className: 'card acc-section ed-preview' }, [
            el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Anteprima'])]),
            phone(data),
        ]),
    ]);
}

registerComponent('post-summary', renderPostSummary);
