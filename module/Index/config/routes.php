<?php

declare(strict_types=1);

use Index\Controller\ImportController;
use Index\Controller\AgentsController;
use Index\Controller\AppointmentsController;
use Index\Controller\BlogController;
use Index\Controller\CampaignsController;
use Index\Controller\CasesController;
use Index\Controller\CatalogController;
use Index\Controller\ClientRequestsController;
use Index\Controller\CompanyProfilesController;
use Index\Controller\CommunicationsController;
use Index\Controller\CustomerContactsController;
use Index\Controller\CustomerTeamMembersController;
use Index\Controller\CustomerTouchpointsController;
use Index\Controller\CustomerServicesController;
use Index\Controller\CustomersController;
use Index\Controller\InstallmentsController;
use Index\Controller\DocumentRequestsController;
use Index\Controller\ExpensesController;
use Index\Controller\LeavesController;
use Index\Controller\EditorialController;
use Index\Controller\EditorialQuotasController;
use Index\Controller\EditorialStrategiesController;
use Index\Controller\EntityReferenceController;
use Index\Controller\GeoController;
use Index\Controller\IndexController;
use Index\Controller\InterventionsController;
use Index\Controller\InvoicesController;
use Index\Controller\LeadDuplicatesController;
use Index\Controller\LeadsController;
use Index\Controller\MediaController;
use Index\Controller\MessageTemplatesController;
use Index\Controller\PaymentsController;
use Index\Controller\PermissionsController;
use Index\Controller\PortalController;
use Index\Controller\PlannerController;
use Index\Controller\WorkSchedulesController;
use Index\Controller\WorklogController;
use Index\Controller\WorkEntriesController;
use Index\Controller\ProfileController;
use Index\Controller\ProfilesController;
use Index\Controller\ProjectsController;
use Index\Controller\PromptController;
use Index\Controller\QuoteLinesController;
use Index\Controller\QuotesController;
use Index\Controller\RefundsController;
use Index\Controller\ReportController;
use Index\Controller\RestrictedAreaController;
use Index\Controller\ServicesController;
use Index\Controller\SetupController;
use Index\Controller\TasksController;
use Index\Controller\UsersController;
use Index\Controller\WorkOrderTypeStepsController;
use Index\Controller\WorkOrderTypesController;
use Index\Controller\WorkOrdersController;
use Index\Controller\WizardController;

/**
 * index/nuovo/:id per un App\Controller\AbstractEntityController: le
 * stesse 3 rotte, ripetute identiche per ogni pacchetto costruito sulla
 * base generica (vedi li'). 'nuovo' PRIMA di ':id' nell'array risultante
 * (vedi piu' sotto): altrimenti verrebbe catturato da ':id' - il router
 * prova le rotte nell'ordine di dichiarazione (App\Core\Router::match()).
 *
 * @return array<string, array{path: string, controller: string, action: string}>
 */
function entityRoutes(string $name, string $path, string $controllerClass): array
{
    return [
        $name => ['path' => $path, 'controller' => $controllerClass, 'action' => 'index'],
        "{$name}-new" => ['path' => "{$path}/nuovo", 'controller' => $controllerClass, 'action' => 'new'],
        // Router::compile() ancora il pattern con '^...$' (match sull'intero
        // path) - a differenza di 'nuovo' sopra, l'ordine qui non conta:
        // ':id/elimina' ha un segmento in piu' di ':id' da solo, i due
        // pattern non possono mai confondersi a prescindere da chi viene
        // prima.
        "{$name}-delete" => ['path' => "{$path}/:id/elimina", 'controller' => $controllerClass, 'action' => 'delete'],
        "{$name}-edit" => ['path' => "{$path}/:id", 'controller' => $controllerClass, 'action' => 'edit'],
    ];
}

return [
    'import' => [
        'path' => '/importazione',
        'controller' => ImportController::class,
        'action' => 'index',
    ],
    'import-run' => [
        'path' => '/importazione/avvia',
        'controller' => ImportController::class,
        'action' => 'run',
    ],
    'index' => [
        'path' => '/',
        'controller' => IndexController::class,
        'action' => 'index',
    ],
    'prompt' => [
        'path' => '/prompt',
        'controller' => PromptController::class,
        'action' => 'index',
    ],
    'wizard' => [
        'path' => '/wizard',
        'controller' => WizardController::class,
        'action' => 'index',
    ],
    'index-hello' => [
        'path' => '/hello[/:name]',
        'controller' => IndexController::class,
        'action' => 'hello',
    ],
    'restricted-area' => [
        'path' => '/area-riservata',
        'controller' => RestrictedAreaController::class,
        'action' => 'index',
    ],
    'users' => [
        'path' => '/utenti',
        'controller' => UsersController::class,
        'action' => 'index',
    ],
    'users-new' => ['path' => '/utenti/nuovo', 'controller' => UsersController::class, 'action' => 'new'],
    'users-invite' => ['path' => '/utenti/:id/invito', 'controller' => UsersController::class, 'action' => 'invite'],
    'users-deactivate' => ['path' => '/utenti/:id/disattiva', 'controller' => UsersController::class, 'action' => 'deactivate'],
    'profiles' => ['path' => '/profili', 'controller' => ProfilesController::class, 'action' => 'index'],
    'profiles-new' => ['path' => '/profili/nuovo', 'controller' => ProfilesController::class, 'action' => 'new'],
    'profiles-edit' => ['path' => '/profili/:id', 'controller' => ProfilesController::class, 'action' => 'edit'],
    'users-edit' => [
        'path' => '/utenti/:id',
        'controller' => UsersController::class,
        'action' => 'edit',
    ],
    'profile' => [
        'path' => '/profilo',
        'controller' => ProfileController::class,
        'action' => 'edit',
    ],
    'profile-password' => [
        'path' => '/profilo/password',
        'controller' => ProfileController::class,
        'action' => 'changePassword',
    ],
    'onboarding-dismiss' => [
        'path' => '/onboarding/dismiss',
        'controller' => ProfileController::class,
        'action' => 'dismissOnboarding',
    ],
    'permissions' => [
        'path' => '/permessi',
        'controller' => PermissionsController::class,
        'action' => 'index',
    ],
    'permissions-edit' => [
        'path' => '/permessi/:id',
        'controller' => PermissionsController::class,
        'action' => 'edit',
    ],
    'leads' => [
        'path' => '/contatti',
        'controller' => LeadsController::class,
        'action' => 'index',
    ],
    // Prima di 'leads-edit': 'nuovo' altrimenti verrebbe catturato da
    // ':id' (il router prova le rotte nell'ordine in cui sono dichiarate
    // - vedi App\Core\Router::match()).
    'leads-new' => [
        'path' => '/contatti/nuovo',
        'controller' => LeadsController::class,
        'action' => 'new',
    ],
    // Stesso motivo di 'leads-new' sopra: segmento letterale, deve
    // precedere ':id' o verrebbe interpretato come l'id di un contatto.
    'leads-duplicates' => [
        'path' => '/contatti/duplicati',
        'controller' => LeadDuplicatesController::class,
        'action' => 'index',
    ],
    'leads-edit' => [
        'path' => '/contatti/:id',
        'controller' => LeadsController::class,
        'action' => 'edit',
    ],
    'leads-duplicates-confirm' => [
        'path' => '/contatti/duplicati/:id',
        'controller' => LeadDuplicatesController::class,
        'action' => 'confirm',
    ],
    'leads-convert' => [
        'path' => '/contatti/:id/converti',
        'controller' => LeadsController::class,
        'action' => 'convert',
    ],
    'leads-lost' => [
        'path' => '/contatti/:id/perso',
        'controller' => LeadsController::class,
        'action' => 'markLost',
    ],
    'leads-send-email' => [
        'path' => '/contatti/:id/invia-email',
        'controller' => LeadsController::class,
        'action' => 'sendEmail',
    ],
    'leads-mark-contacted' => [
        'path' => '/contatti/:id/contattato',
        'controller' => LeadsController::class,
        'action' => 'markContacted',
    ],

    // Deve precedere entityRoutes('customers', ...): quella registra
    // '/clienti/:id' (customers-edit), che essendo un pattern generico
    // ([^/]+) matcherebbe anche 'esporta' come se fosse un id - Router::
    // match() e' first-match-wins per ordine di dichiarazione, non per
    // specificita' del pattern (vedi Router::compile()).
    'customers-export' => [
        'path' => '/clienti/esporta',
        'controller' => CustomersController::class,
        'action' => 'export',
    ],
    // Anche questi prima di '/clienti/:id', per lo stesso motivo.
    ...entityRoutes('customer_contacts', '/clienti/referenti', CustomerContactsController::class),
    ...entityRoutes('customer_team_members', '/clienti/squadra', CustomerTeamMembersController::class),
    ...entityRoutes('customer_touchpoints', '/clienti/contatti', CustomerTouchpointsController::class),
    'client-health' => ['path' => '/clienti/temperatura', 'controller' => CustomerTouchpointsController::class, 'action' => 'index'],
    'customers-portal-link' => ['path' => '/clienti/:id/area-cliente', 'controller' => CustomersController::class, 'action' => 'portalLink'],
    ...entityRoutes('customers', '/clienti', CustomersController::class),
    ...entityRoutes('customer_services', '/servizi-clienti', CustomerServicesController::class),
    'customer-services-renew' => ['path' => '/servizi-clienti/:id/rinnova', 'controller' => CustomerServicesController::class, 'action' => 'renew'],
    'customer-services-rebuild' => ['path' => '/servizi-clienti/:id/ricrea-scadenze', 'controller' => CustomerServicesController::class, 'action' => 'rebuild'],
    ...entityRoutes('installments', '/scadenzario', InstallmentsController::class),
    'installments-pay' => ['path' => '/scadenzario/:id/incassa', 'controller' => InstallmentsController::class, 'action' => 'pay'],
    'installments-remind' => ['path' => '/scadenzario/:id/sollecitato', 'controller' => InstallmentsController::class, 'action' => 'remind'],
    'installments-invoice' => ['path' => '/scadenzario/:id/fattura', 'controller' => InstallmentsController::class, 'action' => 'invoice'],
    'installments-link' => ['path' => '/scadenzario/:id/link-pagamento', 'controller' => InstallmentsController::class, 'action' => 'link'],
    // Pubbliche: il webhook lo chiama Stripe (firma al posto del CSRF), il ritorno e' il browser del cliente.
    'payments-stripe-webhook' => ['path' => '/pagamenti/webhook/stripe', 'controller' => PaymentsController::class, 'action' => 'stripeWebhook', 'csrf' => false],
    'payments-paypal-return' => ['path' => '/pagamenti/paypal/ritorno', 'controller' => PaymentsController::class, 'action' => 'paypalReturn'],
    // Area del cliente: link personale senza password (vedi PortalController).
    'portal' => ['path' => '/area/:token', 'controller' => PortalController::class, 'action' => 'index'],
    'portal-approve' => ['path' => '/area/:token/approva', 'controller' => PortalController::class, 'action' => 'approve'],
    'portal-approve-all' => ['path' => '/area/:token/approva-tutti', 'controller' => PortalController::class, 'action' => 'approveAll'],
    'portal-accept-quote' => ['path' => '/area/:token/accetta-preventivo', 'controller' => PortalController::class, 'action' => 'acceptQuote'],
    'portal-changes' => ['path' => '/area/:token/modifiche', 'controller' => PortalController::class, 'action' => 'changes'],
    'portal-pay' => ['path' => '/area/:token/paga', 'controller' => PortalController::class, 'action' => 'pay'],
    'portal-method' => ['path' => '/area/:token/metodo', 'controller' => PortalController::class, 'action' => 'method'],
    'portal-autopay' => ['path' => '/area/:token/automatico', 'controller' => PortalController::class, 'action' => 'autoPay'],
    'portal-autopay-off' => ['path' => '/area/:token/automatico/disattiva', 'controller' => PortalController::class, 'action' => 'autoPayOff'],
    'worklog-week' => ['path' => '/registro', 'controller' => WorklogController::class, 'action' => 'week'],
    'worklog-checkin' => ['path' => '/registro/check-in', 'controller' => WorklogController::class, 'action' => 'checkin'],
    'worklog-status' => ['path' => '/registro/stato', 'controller' => WorklogController::class, 'action' => 'status'],
    'worklog-insights' => ['path' => '/registro/andamento', 'controller' => WorklogController::class, 'action' => 'insights'],
    'worklog-apply-template' => ['path' => '/registro/modello', 'controller' => WorklogController::class, 'action' => 'applyTemplate'],
    ...entityRoutes('work_entries', '/registro/voci', WorkEntriesController::class),
    'planner-today' => ['path' => '/oggi', 'controller' => PlannerController::class, 'action' => 'today'],
    'planner-postpone' => ['path' => '/oggi/rimanda', 'controller' => PlannerController::class, 'action' => 'postpone'],
    'planner-reassign' => ['path' => '/oggi/riassegna', 'controller' => PlannerController::class, 'action' => 'reassign'],
    'planner-team' => ['path' => '/team', 'controller' => PlannerController::class, 'action' => 'team'],
    ...entityRoutes('work_schedules', '/orari', WorkSchedulesController::class),
    ...entityRoutes('leave_requests', '/ferie', LeavesController::class),
    'leaves-decide' => ['path' => '/ferie/:id/decidi', 'controller' => LeavesController::class, 'action' => 'decide'],
    ...entityRoutes('expenses', '/spese', ExpensesController::class),
    'expenses-decide' => ['path' => '/spese/:id/decidi', 'controller' => ExpensesController::class, 'action' => 'decide'],
    'expenses-reimbursed' => ['path' => '/spese/:id/rimborsata', 'controller' => ExpensesController::class, 'action' => 'reimbursed'],
    ...entityRoutes('client_requests', '/richieste', ClientRequestsController::class),
    // Righe prima di '/preventivi/:id' (first-match-wins).
    ...entityRoutes('quote_lines', '/preventivi/righe', QuoteLinesController::class),
    ...entityRoutes('quotes', '/preventivi', QuotesController::class),
    'quotes-step' => ['path' => '/preventivi/:id/passo', 'controller' => QuotesController::class, 'action' => 'step'],
    'quotes-client-link' => ['path' => '/preventivi/:id/link-cliente', 'controller' => QuotesController::class, 'action' => 'clientLink'],
    'quotes-print' => ['path' => '/preventivi/:id/stampa', 'controller' => QuotesController::class, 'action' => 'print'],
    'client-requests-take' => ['path' => '/richieste/:id/prendo', 'controller' => ClientRequestsController::class, 'action' => 'take'],
    'client-requests-complete' => ['path' => '/richieste/:id/fatta', 'controller' => ClientRequestsController::class, 'action' => 'complete'],
    'client-requests-bill' => ['path' => '/richieste/:id/in-conto', 'controller' => ClientRequestsController::class, 'action' => 'bill'],
    // Contratti e riempimento prima di '/piano-editoriale/:id' (first-match-wins).
    ...entityRoutes('editorial_quotas', '/piano-editoriale/contratti', EditorialQuotasController::class),
    ...entityRoutes('editorial_strategies', '/piano-editoriale/strategie', EditorialStrategiesController::class),
    'editorial-fill' => ['path' => '/piano-editoriale/riempi', 'controller' => EditorialController::class, 'action' => 'fill'],
    'editorial-approve-customer' => ['path' => '/piano-editoriale/approva-cliente', 'controller' => EditorialController::class, 'action' => 'approveCustomer'],
    ...entityRoutes('editorial_posts', '/piano-editoriale', EditorialController::class),
    'editorial-stage' => ['path' => '/piano-editoriale/:id/stato', 'controller' => EditorialController::class, 'action' => 'stage'],
    'editorial-caption' => ['path' => '/piano-editoriale/:id/testo', 'controller' => EditorialController::class, 'action' => 'caption'],
    'editorial-client-link' => ['path' => '/piano-editoriale/:id/link-cliente', 'controller' => EditorialController::class, 'action' => 'clientLink'],
    'editorial-story' => ['path' => '/piano-editoriale/:id/storia', 'controller' => EditorialController::class, 'action' => 'story'],
    ...entityRoutes('tasks', '/attivita', TasksController::class),
    'tasks-toggle' => ['path' => '/attivita/:id/fatta', 'controller' => TasksController::class, 'action' => 'toggle'],
    // Le fasi dei modelli prima di '/tipi-commessa/:id' (first-match-wins).
    ...entityRoutes('work_order_type_steps', '/tipi-commessa/fasi', WorkOrderTypeStepsController::class),
    ...entityRoutes('work_order_types', '/tipi-commessa', WorkOrderTypesController::class),
    ...entityRoutes('work_orders', '/commesse', WorkOrdersController::class),
    'work-orders-wait' => ['path' => '/commesse/:id/attesa', 'controller' => WorkOrdersController::class, 'action' => 'wait'],
    'work-orders-resume' => ['path' => '/commesse/:id/riprendi', 'controller' => WorkOrdersController::class, 'action' => 'resume'],
    'work-orders-pause' => ['path' => '/commesse/:id/pausa', 'controller' => WorkOrdersController::class, 'action' => 'pause'],
    'work-orders-deliver' => ['path' => '/commesse/:id/consegnata', 'controller' => WorkOrdersController::class, 'action' => 'deliver'],
    'work-orders-reminded' => ['path' => '/commesse/:id/sollecitato', 'controller' => WorkOrdersController::class, 'action' => 'reminded'],
    'appointments-sync' => ['path' => '/appuntamenti/sincronizza', 'controller' => AppointmentsController::class, 'action' => 'sync'],
    ...entityRoutes('appointments', '/appuntamenti', AppointmentsController::class),
    'appointments-stage' => ['path' => '/appuntamenti/:id/stato', 'controller' => AppointmentsController::class, 'action' => 'stage'],
    // Azioni dell'archivio prima di '/materiali/:id' (first-match-wins).
    'media-sync' => ['path' => '/materiali/sincronizza', 'controller' => MediaController::class, 'action' => 'sync'],
    'media-describe' => ['path' => '/materiali/descrivi', 'controller' => MediaController::class, 'action' => 'describe'],
    ...entityRoutes('media_assets', '/materiali', MediaController::class),
    'media-thumb' => ['path' => '/materiali/:id/anteprima', 'controller' => MediaController::class, 'action' => 'thumb'],
    'media-file' => ['path' => '/materiali/:id/file', 'controller' => MediaController::class, 'action' => 'file'],
    'media-analyze' => ['path' => '/materiali/:id/analizza', 'controller' => MediaController::class, 'action' => 'analyze'],
    ...entityRoutes('catalog', '/catalogo', CatalogController::class),
    ...entityRoutes('services', '/servizi', ServicesController::class),
    ...entityRoutes('interventions', '/interventi', InterventionsController::class),
    ...entityRoutes('blog', '/blog', BlogController::class),
    ...entityRoutes('campaigns', '/campagne', CampaignsController::class),
    // Dati azienda prima di '/fatture/:id' (first-match-wins).
    ...entityRoutes('company_profiles', '/fatture/dati-azienda', CompanyProfilesController::class),
    ...entityRoutes('invoices', '/fatture', InvoicesController::class),
    'invoices-issue' => ['path' => '/fatture/:id/emetti', 'controller' => InvoicesController::class, 'action' => 'issue'],
    'invoices-send' => ['path' => '/fatture/:id/invia', 'controller' => InvoicesController::class, 'action' => 'send'],
    'invoices-paid' => ['path' => '/fatture/:id/pagata', 'controller' => InvoicesController::class, 'action' => 'paid'],
    'invoices-xml' => ['path' => '/fatture/:id/xml', 'controller' => InvoicesController::class, 'action' => 'xml'],
    'invoices-convert' => ['path' => '/fatture/:id/in-fattura', 'controller' => InvoicesController::class, 'action' => 'convert'],
    ...entityRoutes('cases', '/pratiche', CasesController::class),
    ...entityRoutes('document_requests', '/documenti-richiesti', DocumentRequestsController::class),
    'document-requests-remind' => [
        'path' => '/documenti-richiesti/:id/sollecita',
        'controller' => DocumentRequestsController::class,
        'action' => 'remind',
    ],
    ...entityRoutes('communications', '/comunicazioni', CommunicationsController::class),
    ...entityRoutes('refunds', '/rimborsi', RefundsController::class),
    ...entityRoutes('message_templates', '/modelli-messaggio', MessageTemplatesController::class),

    'setup' => [
        'path' => '/configurazione',
        'controller' => SetupController::class,
        'action' => 'index',
    ],
    'setup-status' => [
        'path' => '/configurazione/stato',
        'controller' => SetupController::class,
        'action' => 'status',
    ],
    // Prima di '/configurazione/connettori/:code': ritorno da Google dopo l'autorizzazione.
    'setup-google-callback' => [
        'path' => '/configurazione/google/callback',
        'controller' => SetupController::class,
        'action' => 'googleCallback',
    ],
    'setup-connector-connect' => [
        'path' => '/configurazione/connettori/:code',
        'controller' => SetupController::class,
        'action' => 'connect',
    ],
    'setup-connector-disconnect' => [
        'path' => '/configurazione/connettori/:code/scollega',
        'controller' => SetupController::class,
        'action' => 'disconnect',
    ],

    // Percorsi letterali PRIMA di '/agenti/:id' (first-match-wins, vedi Router::match()).
    'agents' => ['path' => '/agenti', 'controller' => AgentsController::class, 'action' => 'index'],
    'agents-feed' => ['path' => '/agenti/messaggi', 'controller' => AgentsController::class, 'action' => 'feed'],
    'agents-restart' => ['path' => '/agenti/riavvia', 'controller' => AgentsController::class, 'action' => 'restart'],
    'agents-message-approve' => ['path' => '/agenti/messaggi/:id/approva', 'controller' => AgentsController::class, 'action' => 'approve'],
    'agents-message-dismiss' => ['path' => '/agenti/messaggi/:id/ignora', 'controller' => AgentsController::class, 'action' => 'dismiss'],
    'agents-run' => ['path' => '/agenti/:id/esegui', 'controller' => AgentsController::class, 'action' => 'run'],
    'agents-save' => ['path' => '/agenti/:id', 'controller' => AgentsController::class, 'action' => 'save'],

    // Solo dove c'e' il generatore di progetti (Uno): un progetto generato
    // non ne riceve il codice (vedi ProjectProvisioner::CONFIGURATOR_FILES).
    ...(is_file(ROOT_PATH . '/packages/provisioning/package.php') ? [
        'projects' => ['path' => '/progetti', 'controller' => ProjectsController::class, 'action' => 'index'],
        'projects-create' => ['path' => '/progetti/crea', 'controller' => ProjectsController::class, 'action' => 'create'],
    ] : []),

    'report' => [
        'path' => '/report',
        'controller' => ReportController::class,
        'action' => 'index',
    ],

    'geo-search-municipalities' => [
        'path' => '/geo/comuni/cerca',
        'controller' => GeoController::class,
        'action' => 'searchMunicipalities',
    ],

    'entity-ref-search' => [
        'path' => '/riferimenti/:source/cerca',
        'controller' => EntityReferenceController::class,
        'action' => 'search',
    ],
];
