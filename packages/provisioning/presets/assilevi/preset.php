<?php

declare(strict_types=1);

/**
 * Assistenza reclami voli: il gestionale descritto nel documento di
 * progetto Assilevi (lead da Jotform, qualificazione, documenti, pratiche,
 * conciliazione, rimborsi) - vedi demo.sql per i dati di esempio.
 */
return [
    'label' => 'Assistenza reclami voli',
    'description' => 'Contatti, pratiche, documenti richiesti, comunicazioni, rimborsi, agenti e collegamenti esterni (Jotform, Google Sheets, WhatsApp).',
    'appName' => 'Assilevi',
    'packages' => [
        'leads',
        'customers',
        'cases',
        'document_requests',
        'communications',
        'refunds',
        'tasks',
        'message_templates',
        'connectors',
        'agents',
    ],
    'demoReferenceDate' => '2026-10-02 10:34:23',
];
