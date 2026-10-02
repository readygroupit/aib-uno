<?php

declare(strict_types=1);

/**
 * Testi della Guida di Uno (vedi public/js/onboarding.js). Un progetto
 * generato riceve i suoi (preset o guida di partenza, vedi
 * packages/provisioning) al posto di questo file.
 */
return [
    // Titolo del primo accesso: Uno si presenta in prima persona.
    'welcome' => 'Ciao, io sono Uno',
    'tabs' => [
        [
            'key' => 'overview',
            'label' => 'Panoramica',
            'blocks' => [
                ['text' => 'Uno costruisce gestionali su misura. Descrivi cosa serve, scegli i pacchetti e in pochi secondi il nuovo progetto e\' online, con il suo database e il suo indirizzo.'],
                ['points' => [
                    ['Progetti', 'i gestionali gia\' generati e il modulo per crearne uno nuovo.'],
                    ['Pacchetti', 'i mattoni disponibili: contatti, clienti, pratiche, documenti, rimborsi, agenti...'],
                    ['Preset', 'punti di partenza pronti per un settore, con dati di esempio.'],
                ]],
            ],
        ],
        [
            'key' => 'journey',
            'label' => 'Come nasce un progetto',
            'blocks' => [
                ['steps' => [
                    ['accent', 'Scegli il punto di partenza', 'Un preset di settore (es. assistenza reclami voli) oppure da zero.'],
                    ['strong', 'Scegli i pacchetti', 'Le dipendenze si aggiungono da sole: chi sceglie Clienti si porta dietro i dati geografici.'],
                    ['info', 'Crea', 'Uno prepara cartella, database, utente amministratore e permessi.'],
                    ['warning', 'Apri e mostra', 'Il progetto risponde subito su nome.localhost, con i dati demo se richiesti.'],
                ]],
            ],
        ],
    ],
];
