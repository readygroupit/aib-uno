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
use Index\Controller\MessagesController;
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
// Protetta: le rotte si leggono anche per la guida delle pagine (App\Help\PageGuideService).
if (!function_exists('entityRoutes')) {
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
    'profile-avatar' => ['path' => '/profilo/foto', 'controller' => ProfileController::class, 'action' => 'avatar'],
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
    // Un'idea della roadmap diventa un progetto (prima delle rotte del diario).
    'customer-note-to-project' => ['path' => '/clienti/diario/:id/progetto', 'controller' => WorkOrdersController::class, 'action' => 'fromIdea'],
    ...entityRoutes('customer_notes', '/clienti/diario', \Index\Controller\CustomerNotesController::class),
    ...entityRoutes('customer_profiles', '/clienti/profilo', \Index\Controller\CustomerProfilesController::class),
    ...entityRoutes('customer_contacts', '/clienti/referenti', CustomerContactsController::class),
    ...entityRoutes('customer_team_members', '/clienti/squadra', CustomerTeamMembersController::class),
    ...entityRoutes('customer_touchpoints', '/clienti/contatti', CustomerTouchpointsController::class),
    'client-health' => ['path' => '/clienti/temperatura', 'controller' => CustomerTouchpointsController::class, 'action' => 'index'],
    'customers-missing' => ['path' => '/clienti/:id/dati-mancanti', 'controller' => CustomersController::class, 'action' => 'missing'],
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
    'portal-test-pay' => ['path' => '/area/:token/pagamento-prova/:id', 'controller' => PortalController::class, 'action' => 'testPay'],
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
    'editorial-file' => ['path' => '/piano-editoriale/:id/file', 'controller' => EditorialController::class, 'action' => 'file'],
    'editorial-publish' => ['path' => '/piano-editoriale/:id/pubblica', 'controller' => EditorialController::class, 'action' => 'publish'],
    'editorial-schedule' => ['path' => '/piano-editoriale/:id/programma', 'controller' => EditorialController::class, 'action' => 'schedule'],
    ...entityRoutes('tasks', '/attivita', TasksController::class),
    'tasks-toggle' => ['path' => '/attivita/:id/fatta', 'controller' => TasksController::class, 'action' => 'toggle'],
    'tasks-assign' => ['path' => '/attivita/:id/assegna', 'controller' => TasksController::class, 'action' => 'assign'],
    'tasks-outcome' => ['path' => '/attivita/:id/esito', 'controller' => TasksController::class, 'action' => 'outcome'],
    'tasks-item-note' => ['path' => '/attivita/:id/nota-voce', 'controller' => TasksController::class, 'action' => 'itemNote'],
    'tasks-upload' => ['path' => '/attivita/:id/file', 'controller' => TasksController::class, 'action' => 'upload'],
    'tasks-client-approved' => ['path' => '/attivita/:id/approvato-dal-cliente', 'controller' => TasksController::class, 'action' => 'clientApproved'],
    'tasks-check' => ['path' => '/attivita/:id/punto', 'controller' => TasksController::class, 'action' => 'check'],
    'tasks-approve' => ['path' => '/attivita/:id/approva', 'controller' => TasksController::class, 'action' => 'approve'],
    'tasks-reject' => ['path' => '/attivita/:id/rimanda', 'controller' => TasksController::class, 'action' => 'reject'],
    'tasks-skip' => ['path' => '/attivita/:id/salta', 'controller' => TasksController::class, 'action' => 'skip'],
    'tasks-briefing' => ['path' => '/attivita/:id/briefing', 'controller' => TasksController::class, 'action' => 'briefing'],
    'action-plan-execute' => ['path' => '/prompt/piano/:id/esegui', 'controller' => \Index\Controller\ActionPlansController::class, 'action' => 'execute'],
    'action-plan-cancel' => ['path' => '/prompt/piano/:id/annulla', 'controller' => \Index\Controller\ActionPlansController::class, 'action' => 'cancel'],
    'documents' => ['path' => '/documenti', 'controller' => \Index\Controller\DocumentsController::class, 'action' => 'index'],
    'documents-search' => ['path' => '/documenti/cerca', 'controller' => \Index\Controller\DocumentsController::class, 'action' => 'search'],
    'documents-sync' => ['path' => '/documenti/aggiorna', 'controller' => \Index\Controller\DocumentsController::class, 'action' => 'sync'],
    'documents-folder' => ['path' => '/documenti/cartella/:id', 'controller' => \Index\Controller\DocumentsController::class, 'action' => 'folder'],
    'documents-folder-new' => ['path' => '/documenti/cartella/:id/nuova', 'controller' => \Index\Controller\DocumentsController::class, 'action' => 'newFolder'],
    'documents-folder-upload' => ['path' => '/documenti/cartella/:id/carica', 'controller' => \Index\Controller\DocumentsController::class, 'action' => 'upload'],
    'documents-folder-rename' => ['path' => '/documenti/cartella/:id/rinomina', 'controller' => \Index\Controller\DocumentsController::class, 'action' => 'rename'],
    'documents-folder-trash' => ['path' => '/documenti/cartella/:id/elimina', 'controller' => \Index\Controller\DocumentsController::class, 'action' => 'trashFolder'],
    'documents-file-download' => ['path' => '/documenti/file/:id/scarica', 'controller' => \Index\Controller\DocumentsController::class, 'action' => 'download'],
    'documents-file-trash' => ['path' => '/documenti/file/:id/cestino', 'controller' => \Index\Controller\DocumentsController::class, 'action' => 'trashFile'],
    'documents-project' => ['path' => '/documenti/progetto/:id/cartelle', 'controller' => \Index\Controller\DocumentsController::class, 'action' => 'projectFolders'],
    'documents-customer' => ['path' => '/documenti/cliente/:id/cartella', 'controller' => \Index\Controller\DocumentsController::class, 'action' => 'customerFolder'],
    ...entityRoutes('documents-file', '/documenti/file', \Index\Controller\DocumentsController::class),
    'assistant-postpone' => ['path' => '/assistente/rimanda', 'controller' => \Index\Controller\AssistantController::class, 'action' => 'postpone'],
    ...entityRoutes('seasonal_events', '/assistente/stagioni', \Index\Controller\SeasonalEventsController::class),
    'assistant' => ['path' => '/assistente', 'controller' => \Index\Controller\AssistantController::class, 'action' => 'index'],
    'assistant-rules' => ['path' => '/assistente/regole', 'controller' => \Index\Controller\AssistantController::class, 'action' => 'rules'],
    'assistant-prepare' => ['path' => '/assistente/prepara', 'controller' => \Index\Controller\AssistantController::class, 'action' => 'prepare'],
    'assistant-dismiss' => ['path' => '/assistente/azioni/:id/scarta', 'controller' => \Index\Controller\AssistantController::class, 'action' => 'dismiss'],
    'assistant-action' => ['path' => '/assistente/azioni/:id', 'controller' => \Index\Controller\AssistantController::class, 'action' => 'edit'],
    'notifications' => ['path' => '/avvisi', 'controller' => \Index\Controller\NotificationsController::class, 'action' => 'index'],
    'page-guide' => ['path' => '/guida-pagina', 'controller' => \Index\Controller\HelpController::class, 'action' => 'page'],
    'notifications-count' => ['path' => '/avvisi/conteggio', 'controller' => \Index\Controller\NotificationsController::class, 'action' => 'count'],
    'notifications-read-all' => ['path' => '/avvisi/tutti-letti', 'controller' => \Index\Controller\NotificationsController::class, 'action' => 'readAll'],
    'notifications-preferences' => ['path' => '/avvisi/preferenze', 'controller' => \Index\Controller\NotificationsController::class, 'action' => 'preferences'],
    'notifications-open' => ['path' => '/avvisi/:id', 'controller' => \Index\Controller\NotificationsController::class, 'action' => 'open'],
    // Le fasi dei modelli prima di '/tipi-commessa/:id' (first-match-wins).
    ...entityRoutes('work_order_type_steps', '/tipi-commessa/fasi', WorkOrderTypeStepsController::class),
    ...entityRoutes('work_order_type_roles', '/tipi-commessa/ruoli', \Index\Controller\WorkOrderTypeRolesController::class),
    ...entityRoutes('work_order_type_milestones', '/tipi-commessa/date', \Index\Controller\WorkOrderTypeMilestonesController::class),
    ...entityRoutes('work_order_type_deliverables', '/tipi-commessa/consegne', \Index\Controller\WorkOrderTypeDeliverablesController::class),
    ...entityRoutes('work_order_types', '/tipi-commessa', WorkOrderTypesController::class),
    // Le parti del progetto prima di '/commesse/:id' (first-match-wins).
    // La fase "Comporre la squadra": prima delle rotte generiche di squadra e commesse.
    'work-order-team-assign' => ['path' => '/commesse/:id/squadra/assegna', 'controller' => \Index\Controller\WorkOrderMembersController::class, 'action' => 'assign'],
    'work-order-team-remove' => ['path' => '/commesse/squadra/:id/togli', 'controller' => \Index\Controller\WorkOrderMembersController::class, 'action' => 'remove'],
    ...entityRoutes('work_order_members', '/commesse/squadra', \Index\Controller\WorkOrderMembersController::class),
    ...entityRoutes('work_order_milestones', '/commesse/date', \Index\Controller\WorkOrderMilestonesController::class),
    'work-order-deliverables-toggle' => ['path' => '/commesse/consegne/:id/pronto', 'controller' => \Index\Controller\WorkOrderDeliverablesController::class, 'action' => 'toggle'],
    ...entityRoutes('work_order_deliverables', '/commesse/consegne', \Index\Controller\WorkOrderDeliverablesController::class),
    ...entityRoutes('work_order_change_requests', '/commesse/modifiche', \Index\Controller\WorkOrderChangeRequestsController::class),
    ...entityRoutes('work_orders', '/commesse', WorkOrdersController::class),
    'work-orders-quote' => ['path' => '/commesse/:id/preventivo', 'controller' => WorkOrdersController::class, 'action' => 'quote'],
    'work-orders-lost' => ['path' => '/commesse/:id/non-acquisita', 'controller' => WorkOrdersController::class, 'action' => 'lost'],
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
    'services-process' => ['path' => '/servizi/:id/processo', 'controller' => ServicesController::class, 'action' => 'process'],
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
    'messages-whatsapp' => ['path' => '/messaggi/whatsapp', 'controller' => MessagesController::class, 'action' => 'whatsapp'],
    'setup-meta-callback' => ['path' => '/configurazione/meta/callback', 'controller' => SetupController::class, 'action' => 'metaCallback'],
    'setup-social' => ['path' => '/configurazione/social', 'controller' => SetupController::class, 'action' => 'social'],
    'setup-test-mode' => ['path' => '/configurazione/prova', 'controller' => SetupController::class, 'action' => 'testMode'],
    'setup-logo' => [
        'path' => '/configurazione/logo',
        'controller' => SetupController::class,
        'action' => 'logo',
    ],
    'setup-pcloud-callback' => [
        'path' => '/configurazione/pcloud/callback',
        'controller' => SetupController::class,
        'action' => 'pcloudCallback',
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

    'geo-search-postal-codes' => [
        'path' => '/geo/cap/cerca',
        'controller' => GeoController::class,
        'action' => 'searchPostalCodes',
    ],

    'entity-ref-search' => [
        'path' => '/riferimenti/:source/cerca',
        'controller' => EntityReferenceController::class,
        'action' => 'search',
    ],
];
