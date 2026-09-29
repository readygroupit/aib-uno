// Mostra/nascondi password sul login - unica interattivita' di quella
// pagina, per questo un file a se' invece di infilarlo in hero.js (che
// presuppone #page-root, mai presente sulle pagine di autenticazione).
// Guardia sull'esistenza del pulsante, stesso principio di boot() in
// app.js per #page-root: nessun errore sulle pagine senza questo bottone.
function initPasswordToggles() {
    document.querySelectorAll('[data-auth-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.authPasswordToggle);
        if (!input) return;

        button.addEventListener('click', () => {
            const shown = input.type === 'text';
            input.type = shown ? 'password' : 'text';
            button.textContent = shown ? 'Mostra' : 'Nascondi';
        });
    });
}

document.addEventListener('DOMContentLoaded', initPasswordToggles);
