<?php

declare(strict_types=1);

/**
 * Testi della Guida di Uno (vedi public/js/onboarding.js). Parla Uno, in
 * prima persona e al "tu": e' un assistente che lavora con chi sta al PC,
 * non un manuale. Un progetto generato riceve i suoi testi (preset o guida
 * di partenza, vedi packages/provisioning) al posto di questo file.
 */
return [
    'welcome' => 'Ciao, io sono Uno',
    // Uno non apre la Guida da solo: la home e' solo il prompt, la Guida
    // compare in pagina quando l'utente chiede "cosa puoi fare".
    'autoOpen' => false,
    'reply' => 'Ecco cosa so fare e come possiamo lavorare insieme. Da dove vuoi cominciare?',
    'suggestions' => ['nuovo progetto', 'pacchetti', 'menu'],
    // La Guida in pagina ("cosa sai fare") come una landing: titoli grandi,
    // frasi corte, una idea per sezione. {packages} = numero di pacchetti
    // nel catalogo, '{packages}' come 'chips' = i loro nomi (vedi ShowWizardTool).
    'landing' => [
        'hero' => [
            'eyebrow' => 'Il tuo assistente',
            'title' => 'Ciao, io sono Uno.',
            'lines' => ['Costruisco gestionali.', 'Su misura.', 'In pochi secondi.'],
            'text' => 'Mi dici di cosa ha bisogno un\'azienda e preparo il programma completo: database, schermate, permessi e dati di esempio per mostrarlo subito.',
            'actions' => [
                ['label' => 'Crea un progetto', 'prompt' => 'nuovo progetto', 'primary' => true],
                ['label' => 'Guarda i pacchetti', 'prompt' => 'pacchetti'],
            ],
        ],
        'figures' => [
            ['value' => '2 sec', 'label' => 'per andare online'],
            ['value' => '{packages}', 'label' => 'mattoni pronti'],
            ['value' => '0', 'label' => 'righe di codice per te'],
        ],
        'features' => [
            [
                'eyebrow' => 'Parliamo',
                'title' => 'Tu descrivi. Io costruisco.',
                'text' => 'Scrivimi o parlami dalla casella a sinistra, come faresti con un collega. Ti chiedo io i dettagli che mancano e ti porto dove serve.',
                'visual' => ['chat' => [
                    ['me', 'Mi serve un gestionale per i reclami dei voli.'],
                    ['uno', 'Fatto. Contatti, pratiche, documenti e rimborsi. E\' gia\' online.'],
                ]],
            ],
            [
                'eyebrow' => 'Mattoni',
                'title' => 'Pezzi pronti. Combinati per te.',
                'text' => 'Contatti, clienti, pratiche, documenti, rimborsi, agenti: scegli solo quello che serve. Alle dipendenze penso io, senza che tu debba ricordarle.',
                'visual' => ['chips' => '{packages}'],
            ],
            [
                'eyebrow' => 'Subito',
                'title' => 'Online in un attimo.',
                'text' => 'Preparo database, utente amministratore, permessi e indirizzo. Se vuoi carico anche dei dati demo, cosi\' il progetto e\' pronto da presentare. Se qualcosa va storto, rimetto tutto com\'era.',
                'visual' => ['url' => 'assilevi.localhost'],
            ],
        ],
        'steps' => [
            'title' => 'Quattro passi. Un minuto.',
            'items' => [
                ['Scegli', 'un preset di settore, come l\'assistenza reclami voli, o parti da zero.'],
                ['Combina', 'i pacchetti che servono: le dipendenze le aggiungo io.'],
                ['Crea', 'preparo cartella, database e permessi in un paio di secondi.'],
                ['Mostra', 'apri il link: il gestionale e\' gia\' online, con i dati demo.'],
            ],
        ],
        'cta' => [
            'title' => 'Da dove vuoi cominciare?',
            'actions' => [
                ['label' => 'Crea un progetto', 'prompt' => 'nuovo progetto', 'primary' => true],
                ['label' => 'Apri il menu', 'prompt' => 'menu'],
            ],
        ],
    ],
    'subtitle' => 'Il tuo assistente per costruire gestionali su misura.',
    'tabs' => [
        [
            'key' => 'overview',
            'label' => 'Chi sono',
            'blocks' => [
                ['text' => 'Costruisco gestionali su misura insieme a te. Tu mi dici di cosa ha bisogno un\'azienda, io preparo il programma: database, schermate, permessi, perfino dei dati di esempio per mostrarlo subito.'],
                ['text' => 'Puoi parlarmi dalla casella in alto, scrivendo o a voce: chiedimi "progetti", "menu" o "aiuto" e ti porto dove serve.'],
                ['points' => [
                    ['Progetti', 'qui trovi i gestionali che abbiamo gia\' creato e da qui ne facciamo di nuovi.'],
                    ['Pacchetti', 'sono i mattoni che uso: contatti, clienti, pratiche, documenti, rimborsi, agenti...'],
                    ['Preset', 'punti di partenza che conosco gia\' per un settore, con i dati di esempio pronti.'],
                ]],
            ],
        ],
        [
            'key' => 'journey',
            'label' => 'Come lavoriamo',
            'blocks' => [
                ['text' => 'Creare un progetto con me richiede un minuto:'],
                ['steps' => [
                    ['accent', 'Mi dici da dove partire', 'Scegli un preset del settore (per esempio assistenza reclami voli) oppure partiamo da zero.'],
                    ['strong', 'Scegli i pacchetti', 'Alle dipendenze penso io: se scegli Clienti, aggiungo da solo i dati geografici che servono.'],
                    ['info', 'Io lo costruisco', 'Preparo cartella, database, utente amministratore e permessi. Se qualcosa va storto, rimetto tutto com\'era.'],
                    ['warning', 'Lo apri e lo mostri', 'Ti do il link: il progetto e\' gia\' online, con i dati di esempio se li hai chiesti.'],
                ]],
            ],
        ],
    ],
];
