<?php

declare(strict_types=1);

use Index\Controller\AgentsController;
use Index\Controller\AppointmentsController;
use Index\Controller\BlogController;
use Index\Controller\CampaignsController;
use Index\Controller\CasesController;
use Index\Controller\CatalogController;
use Index\Controller\CommunicationsController;
use Index\Controller\CustomersController;
use Index\Controller\DocumentRequestsController;
use Index\Controller\EntityReferenceController;
use Index\Controller\GeoController;
use Index\Controller\IndexController;
use Index\Controller\InterventionsController;
use Index\Controller\InvoicesController;
use Index\Controller\LeadDuplicatesController;
use Index\Controller\LeadsController;
use Index\Controller\MessageTemplatesController;
use Index\Controller\PermissionsController;
use Index\Controller\ProfileController;
use Index\Controller\ProjectsController;
use Index\Controller\PromptController;
use Index\Controller\RefundsController;
use Index\Controller\ReportController;
use Index\Controller\RestrictedAreaController;
use Index\Controller\ServicesController;
use Index\Controller\SetupController;
use Index\Controller\TasksController;
use Index\Controller\UsersController;
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
    ...entityRoutes('customers', '/clienti', CustomersController::class),
    ...entityRoutes('tasks', '/attivita', TasksController::class),
    ...entityRoutes('appointments', '/appuntamenti', AppointmentsController::class),
    ...entityRoutes('catalog', '/catalogo', CatalogController::class),
    ...entityRoutes('services', '/servizi', ServicesController::class),
    ...entityRoutes('interventions', '/interventi', InterventionsController::class),
    ...entityRoutes('blog', '/blog', BlogController::class),
    ...entityRoutes('campaigns', '/campagne', CampaignsController::class),
    ...entityRoutes('invoices', '/fatture', InvoicesController::class),
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
