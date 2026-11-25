import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { personAvatar } from '../avatars.js';
import { toast, reloadCurrentScreen } from '../accounts-ui.js';

// La propria foto: si sceglie un'immagine, si carica, la pagina si ricarica.
function avatarBox(data) {
    const box = el('div', { className: 'profile-header__photo' }, [personAvatar({ avatar: data.avatarUrl, initials: data.initials }, 72)]);
    if (!data.avatarAction) return box;
    const input = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', className: 'dc-file-input' });
    const send = async (body) => {
        try {
            const response = await fetch(data.avatarAction, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.UNO_CSRF || '' }, body });
            const result = await response.json();
            toast(result.message, result.ok ? 'success' : 'error');
            if (result.ok) window.location.reload();
        } catch (e) {
            toast('Foto non caricata (troppo grande o connessione interrotta).', 'error');
        }
    };
    input.addEventListener('change', () => {
        if (!input.files[0]) return;
        const body = new FormData();
        body.append('file', input.files[0]);
        send(body);
    });
    const change = el('button', { type: 'button', className: 'btn btn--ghost btn--small' }, [data.avatarUrl ? 'Cambia foto' : 'Aggiungi foto']);
    change.addEventListener('click', () => input.click());
    box.append(change, input);
    if (data.avatarUrl) {
        const remove = el('button', { type: 'button', className: 'btn btn--ghost btn--small' }, ['Togli']);
        remove.addEventListener('click', () => { const body = new FormData(); body.append('remove', '1'); send(body); });
        box.appendChild(remove);
    }
    return box;
}

function renderProfileHeader(data) {
    const metaItems = [];
    if (data.memberSince) metaItems.push(el('span', {}, [`Da uno · ${data.memberSince}`]));
    if (data.lastLogin) metaItems.push(el('span', {}, [`Ultimo accesso ${data.lastLogin}`]));

    return el('div', { className: 'profile-header' }, [
        avatarBox(data),
        el('div', { className: 'profile-header__text' }, [
            el('h1', { className: 'profile-header__name' }, [data.name]),
            el('div', { className: 'profile-header__sub' }, [
                el('span', { className: 'profile-header__role' }, [data.roleLabel]),
                el('span', { className: 'profile-header__email' }, [data.email]),
            ]),
            metaItems.length > 0 ? el('div', { className: 'profile-header__meta' }, metaItems) : null,
        ]),
    ]);
}

registerComponent('profile-header', renderProfileHeader);
