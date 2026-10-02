<?php

declare(strict_types=1);

/**
 * Testi della Guida (vedi public/js/onboarding.js): copiati in
 * config/guide.php del progetto generato. Blocchi: ['text' => ...],
 * ['points' => [[etichetta, descrizione]]], ['steps' => [[colore, etichetta, descrizione]]].
 */
return [
    'tabs' => [
        [
            'key' => 'overview',
            'label' => 'Panoramica',
            'blocks' => [
                ['text' => 'Assilevi segue i reclami dei passeggeri aerei - ritardi, cancellazioni, negato imbarco, bagagli - dal primo contatto fino al rimborso.'],
                ['text' => 'Il prompt in cima alla pagina e\' un assistente vero: scrivi cosa vuoi fare (es. "elenco clienti") ed esegue l\'operazione al posto tuo, senza dover cercare la voce di menu giusta.'],
                ['points' => [
                    ['Contatti', 'un lead grezzo, prima ancora di diventare cliente.'],
                    ['Clienti', 'la situazione della pratica di ognuno, in un colpo d\'occhio.'],
                    ['Pratiche', 'il fascicolo completo: documenti, comunicazioni, rimborso.'],
                    ['Agenti', 'colleghi digitali che segnalano cosa fare e preparano i messaggi, con la tua approvazione.'],
                ]],
            ],
        ],
        [
            'key' => 'journey',
            'label' => 'Percorso pratica',
            'blocks' => [
                // Stessi 5 stati/colori di ListCustomersTool::STAGE_META.
                ['text' => 'Ogni pratica passa (nell\'ordine) per questi 5 stati - sono gli stessi che vedi come etichette colorate in Clienti e Pratiche.'],
                ['steps' => [
                    ['accent', 'Raccolta documenti', 'Si chiedono al cliente i documenti che servono: carta d\'imbarco, documento d\'identita\', mandato.'],
                    ['strong', 'Reclamo inviato', 'La richiesta e\' partita verso il vettore aereo. Si attende una risposta.'],
                    ['info', 'In conciliazione', 'Se il vettore non risponde o rifiuta, la pratica passa a ConciliaWeb per una decisione terza.'],
                    ['warning', 'Rimborsato', 'Il cliente ha ricevuto quanto dovuto. La pratica e\' chiusa con esito positivo.'],
                    ['danger', 'Respinta', 'La richiesta non ha avuto esito positivo, in nessuna delle fasi precedenti.'],
                ]],
            ],
        ],
        [
            'key' => 'where',
            'label' => 'Dove trovare',
            'blocks' => [
                ['points' => [
                    ['Clienti', 'chi ha documenti mancanti, chi e\' in conciliazione, importi in gioco.'],
                    ['Pratiche / Contatti', 'gestione dettagliata di lead e pratiche legali.'],
                    ['Documenti richiesti', 'cosa manca ancora per completare un fascicolo.'],
                    ['Comunicazioni', 'il registro di cosa e\' stato scritto/inviato a un cliente.'],
                    ['Rimborsi', 'importi richiesti, accettati, pagati.'],
                    ['Agenti', 'chi sono, cosa fanno e quanta autonomia hanno.'],
                    ['Menu (icona a griglia)', 'tutte le funzioni disponibili, raggruppate per area.'],
                ]],
            ],
        ],
    ],
    // "Cosa manca ancora" per chi non puo' configurare (non vede la checklist).
    'missingReadOnly' => [
        ['text' => 'Alcuni collegamenti esterni non sono ancora attivi. Chiedi a un amministratore di completarli.'],
        ['points' => [
            ['Jotform', 'i moduli di raccolta lead non sono collegati - i contatti vanno inseriti a mano.'],
            ['Google Sheets', 'se usato come archivio esistente, non c\'e\' ancora un collegamento automatico.'],
            ['ConciliaWeb', 'invio e monitoraggio delle pratiche in conciliazione non sono automatizzati.'],
            ['WhatsApp', 'le comunicazioni restano registrate nel sistema, ma non partono davvero.'],
        ]],
    ],
];
