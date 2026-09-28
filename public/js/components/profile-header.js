import { registerComponent } from './registry.js';
import { el } from '../dom.js';

function renderProfileHeader(data) {
    const metaItems = [];
    if (data.memberSince) metaItems.push(el('span', {}, [`Da uno · ${data.memberSince}`]));
    if (data.lastLogin) metaItems.push(el('span', {}, [`Ultimo accesso ${data.lastLogin}`]));

    return el('div', { className: 'profile-header' }, [
        el('span', { className: 'profile-header__avatar' }, [data.initials]),
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
