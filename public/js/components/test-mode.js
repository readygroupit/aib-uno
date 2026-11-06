import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { toast } from '../accounts-ui.js';

// Configurazione, "Modalita' prova" (vedi TestModeService): l'interruttore,
// dove mandare email e WhatsApp in prova, cosa resta reale, e il registro
// di cosa sarebbe successo davvero.

function renderTestMode(data) {
    const root = el('div', { className: 'acc-board test-mode' });
    const draw = (current) => {
        root.textContent = '';
        const enabled = el('input', { type: 'checkbox', className: 'test-mode__switch-input', checked: current.enabled ? 'checked' : null });
        const email = el('input', { type: 'email', className: 'field__input', value: current.email || '', placeholder: 'prove@vostraazienda.it' });
        const phone = el('input', { type: 'text', className: 'field__input', value: current.phone || '', placeholder: '+39 333 1234567' });
        const reals = current.effects.map((e) => ({ key: e.key, box: el('input', { type: 'checkbox', checked: e.real ? 'checked' : null }) }));
        const save = el('button', { type: 'button', className: 'btn btn--primary' }, ['Salva']);

        save.addEventListener('click', async () => {
            if (!enabled.checked && current.enabled && !window.confirm('Spegnere la modalita\' prova? Da adesso email, WhatsApp, pagamenti, fatture e social partono davvero.')) {
                return;
            }
            save.disabled = true;
            const response = await fetch(current.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '' },
                body: JSON.stringify({ enabled: enabled.checked, email: email.value, phone: phone.value, real: reals.filter((r) => r.box.checked).map((r) => r.key) }),
            });
            const result = await response.json().catch(() => ({ ok: false, message: 'Non salvato.' }));
            toast(result.message, result.ok ? 'success' : 'error');
            save.disabled = false;
            // La fascia in cima cambia con la pagina: si ricarica.
            if (result.ok) window.location.reload();
        });

        root.append(
            el('div', { className: 'claim-table__header' }, [el('h1', { className: 'claim-table__title' }, [current.title])]),
            el('section', { className: 'card acc-section' }, [
                el('label', { className: 'test-mode__switch' }, [
                    enabled,
                    el('span', {}, [el('strong', {}, ['Modalita\' prova accesa']), el('span', { className: 'acc-muted' }, [' — quello che va verso clienti, soldi e social e\' simulato e finisce nel registro qui sotto. Agli utenti del gestionale si scrive normalmente.'])]),
                ]),
                el('div', { className: 'test-mode__fields' }, [
                    el('label', { className: 'field' }, [el('span', { className: 'field__label' }, ['Email per le prove']), email, el('span', { className: 'field__help' }, ['Le email ai clienti arrivano qui, con il destinatario vero nell\'oggetto. Vuota: non parte nessuna email, resta solo il registro.'])]),
                    el('label', { className: 'field' }, [el('span', { className: 'field__label' }, ['Numero WhatsApp per le prove']), phone, el('span', { className: 'field__help' }, ['I WhatsApp ai clienti arrivano qui (serve WhatsApp collegato). Vuoto: solo registro.'])]),
                ]),
                el('h3', { className: 'test-mode__subtitle' }, ['Cosa resta reale anche in prova']),
                el('div', { className: 'test-mode__effects' }, current.effects.map((e, i) => el('label', { className: 'test-mode__effect' }, [reals[i].box, e.label]))),
                el('div', { className: 'test-mode__actions' }, [save]),
            ]),
            el('section', { className: 'card acc-section' }, [
                el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Cosa sarebbe successo davvero']), current.log.length ? el('span', { className: 'acc-count' }, [String(current.log.length)]) : null]),
                current.log.length
                    ? el('div', { className: 'test-mode__log' }, current.log.map((l) => el('details', { className: 'test-mode__entry' }, [
                        el('summary', {}, [el('span', { className: 'test-mode__when' }, [l.when]), el('span', { className: 'test-mode__effect-tag' }, [l.effect]), el('span', {}, [l.summary])]),
                        l.recipient ? el('p', { className: 'acc-muted' }, [`A: ${l.recipient}`]) : null,
                        l.detail ? el('pre', { className: 'test-mode__detail' }, [l.detail]) : null,
                    ])))
                    : el('p', { className: 'acc-empty' }, [icon('circle-check'), ' Ancora niente: qui compare ogni email, WhatsApp, pagamento, fattura o post simulato.']),
            ]),
        );
    };
    draw(data);

    return root;
}

registerComponent('test-mode', renderTestMode);
