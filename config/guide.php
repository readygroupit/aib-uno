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
