<?php

declare(strict_types=1);

/**
 * Testi della Guida di Brains at Work (vedi public/js/onboarding.js):
 * copiati in config/guide.php del progetto generato. Blocchi: ['text' => ...],
 * ['points' => [[etichetta, descrizione]]], ['steps' => [[colore, etichetta, descrizione]]].
 */
return [
    'tabs' => [
        [
            'key' => 'overview',
            'label' => 'Panoramica',
            'blocks' => [
                ['text' => 'Il gestionale di Brains at Work: clienti e servizi, commesse, piano editoriale, la giornata di ognuno, preventivi, fatture e pagamenti, in un posto solo al posto di Excel, Trello e chat.'],
                ['text' => 'Il prompt in cima alla pagina e\' un assistente: scrivi cosa ti serve ("la mia giornata", "cosa devo approvare", "foto di pizza senza persone") e ti porta li\'.'],
                ['points' => [
                    ['La mia giornata', 'cosa fare oggi, in ordine, con i tempi: arrivi e sai gia\' da dove partire.'],
                    ['Piano editoriale', 'i contenuti di ogni cliente, chi scrive, chi realizza, le approvazioni.'],
                    ['Richieste dei clienti', 'quello che arriva al telefono o su WhatsApp, a chi deve farlo, senza interrompere tutti.'],
                    ['Archivio foto e video', 'il Drive ricercabile a parole: l\'IA ha gia\' guardato ogni file.'],
                ]],
            ],
        ],
        [
            'key' => 'journey',
            'label' => 'Un contenuto',
            'blocks' => [
                ['text' => 'Ogni contenuto del piano editoriale passa per questi passi; a ognuno, chi deve fare qualcosa se lo trova nella sua giornata.'],
                ['steps' => [
                    ['neutral', 'Idea', 'Il tema, anche proposto dall\'IA dalla strategia del cliente.'],
                    ['info', 'In lavorazione', 'Chi scrive prepara il testo, poi grafica o video lo realizzano.'],
                    ['accent', 'Revisione interna', 'Un titolare lo approva (o lo rimanda indietro con una nota).'],
                    ['warning', 'Dal cliente', 'Il cliente lo vede nella sua area e approva, o chiede modifiche.'],
                    ['success', 'Approvato', 'Si programma e si pubblica.'],
                ]],
            ],
        ],
        [
            'key' => 'where',
            'label' => 'Dove trovare',
            'blocks' => [
                ['points' => [
                    ['Lavoro', 'la mia giornata, il team, attivita\', commesse, calendario, registro, ferie.'],
                    ['Contenuti', 'piano editoriale, contratti social, archivio foto e video.'],
                    ['Clienti', 'clienti, richieste, clienti a rischio.'],
                    ['Amministrazione', 'preventivi, scadenzario, fatture, spese, listino.'],
                    ['Impostazioni', 'utenti, permessi, orari, tipi di commessa, collegamenti.'],
                    ['Menu (icona a griglia)', 'tutte le funzioni, raggruppate per area.'],
                ]],
            ],
        ],
    ],
    // "Cosa manca ancora" per chi non puo' configurare (non vede la checklist).
    'missingReadOnly' => [
        ['text' => 'Alcuni collegamenti non sono ancora attivi: chiedi a un titolare di completarli.'],
        ['points' => [
            ['Google Calendar', 'gli appuntamenti restano qui e non arrivano sul calendario dell\'agenzia.'],
            ['Google Drive', 'l\'archivio foto e video e\' vuoto finche\' non si collega la cartella.'],
            ['Pagamenti e fatture', 'link di pagamento e invio allo SdI partono quando si collegano Stripe, PayPal, Fatture in Cloud o Aruba.'],
        ]],
    ],
];
