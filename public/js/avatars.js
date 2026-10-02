/**
 * Volti degli agenti: illustrazioni SVG generate da pochi parametri
 * (carnagione, capelli, camicia...), disegnate qui - nessuna immagine
 * esterna, quindi nessuna dipendenza e nessuna foto di persone reali.
 * Un agente non nel catalogo riceve un volto neutro.
 */
const SVG_NS = 'http://www.w3.org/2000/svg';

const FACES = {
    lead_intake:    { bg: '#e9f0ef', skin: '#f2c9a5', hair: '#3b2a20', style: 'long',   shirt: '#5f8a86', glasses: false },
    qualification:  { bg: '#eeecf3', skin: '#d9a77e', hair: '#1f1a17', style: 'short',  shirt: '#8b83ad', glasses: true },
    follow_up:      { bg: '#f6f0df', skin: '#f5d3b3', hair: '#8a4b2a', style: 'bun',    shirt: '#b99333', glasses: false },
    documents:      { bg: '#eaf1ea', skin: '#c68e63', hair: '#2a2420', style: 'curly',  shirt: '#4f7b5c', glasses: false },
    legal:          { bg: '#f6e6e2', skin: '#f2c9a5', hair: '#6b3a22', style: 'bob',    shirt: '#8e3a29', glasses: true },
    conciliaweb:    { bg: '#e9f0ef', skin: '#e0b08a', hair: '#17304a', style: 'short',  shirt: '#1f5566', glasses: false },
    administration: { bg: '#eeecf3', skin: '#8d5a3b', hair: '#18120f', style: 'afro',   shirt: '#5b5480', glasses: false },
    reporter:       { bg: '#f6f0df', skin: '#f5d3b3', hair: '#c9a23a', style: 'long',   shirt: '#12333f', glasses: true },
};

const FALLBACK = { bg: '#e5e3dd', skin: '#e0b08a', hair: '#3b2a20', style: 'short', shirt: '#12333f', glasses: false };

function node(tag, attrs) {
    const n = document.createElementNS(SVG_NS, tag);
    Object.entries(attrs).forEach(([k, v]) => n.setAttribute(k, v));
    return n;
}

// Capelli dietro la testa (lunghi/chignon/afro) e sopra la fronte.
function hairBack(style, color) {
    if (style === 'long') return [node('path', { d: 'M22 52 C20 28 34 20 50 20 C66 20 80 28 78 52 L80 84 L20 84 Z', fill: color })];
    if (style === 'bob') return [node('path', { d: 'M23 56 C21 30 34 21 50 21 C66 21 79 30 77 56 L77 66 L23 66 Z', fill: color })];
    if (style === 'afro') return [node('circle', { cx: 50, cy: 38, r: 25, fill: color })];
    if (style === 'bun') return [node('circle', { cx: 50, cy: 15, r: 8, fill: color })];
    return [];
}

function hairFront(style, color) {
    if (style === 'short') return [node('path', { d: 'M28 40 C28 24 40 20 50 20 C62 20 72 25 72 40 C66 32 58 30 50 31 C42 30 34 32 28 40 Z', fill: color })];
    if (style === 'curly') return [0, 1, 2, 3, 4].map((i) => node('circle', { cx: 31 + i * 9.5, cy: 31 - (i % 2) * 3, r: 7.5, fill: color }));
    return [node('path', { d: 'M28 42 C28 24 40 20 50 20 C62 20 72 24 72 42 C64 33 56 31 50 32 C42 31 34 33 28 42 Z', fill: color })];
}

export function agentAvatar(code, size = 40) {
    const face = FACES[code] || FALLBACK;
    const svg = node('svg', { viewBox: '0 0 100 100', width: size, height: size, role: 'img', 'aria-hidden': 'true' });
    svg.classList.add('avatar-face');

    const clip = node('clipPath', { id: 'face-clip-' + code });
    clip.appendChild(node('circle', { cx: 50, cy: 50, r: 50 }));
    svg.appendChild(clip);

    const group = node('g', { 'clip-path': `url(#face-clip-${code})` });
    group.appendChild(node('rect', { width: 100, height: 100, fill: face.bg }));
    hairBack(face.style, face.hair).forEach((n) => group.appendChild(n));
    group.appendChild(node('path', { d: 'M14 100 C14 80 30 72 50 72 C70 72 86 80 86 100 Z', fill: face.shirt }));
    group.appendChild(node('rect', { x: 43, y: 58, width: 14, height: 18, rx: 5, fill: face.skin }));
    group.appendChild(node('ellipse', { cx: 50, cy: 46, rx: 19, ry: 22, fill: face.skin }));
    hairFront(face.style, face.hair).forEach((n) => group.appendChild(n));
    group.appendChild(node('circle', { cx: 42, cy: 47, r: 2.2, fill: '#1d1a17' }));
    group.appendChild(node('circle', { cx: 58, cy: 47, r: 2.2, fill: '#1d1a17' }));
    group.appendChild(node('path', { d: 'M43 57 Q50 62 57 57', fill: 'none', stroke: '#8a4f3a', 'stroke-width': 2, 'stroke-linecap': 'round' }));
    group.appendChild(node('circle', { cx: 36, cy: 54, r: 3.4, fill: '#e58f80', opacity: 0.35 }));
    group.appendChild(node('circle', { cx: 64, cy: 54, r: 3.4, fill: '#e58f80', opacity: 0.35 }));
    if (face.glasses) {
        group.appendChild(node('circle', { cx: 42, cy: 47, r: 6.5, fill: 'none', stroke: '#1d1a17', 'stroke-width': 1.6 }));
        group.appendChild(node('circle', { cx: 58, cy: 47, r: 6.5, fill: 'none', stroke: '#1d1a17', 'stroke-width': 1.6 }));
        group.appendChild(node('path', { d: 'M48.5 47 H51.5', stroke: '#1d1a17', 'stroke-width': 1.6 }));
    }
    svg.appendChild(group);

    return svg;
}
