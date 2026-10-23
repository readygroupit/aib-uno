<?php

declare(strict_types=1);

/**
 * Agenzia di comunicazione (Brains at Work): clienti con intermediari e
 * fornitori, listino servizi, servizi venduti con rinnovi e piani di
 * pagamento, scadenzario incassi, commesse con fasi a modello, attivita',
 * calendario con Google Calendar, organizer, registro attivita', piano
 * editoriale con approvazione del cliente, fatture elettroniche, pagamenti
 * online (Stripe, PayPal), temperatura dei clienti e area cliente,
 * richieste dei clienti, preventivi, ferie e permessi, spese, archivio foto
 * e video descritto dall'IA.
 * Dati demo: generate-demo.php -> demo.sql.
 */
return [
    'label' => 'Agenzia di comunicazione',
    'description' => 'Clienti, intermediari e fornitori, listino servizi, servizi venduti con rinnovi e piani di pagamento, scadenzario incassi, commesse con fasi a modello, attivita\', calendario collegato a Google Calendar, organizer della giornata e del team, registro attivita\' con i tempi medi, piano editoriale social con approvazione del cliente, fatture elettroniche, pagamenti online e addebiti automatici, temperatura dei clienti, area cliente senza password, richieste dei clienti, preventivi con approvazione e accettazione online, ferie e permessi, spese, archivio foto e video ricercabile a parole.',
    'appName' => 'Brains at Work',
    'packages' => [
        'customers',
        'services',
        'customer_services',
        'installments',
        'tasks',
        'work_orders',
        'appointments',
        'connectors',
        'scheduler',
        'planner',
        'worklog',
        'editorial',
        'invoices',
        'client_health',
        'client_requests',
        'quotes',
        'leaves',
        'expenses',
        'media_library',
    ],
    'demoReferenceDate' => '2026-10-23 12:00:00',
];
