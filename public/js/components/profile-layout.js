import { registerComponent, renderComponent } from './registry.js';
import { el } from '../dom.js';

// Puramente strutturale (vedi App\View\Component\ProfileLayoutComponent):
// ogni voce di 'main'/'sidebar' e' gia' un dato di componente completo,
// si richiama renderComponent() (lo stesso dispatcher usato per la lista
// di primo livello in registry.js/mountComponents) invece di duplicare
// la logica di disegno qui.
function renderProfileLayout(data) {
    const main = el('div', { className: 'profile-layout__main' }, (data.main || []).map(renderComponent).filter(Boolean));
    const sidebar = el('div', { className: 'profile-layout__sidebar' }, (data.sidebar || []).map(renderComponent).filter(Boolean));

    return el('div', { className: 'profile-layout' }, [main, sidebar]);
}

registerComponent('profile-layout', renderProfileLayout);
