<?php

declare(strict_types=1);

/**
 * Guida di partenza per un progetto senza preset (vedi
 * presets/assilevi/guide.php per la forma completa): da riscrivere sul
 * cliente, copiata in config/guide.php dal ProjectProvisioner.
 */
return [
    'tabs' => [
        [
            'key' => 'overview',
            'label' => 'Panoramica',
            'blocks' => [
                ['text' => 'Benvenuto nel tuo gestionale. Il prompt in cima alla pagina e\' un assistente: scrivi cosa vuoi fare (es. "menu") ed esegue l\'operazione al posto tuo.'],
                ['points' => [
                    ['Menu (icona a griglia)', 'tutte le funzioni disponibili, raggruppate per area.'],
                    ['Profilo (icona persona)', 'i tuoi dati, la password e questa Guida.'],
                ]],
            ],
        ],
    ],
];
