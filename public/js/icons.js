/**
 * Font Awesome 7 Pro (stile "regular"), licenza gia' in uso nel progetto
 * Core - SVG statici copiati nel nostro stesso bundle, non dati esterni
 * o provenienti dal server: qui innerHTML e' sicuro (unica eccezione
 * voluta alla regola "mai innerHTML con dati che vengono dal server",
 * vedi dom.js).
 */
const ICONS = {
    microphone:
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path fill="currentColor" d="M192 48c26.5 0 48 21.5 48 48l0 128c0 26.5-21.5 48-48 48s-48-21.5-48-48l0-128c0-26.5 21.5-48 48-48zM96 96l0 128c0 53 43 96 96 96s96-43 96-96l0-128c0-53-43-96-96-96S96 43 96 96zM48 184c0-13.3-10.7-24-24-24S0 170.7 0 184l0 40c0 97.9 73.3 178.7 168 190.5l0 49.5-48 0c-13.3 0-24 10.7-24 24s10.7 24 24 24l144 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-48 0 0-49.5c94.7-11.8 168-92.6 168-190.5l0-40c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 40c0 79.5-64.5 144-144 144S48 303.5 48 224l0-40z"/></svg>',
    plus:
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M248 56c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 176-176 0c-13.3 0-24 10.7-24 24s10.7 24 24 24l176 0 0 176c0 13.3 10.7 24 24 24s24-10.7 24-24l0-176 176 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-176 0 0-176z"/></svg>',
    'ellipsis-vertical':
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 512"><path fill="currentColor" d="M112 80a48 48 0 1 1 -96 0 48 48 0 1 1 96 0zM16 256c0-26.5 21.5-48 48-48s48 21.5 48 48-21.5 48-48 48-48-21.5-48-48zm96 176c0 26.5-21.5 48-48 48s-48-21.5-48-48 21.5-48 48-48 48 21.5 48 48z"/></svg>',
    'arrow-left':
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M7 239c-9.4 9.4-9.4 24.6 0 33.9L175 441c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9L81.9 280 488 280c13.3 0 24-10.7 24-24s-10.7-24-24-24L81.9 232 209 105c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0L7 239z"/></svg>',
    // Le altre icone qui sopra sono FA7 Pro (regular); queste due sono
    // disegnate a mano con tratto (stroke, non fill) - stessa resa fine
    // ma senza dipendere da path FA memorizzati a occhio per un glifo
    // che FA non aveva pronto in questo stile.
    user:
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="7.5" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M4.5 20c0-4.4 3.7-7 7.5-7s7.5 2.6 7.5 7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    'arrow-right-from-bracket':
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 8l4 4-4 4M17 12H9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'arrow-right':
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 12h15M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    search:
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M19.5 19.5L15 15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    close:
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M3 3l10 10M13 3 3 13" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    sparkle:
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M8 2v3M8 11v3M2 8h3M11 8h3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    home:
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 11.5 12 4l8 7.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 10v9a1 1 0 0 0 1 1h3v-6h4v6h3a1 1 0 0 0 1-1v-9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    grid:
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="7" height="7" rx="1.6" fill="none" stroke="currentColor" stroke-width="1.8"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.6" fill="none" stroke="currentColor" stroke-width="1.8"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.6" fill="none" stroke="currentColor" stroke-width="1.8"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.6" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
    help:
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .8-1 1.5v.4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="16.8" r="0.9" fill="currentColor" stroke="none"/></svg>',
};

export function icon(name, className = '') {
    const span = document.createElement('span');
    span.className = `icon ${className}`.trim();
    span.innerHTML = ICONS[name] || '';
    return span;
}
