<?php

declare(strict_types=1);

/**
 * Dati demo di un'agenzia di comunicazione (clienti, intermediari,
 * fornitori, listino, servizi venduti, scadenzario) nel database di
 * sviluppo di Uno, poi esportati in demo.sql per il preset. Tutti i nomi
 * sono inventati.
 *
 * Gli incassi nascono dal piano di pagamento vero (PaymentPlanService),
 * come succede usando il gestionale; quelli gia' scaduti risultano
 * pagati, tranne pochi lasciati in ritardo apposta per la demo.
 *
 * Uso sul PC: APPLICATION_ENV=localhost php8.4 packages/provisioning/presets/brainsatwork/generate-demo.php
 * CANCELLA clienti, servizi e scadenzario del database configurato: solo sviluppo.
 */

define('ROOT_PATH', dirname(__DIR__, 4));
define('CONFIG_PATH', ROOT_PATH . '/config');

require ROOT_PATH . '/autoload.php';

use App\Billing\PaymentPlanService;
use App\Editorial\EditorialService;
use App\Invoicing\InvoiceService;
use App\Repository\AppointmentRepository;
use App\Repository\CompanyProfileRepository;
use App\Repository\ClientRequestRepository;
use App\Repository\CustomerTeamMemberRepository;
use App\Repository\CustomerTouchpointRepository;
use App\Repository\EditorialStrategyRepository;
use App\Repository\ExpenseRepository;
use App\Repository\LeaveRequestRepository;
use App\Repository\QuoteLineRepository;
use App\Repository\QuoteRepository;
use App\Repository\EditorialPostRepository;
use App\Repository\EditorialQuotaRepository;
use App\Repository\InvoiceRepository;
use App\Repository\MunicipalityRepository;
use App\Repository\CustomerContactRepository;
use App\Repository\CustomerRepository;
use App\Repository\CustomerServiceRepository;
use App\Repository\DemoDataRepository;
use App\Repository\InstallmentRepository;
use App\Repository\ServiceRepository;
use App\Repository\TaskRepository;
use App\Repository\WorkOrderRepository;
use App\Repository\WorkOrderTypeRepository;
use App\Repository\WorkOrderTypeStepRepository;
use App\Repository\WorkScheduleRepository;
use App\Repository\WorkEntryRepository;
use App\Repository\WorkCheckinRepository;
use App\Work\WorkOrderService;

/** @var \App\Core\Container $container */
$container = require CONFIG_PATH . '/bootstrap.php';

if (getenv('APPLICATION_ENV') !== 'localhost') {
    fwrite(STDERR, "Solo in sviluppo (APPLICATION_ENV=localhost).\n");
    exit(1);
}

$today = date('Y-m-d');
$day = static fn (int $offset): string => date('Y-m-d', strtotime("{$today} {$offset} days"));

$demo = $container->get(DemoDataRepository::class);
$tables = ['quote_lines', 'quotes', 'expenses', 'leave_requests', 'client_requests', 'editorial_strategies', 'customer_team_members', 'customer_touchpoints', 'editorial_posts', 'editorial_quotas', 'invoices', 'company_profiles', 'work_checkins', 'work_entries', 'work_schedules', 'appointments', 'tasks', 'work_orders', 'work_order_type_steps', 'work_order_types', 'installments', 'customer_services', 'customer_contacts', 'services', 'customers'];
$demo->clear($tables);

$customers = $container->get(CustomerRepository::class);
$contacts = $container->get(CustomerContactRepository::class);
$services = $container->get(ServiceRepository::class);
$sold = $container->get(CustomerServiceRepository::class);
$installments = $container->get(InstallmentRepository::class);
$plans = $container->get(PaymentPlanService::class);

// --- anagrafiche ---------------------------------------------------------
$c = [];
$add = static function (string $key, array $data) use (&$c, $customers): void {
    $c[$key] = $customers->insert($data + ['relationship' => 'cliente', 'importance' => 'abituale']);
};

$add('ferri', ['company_name' => 'Marco Ferri Consulenze', 'first_name' => 'Marco', 'last_name' => 'Ferri', 'email' => 'marco@ferriconsulenze.example', 'mobile_phone' => '3471002003', 'relationship' => 'intermediario', 'importance' => 'chiave', 'sector' => 'Consulenza aziendale', 'customer_since' => $day(-700), 'referral_percent' => '10.00', 'referral_terms' => '10% sul primo anno di ogni cliente portato', 'account_user_id' => 2]);
$add('webfactory', ['company_name' => 'WebFactory Srl', 'email' => 'progetti@webfactory.example', 'phone' => '0521770011', 'relationship' => 'fornitore', 'importance' => 'chiave', 'sector' => 'Sviluppo siti ed e-commerce', 'customer_since' => $day(-1100)]);
$add('drone', ['company_name' => 'Drone Pro Riprese', 'first_name' => 'Paolo', 'last_name' => 'Neri', 'email' => 'paolo@dronepro.example', 'mobile_phone' => '3339988776', 'relationship' => 'collaboratore', 'importance' => 'occasionale', 'sector' => 'Riprese aeree e video']);

$add('gino', ['company_name' => 'Trattoria Da Gino Srl', 'email' => 'info@dagino.example', 'phone' => '0521334455', 'website' => 'www.dagino.example', 'sector' => 'Ristorazione', 'customer_since' => $day(-380), 'account_user_id' => 2, 'vat_number' => '02345670349', 'payment_terms_days' => 30]);
$add('bellavista', ['company_name' => 'Ottica Bellavista', 'email' => 'amministrazione@otticabellavista.example', 'phone' => '0521998812', 'website' => 'www.otticabellavista.example', 'importance' => 'chiave', 'sector' => 'Ottica e occhiali', 'customer_since' => $day(-1060), 'account_user_id' => 3]);
$add('polare', ['company_name' => 'Gelateria Polare', 'email' => 'ciao@gelateriapolare.example', 'mobile_phone' => '3405566778', 'importance' => 'occasionale', 'sector' => 'Food', 'customer_since' => $day(-95), 'account_user_id' => 3]);
$add('moretti', ['company_name' => 'Studio Dentistico Moretti', 'email' => 'segreteria@studiomoretti.example', 'phone' => '0521443322', 'importance' => 'chiave', 'sector' => 'Salute', 'customer_since' => $day(-315), 'account_user_id' => 2]);
$add('aurora', ['company_name' => 'Aurora Boutique', 'email' => 'shop@auroraboutique.example', 'phone' => '0521667788', 'website' => 'www.auroraboutique.example', 'importance' => 'chiave', 'sector' => 'Moda', 'customer_since' => $day(-570), 'account_user_id' => 3]);
$add('colleverde', ['company_name' => 'Cantina Colle Verde', 'email' => 'vendite@colleverde.example', 'phone' => '0524112233', 'sector' => 'Vino', 'customer_since' => $day(-175), 'account_user_id' => 2]);
$add('fitzone', ['company_name' => 'Palestra FitZone', 'email' => 'direzione@fitzone.example', 'mobile_phone' => '3289090901', 'sector' => 'Sport e fitness', 'customer_since' => $day(-283), 'account_user_id' => 3]);
$add('poggio', ['company_name' => 'Agriturismo Il Poggio', 'email' => 'info@ilpoggio.example', 'mobile_phone' => '3312345098', 'importance' => 'occasionale', 'sector' => 'Turismo', 'customer_since' => $day(-20), 'account_user_id' => 2, 'referrer_customer_id' => $c['ferri']]);
$add('miramare', ['company_name' => 'Hotel Miramare', 'email' => 'booking@hotelmiramare.example', 'phone' => '0544123123', 'sector' => 'Turismo', 'customer_since' => $day(-142), 'account_user_id' => 3, 'referrer_customer_id' => $c['ferri']]);

foreach ([
    ['gino', 'Gino Bertoli', 'Titolare', '3351212121', true],
    ['bellavista', 'Chiara Bellavista', 'Titolare', '3387766554', true],
    ['bellavista', 'Fabio Rinaldi', 'Responsabile negozio online', '3381112233', false],
    ['polare', 'Simone Galli', 'Titolare', '3405566778', true],
    ['moretti', 'Dott.ssa Elena Moretti', 'Titolare', '3356677889', true],
    ['moretti', 'Giada Fontana', 'Segreteria', '0521443322', false],
    ['aurora', 'Aurora Conti', 'Titolare', '3471239876', true],
    ['aurora', 'Matteo Riva', 'Marketing', '3471230000', false],
    ['colleverde', 'Lorenzo Basile', 'Responsabile commerciale', '3209988001', true],
    ['fitzone', 'Davide Serra', 'Direttore', '3289090901', true],
    ['poggio', 'Anna Marchi', 'Titolare', '3312345098', true],
    ['miramare', 'Roberto Gentile', 'Direttore', '3356543210', true],
] as [$key, $name, $role, $phone, $decides]) {
    $slug = strtolower(explode(' ', str_replace('Dott.ssa ', '', $name))[0]);
    $contacts->insert(['customer_id' => $c[$key], 'full_name' => $name, 'role' => $role, 'phone' => $phone, 'email' => $slug . '@' . preg_replace('/^[^@]+@/', '', (string) $customers->find($c[$key])->email), 'is_decision_maker' => $decides ? 1 : 0]);
}

// --- listino --------------------------------------------------------------
$s = [];
foreach ([
    'sito' => ['Sito vetrina - realizzazione', 'Siti web', 'una tantum', null, '1800.00', '700.00', 'webfactory', 2400],
    'sito_canone' => ['Sito vetrina - canone annuale', 'Siti web', 'ricorrente', 'annuale', '390.00', '120.00', 'webfactory', null],
    'ecommerce' => ['E-commerce - canone annuale', 'Siti web', 'ricorrente', 'annuale', '1200.00', '450.00', 'webfactory', null],
    'hosting' => ['Hosting e manutenzione', 'Siti web', 'ricorrente', 'annuale', '240.00', '60.00', 'webfactory', null],
    'social' => ['Gestione social - 3 post a settimana', 'Social', 'ricorrente', 'mensile', '650.00', null, null, 960],
    'social_adv' => ['Gestione social + campagne ADV', 'Social', 'ricorrente', 'mensile', '950.00', null, null, 1200],
    'shooting' => ['Shooting fotografico (giornata)', 'Produzione', 'una tantum', null, '900.00', null, null, 480],
    'reel' => ['Pacchetto 4 video reel', 'Produzione', 'una tantum', null, '1200.00', '300.00', 'drone', 960],
    'seo' => ['SEO e copywriting', 'Contenuti', 'ricorrente', 'mensile', '450.00', null, null, 600],
    'adv' => ['Campagna ADV - impostazione', 'Social', 'una tantum', null, '600.00', null, null, 240],
    'consulenza' => ['Consulenza strategica', 'Consulenza', 'una tantum', null, '400.00', null, null, 180],
] as $key => [$name, $category, $billing, $recurrence, $price, $cost, $supplier, $minutes]) {
    $s[$key] = $services->insert([
        'name' => $name, 'category' => $category, 'stage' => 'attivo', 'billing_type' => $billing, 'recurrence' => $recurrence,
        'price' => $price, 'cost_price' => $cost, 'supplier_customer_id' => $supplier !== null ? $c[$supplier] : null,
        'duration_minutes' => $minutes, 'tax_rate_percent' => '22.00',
    ]);
}

// --- servizi venduti --------------------------------------------------------
$sell = static function (string $customer, string $service, array $data) use ($c, $s, $services, $sold, $plans): int {
    $catalog = $services->find($s[$service]);
    $id = $sold->insert($data + [
        'customer_id' => $c[$customer],
        'service_id' => $s[$service],
        'title' => $catalog->name,
        'stage' => 'attivo',
        'billing_type' => $catalog->billingType,
        'recurrence' => $catalog->recurrence,
        'price' => $catalog->price,
        'cost_price' => $catalog->costPrice,
        'supplier_customer_id' => $catalog->supplierCustomerId,
        'payment_plan' => 'unica soluzione',
        'payment_method' => 'bonifico',
        'notice_days' => 21,
        'auto_renew' => 0,
    ]);
    $plans->createInitialPlan($id);

    return $id;
};

$sell('gino', 'sito', ['title' => 'Nuovo sito della trattoria', 'start_date' => $day(-36), 'payment_plan' => 'acconto e saldo', 'deposit_percent' => '50.00', 'balance_date' => $day(9)]);
$sell('gino', 'hosting', ['start_date' => $day(-351), 'auto_renew' => 1, 'payment_method' => 'addebito SEPA']);
$sell('gino', 'social', ['start_date' => $day(-234), 'auto_renew' => 1, 'payment_method' => 'addebito SEPA']);

$sell('bellavista', 'ecommerce', ['title' => 'E-commerce occhiali', 'start_date' => $day(-713), 'renewal_date' => $day(17)]);
$sell('bellavista', 'social_adv', ['start_date' => $day(-293), 'auto_renew' => 1, 'payment_method' => 'carta']);
$sell('bellavista', 'shooting', ['title' => 'Shooting collezione autunno', 'start_date' => $day(7)]);

$sell('polare', 'shooting', ['title' => 'Shooting nuovi gusti', 'start_date' => $day(-31), 'payment_plan' => 'acconto e saldo', 'deposit_percent' => '30.00', 'balance_date' => $day(-18)]);
$sell('polare', 'reel', ['title' => 'Reel estate gelateria', 'start_date' => $day(-81), 'payment_plan' => 'rate', 'installments_count' => 3]);

$sell('moretti', 'sito_canone', ['start_date' => $day(-315), 'auto_renew' => 1, 'renewal_date' => $day(50), 'payment_method' => 'addebito SEPA']);
$sell('moretti', 'seo', ['start_date' => $day(-262), 'auto_renew' => 1, 'payment_method' => 'addebito SEPA']);
$sell('moretti', 'adv', ['title' => 'Campagna sbiancamento autunno', 'start_date' => $day(-6)]);

$sell('aurora', 'ecommerce', ['title' => 'E-commerce Aurora', 'start_date' => $day(-570), 'auto_renew' => 1, 'payment_method' => 'carta']);
$sell('aurora', 'social_adv', ['start_date' => $day(-293), 'auto_renew' => 1, 'payment_method' => 'carta']);
$sell('aurora', 'shooting', ['title' => 'Shooting collezione primavera-estate', 'price' => '1800.00', 'start_date' => $day(30), 'payment_plan' => 'rate', 'installments_count' => 2]);

$sell('colleverde', 'social', ['start_date' => $day(-173), 'auto_renew' => 1]);
$sell('colleverde', 'reel', ['title' => 'Video vendemmia', 'start_date' => $day(-50), 'supplier_customer_id' => $c['drone']]);
$sell('colleverde', 'sito_canone', ['start_date' => $day(-325), 'renewal_date' => $day(41)]);

$sell('fitzone', 'social', ['start_date' => $day(-282), 'auto_renew' => 1, 'payment_method' => 'addebito SEPA']);

$sell('poggio', 'sito', ['title' => 'Sito agriturismo con prenotazioni', 'start_date' => $day(-20), 'payment_plan' => 'rate', 'installments_count' => 3]);
$sell('poggio', 'hosting', ['start_date' => $day(-20), 'auto_renew' => 1]);

$sell('miramare', 'social', ['start_date' => $day(-142), 'auto_renew' => 1, 'payment_method' => 'addebito SEPA']);
$sell('miramare', 'consulenza', ['title' => 'Consulenza piano marketing 2027', 'start_date' => $day(4)]);

// Un servizio chiuso, per vedere anche lo storico.
$closed = $sell('gino', 'adv', ['title' => 'Campagna menu estivo', 'start_date' => $day(-120)]);
$sold->update($closed, ['stage' => 'concluso', 'end_date' => $day(-90)]);

// --- rinnovi automatici arretrati e incassi passati -------------------------
$plans->runAutoRenewals($today);

// In ritardo apposta: il saldo della gelateria e l'ultimo mese della palestra.
$keepOverdue = [];
foreach ($installments->findOpenUntil($day(-1)) as $installment) {
    $isPolareSaldo = (int) $installment->customerId === $c['polare'] && $installment->kind === 'saldo';
    $isFitzoneLast = (int) $installment->customerId === $c['fitzone'] && (string) $installment->dueDate >= $day(-25);
    if ($isPolareSaldo || $isFitzoneLast) {
        $keepOverdue[] = (int) $installment->id;
        continue;
    }
    $delay = ((int) $installment->id * 7) % 9;
    $paidAt = min($today, date('Y-m-d', strtotime((string) $installment->dueDate . " +{$delay} days")));
    $plans->markPaid((int) $installment->id, $paidAt);
}
foreach ($keepOverdue as $index => $id) {
    if ($index === 0) {
        $installments->update($id, ['reminder_sent_at' => $day(-4) . ' 10:30:00']);
    }
}

// --- tipi di commessa con le fasi a modello --------------------------------
$typesRepo = $container->get(WorkOrderTypeRepository::class);
$stepsRepo = $container->get(WorkOrderTypeStepRepository::class);
$t = [];
// [titolo, giorno, minuti, aspetta il cliente, checklist]
foreach ([
    'sito' => ['Sito vetrina', 30, "Dal brief alla messa online: grafica nostra o da Figma, sviluppo dal fornitore.", [
        ['Brief con il cliente', 0, 60, false, "Obiettivi e pubblico\nPagine e contenuti\nSiti di riferimento\nScelta: grafica nostra o Figma del cliente"],
        ['Ricevere testi e foto dal cliente', 3, 15, true, "Testi delle pagine\nFoto e logo in alta qualita\nDati per privacy e cookie"],
        ['Proposta grafica', 7, 480, false, "Home\nPagina interna tipo\nVersione mobile"],
        ['Approvazione grafica del cliente', 12, 30, true, null],
        ['Sviluppo del sito (fornitore)', 14, 120, false, "Mandare grafica e contenuti al fornitore\nVerificare la consegna"],
        ['Inserimento contenuti', 22, 240, false, null],
        ['Controllo finale e SEO di base', 26, 120, false, "Titoli e descrizioni\nVelocita\nModuli di contatto\nCookie banner"],
        ['Messa online e consegna', 30, 60, false, "Dominio e certificato\nAccessi al cliente\nAvviso al cliente"],
    ]],
    'ecommerce' => ['E-commerce', 60, "Negozio online completo, sviluppo dal fornitore.", [
        ['Brief con il cliente', 0, 90, false, "Catalogo e categorie\nPagamenti e spedizioni\nTempi"],
        ['Ricevere catalogo prodotti dal cliente', 5, 15, true, "Elenco prodotti con prezzi\nFoto prodotti\nVarianti e taglie"],
        ['Proposta grafica', 10, 600, false, null],
        ['Approvazione grafica del cliente', 17, 30, true, null],
        ['Sviluppo (fornitore)', 20, 180, false, null],
        ['Caricamento prodotti', 38, 600, false, null],
        ['Pagamenti, spedizioni e prove di ordine', 50, 240, false, "Carta e PayPal\nSpese di spedizione\nOrdine di prova completo"],
        ['Messa online e consegna', 60, 90, false, null],
    ]],
    'shooting' => ['Shooting fotografico', 10, "Una giornata di scatti: organizzazione, scatti, post-produzione.", [
        ['Brief e moodboard', 0, 60, false, "Stile e riferimenti\nElenco degli scatti"],
        ['Organizzare location e materiale', 2, 120, false, "Location\nAttrezzatura\nModelle o prodotti"],
        ['Conferma della data con il cliente', 3, 15, true, null],
        ['Giornata di shooting', 7, 480, false, "Batterie e schede\nLiberatorie firmate"],
        ['Selezione e post-produzione', 9, 360, false, null],
        ['Consegna delle foto', 10, 30, false, null],
    ]],
    'adv' => ['Campagna ADV', 14, "Campagna a pagamento su Meta: creativita, impostazione, controllo.", [
        ['Obiettivi e budget', 0, 60, false, null],
        ['Testi e creativita', 2, 240, false, "3 varianti di testo\nImmagini e video nei formati giusti"],
        ['Approvazione del cliente', 4, 15, true, null],
        ['Impostazione delle campagne', 5, 120, false, "Pubblico\nBudget giornaliero\nPixel e conversioni"],
        ['Controllo dei primi risultati', 9, 60, false, null],
        ['Report finale al cliente', 14, 90, false, null],
    ]],
    'reel' => ['Pacchetto video reel', 14, "Quattro reel: idee, riprese, montaggio.", [
        ['Idee e script', 0, 180, false, null],
        ['Approvazione degli script', 2, 15, true, null],
        ['Giornata di riprese', 5, 480, false, null],
        ['Montaggio', 8, 480, false, "Musiche\nSottotitoli\nFormato verticale"],
        ['Revisione del cliente', 11, 15, true, null],
        ['Consegna dei video', 14, 30, false, null],
    ]],
    'social' => ['Avvio gestione social', 10, "Partenza di un cliente nuovo sui social.", [
        ['Accessi agli account dal cliente', 0, 15, true, "Facebook e Instagram\nTikTok"],
        ['Analisi dei profili e dei concorrenti', 2, 120, false, null],
        ['Piano editoriale del primo mese', 4, 240, false, null],
        ['Approvazione del piano', 6, 15, true, null],
        ['Primi contenuti', 8, 360, false, null],
    ]],
    'consulenza' => ['Consulenza', 7, "Analisi e presentazione al cliente.", [
        ['Raccolta delle informazioni', 0, 90, false, null],
        ['Analisi', 2, 240, false, null],
        ['Presentazione al cliente', 7, 90, false, null],
    ]],
] as $key => [$name, $days, $description, $steps]) {
    $t[$key] = $typesRepo->insert(['name' => $name, 'default_days' => $days, 'description' => $description, 'stage' => 'in uso']);
    foreach ($steps as $i => [$title, $offset, $minutes, $waits, $checklist]) {
        $stepsRepo->insert(['work_order_type_id' => $t[$key], 'position' => $i + 1, 'title' => $title, 'offset_days' => $offset, 'estimated_minutes' => $minutes, 'waits_for_client' => $waits ? 1 : 0, 'checklist' => $checklist]);
    }
}

// --- commesse in stati diversi ---------------------------------------------
$orders = $container->get(WorkOrderRepository::class);
$tasksRepo = $container->get(TaskRepository::class);
$work = $container->get(WorkOrderService::class);
$makeOrder = static function (string $customer, string $type, string $title, int $startOffset, int $owner, int $done, array $extra = []) use ($c, $t, $day, $today, $orders, $tasksRepo, $work): int {
    $id = $orders->insert(['customer_id' => $c[$customer], 'work_order_type_id' => $t[$type], 'title' => $title, 'stage' => 'da avviare', 'priority' => $extra['priority'] ?? 'normale', 'owner_user_id' => $owner, 'start_date' => $day($startOffset), 'due_date' => isset($extra['due']) ? $day($extra['due']) : null, 'supplier_customer_id' => isset($extra['supplier']) ? $c[$extra['supplier']] : null, 'notes' => $extra['notes'] ?? null]);
    $work->createStepsFromType($id);
    foreach ($tasksRepo->findForEntity(WorkOrderService::ENTITY, $id) as $i => $task) {
        // Chi fa cosa: la grafica a Lena (2), riprese e montaggi a Tommaso (3).
        $who = preg_match('/grafica|creativita|piano editoriale|contenuti/i', (string) $task->title) ? 2 : (preg_match('/shooting|riprese|montaggio|post-produzione/i', (string) $task->title) ? 3 : $owner);
        foreach ($extra['assign'] ?? [] as $pattern => $userId) {
            if (preg_match($pattern, (string) $task->title) === 1) {
                $who = $userId;
            }
        }
        $data = ['assigned_user_id' => $who];
        if ($i < $done) {
            $completed = min($day(-1), date('Y-m-d', strtotime((string) $task->plannedDate . ' +1 day')));
            $factor = $extra['factors'][$i] ?? [0.8, 1.0, 1.1, 0.9, 1.2][$i % 5];
            // Variazione piccola e ripetibile, perche' i tempi veri non sono mai tondi.
            $factor *= 1 + ((((int) $task->id * 37) % 11) - 5) / 100;
            $data += ['stage' => 'fatta', 'completed_at' => $completed . ' 17:30:00', 'actual_minutes' => $task->estimatedMinutes ? (int) round($task->estimatedMinutes * $factor) : null];
        }
        $tasksRepo->update((int) $task->id, $data);
    }
    // Le fasi rimaste partono da quando la commessa si e' fermata davvero
    // (come farebbe la ripianificazione usandola), non dal modello originale.
    if ($done > 0 && !isset($extra['delivered'])) {
        $open = array_values(array_filter($tasksRepo->findForEntity(WorkOrderService::ENTITY, $id), static fn ($task) => $task->isOpen()));
        if ($open !== []) {
            $target = $day(-($extra['idle'] ?? 1) + 2);
            $delta = (int) round((strtotime($target) - strtotime((string) $open[0]->plannedDate)) / 86400);
            foreach ($open as $task) {
                $tasksRepo->update((int) $task->id, ['planned_date' => \App\Work\Workdays::adjust(date('Y-m-d', strtotime("{$task->plannedDate} {$delta} days")))]);
            }
        }
    }
    $work->refresh($id, false);
    $after = ['last_activity_at' => $day(-($extra['idle'] ?? 1)) . ' 16:00:00'];
    if (isset($extra['waiting'])) {
        $after['waiting_since'] = $day(-$extra['waiting']);
    }
    if (isset($extra['delivered'])) {
        $after += ['stage' => 'consegnata', 'delivered_at' => $day($extra['delivered'])];
    }
    $orders->update($id, $after);

    return $id;
};

$orderIds = [];
$orderIds['gino'] = $makeOrder('gino', 'sito', 'Nuovo sito della trattoria', -36, 2, 3, ['due' => 9, 'waiting' => 6, 'idle' => 6, 'supplier' => 'webfactory', 'notes' => "Il cliente vuole mettere in evidenza il menu del giorno e le prenotazioni."]);
$orderIds['bellavista'] = $makeOrder('bellavista', 'shooting', 'Shooting collezione autunno', -5, 3, 3, ['due' => 5, 'priority' => 'alta']);
$makeOrder('polare', 'reel', 'Reel nuovi gusti', -30, 3, 6, ['due' => -16, 'delivered' => -12, 'idle' => 12]);
$makeOrder('moretti', 'adv', 'Campagna sbiancamento autunno', -6, 1, 3, ['due' => 8, 'idle' => 3, 'factors' => [1 => 2.1]]);
$orderIds['aurora'] = $makeOrder('aurora', 'shooting', 'Shooting collezione primavera-estate', 30, 3, 0, ['due' => 40]);
$makeOrder('colleverde', 'reel', 'Video vendemmia', -50, 2, 3, ['due' => 4, 'idle' => 12, 'supplier' => 'drone']);
$orderIds['poggio'] = $makeOrder('poggio', 'sito', 'Sito agriturismo con prenotazioni', -20, 1, 1, ['due' => 10, 'waiting' => 14, 'idle' => 14, 'supplier' => 'webfactory']);
$orderIds['miramare'] = $makeOrder('miramare', 'consulenza', 'Consulenza piano marketing 2027', 4, 1, 0);
$makeOrder('fitzone', 'social', 'Nuovi format per i social della palestra', -9, 2, 3, ['due' => 1]);
$makeOrder('gino', 'adv', 'Campagna menu estivo', -120, 2, 6, ['due' => -106, 'delivered' => -104, 'idle' => 104]);

// Commesse dei mesi scorsi, tutte consegnate: la storia da cui il registro
// impara i tempi. La "Proposta grafica" dura di solito piu' delle 8 h del
// modello; sulla post-produzione Lena e' piu' lenta di Tommaso.
$makeOrder('moretti', 'sito', 'Restyling sito dello studio', -165, 2, 8, ['due' => -135, 'delivered' => -133, 'idle' => 133, 'factors' => [2 => 1.3]]);
$makeOrder('colleverde', 'sito', 'Sito della cantina', -150, 2, 8, ['due' => -120, 'delivered' => -118, 'idle' => 118, 'factors' => [2 => 1.25]]);
$makeOrder('fitzone', 'sito', 'Sito della palestra', -125, 2, 8, ['due' => -95, 'delivered' => -94, 'idle' => 94, 'factors' => [2 => 1.2]]);
$makeOrder('aurora', 'shooting', 'Shooting collezione autunno-inverno', -140, 3, 6, ['due' => -130, 'delivered' => -129, 'idle' => 129, 'assign' => ['/post-produzione/' => 2], 'factors' => [4 => 1.7]]);
$makeOrder('bellavista', 'shooting', 'Shooting occhiali da sole', -110, 3, 6, ['due' => -100, 'delivered' => -99, 'idle' => 99, 'factors' => [4 => 0.9]]);
$makeOrder('polare', 'shooting', 'Shooting vetrina e laboratorio', -80, 3, 6, ['due' => -70, 'delivered' => -69, 'idle' => 69, 'assign' => ['/post-produzione/' => 2], 'factors' => [4 => 1.6]]);
$makeOrder('miramare', 'shooting', 'Shooting camere e spiaggia', -60, 3, 6, ['due' => -50, 'delivered' => -49, 'idle' => 49, 'factors' => [4 => 0.95]]);
$makeOrder('fitzone', 'reel', 'Reel allenamenti', -95, 3, 6, ['due' => -81, 'delivered' => -80, 'idle' => 80]);
$makeOrder('miramare', 'reel', 'Reel estate in hotel', -70, 3, 6, ['due' => -56, 'delivered' => -55, 'idle' => 55]);
$makeOrder('bellavista', 'adv', 'Campagna saldi', -45, 2, 6, ['due' => -31, 'delivered' => -30, 'idle' => 30]);
$makeOrder('fitzone', 'adv', 'Campagna iscrizioni settembre', -60, 2, 6, ['due' => -46, 'delivered' => -45, 'idle' => 45]);

// --- calendario: uscite, incontri, call ---------------------------------------
$appointmentsRepo = $container->get(AppointmentRepository::class);
// Il giorno dello shooting e' quello della fase "Giornata di shooting" della commessa.
$shootingDay = $day(2);
foreach ($tasksRepo->findForEntity(WorkOrderService::ENTITY, $orderIds['bellavista']) as $task) {
    if (str_contains((string) $task->title, 'shooting')) {
        $shootingDay = (string) $task->plannedDate;
    }
}
// n giorni lavorativi da oggi (0 = oggi).
$workday = static function (int $n) use ($today): string {
    $date = $today;
    for ($i = 0; $i < $n; $i++) {
        $date = \App\Work\Workdays::adjust(date('Y-m-d', strtotime("{$date} +1 day")));
    }

    return $date;
};
$monday = date('Y-m-d', strtotime('monday this week', strtotime($today)));
$nextMonday = date('Y-m-d', strtotime("{$monday} +7 days"));
foreach ([
    ['Riunione settimanale', 'incontro in sede', $monday, '09:00', '09:45', null, 0, 0, 1, '2,3', null, null, 'confermato', "Commesse ferme\nRinnovi del mese\nShooting della settimana"],
    ['Riunione settimanale', 'incontro in sede', $nextMonday, '09:00', '09:45', null, 0, 0, 1, '2,3', null, null, 'confermato', null],
    ['Shooting collezione autunno', 'shooting', $shootingDay, '09:00', '17:00', 'Via Garibaldi 21, Parma', 30, 0, 3, '2', 'bellavista', 'bellavista', 'confermato', "Due corpi macchina e batterie\nLuci e pannelli\nShot list approvata\nLiberatorie delle modelle"],
    ['Call risultati campagna sbiancamento', 'call', $shootingDay, '11:00', '11:30', null, 0, 1, 1, '2', 'moretti', null, 'confermato', null],
    ['Presentazione della grafica del sito', 'incontro dal cliente', $workday(0), '15:00', '16:00', 'Strada Farini 12, Parma', 15, 0, 2, null, 'gino', 'gino', 'confermato', "Mockup stampati\nTablet con la versione mobile"],
    ['Sopralluogo per le foto del sito', 'sopralluogo', $workday(3), '10:00', '11:30', 'Strada del Poggio 4, Langhirano', 40, 0, 1, '3', 'poggio', 'poggio', 'confermato', "Fotocamera e drone\nElenco delle stanze da fotografare"],
    ['Incontro per il piano marketing 2027', 'incontro dal cliente', $workday(4), '10:00', '11:30', 'Viale Romagna 80, Cervia', 50, 0, 1, null, 'miramare', 'miramare', 'da confermare', null],
    ['Call di aggiornamento con WebFactory', 'call', $workday(2), '16:30', '17:00', null, 0, 1, 2, null, 'webfactory', null, 'confermato', null],
    ['Shooting collezione primavera-estate', 'shooting', \App\Work\Workdays::adjust($day(31)), '09:00', '17:00', 'Borgo Giacomo Tommasini 5, Parma', 20, 0, 3, '2', 'aurora', 'aurora', 'da confermare', null],
    ['Consegna video e brindisi', 'incontro dal cliente', $day(-12), '18:00', '19:00', 'Via Mazzini 3, Parma', 10, 0, 3, null, 'polare', null, 'fatto', null],
] as [$title, $kind, $date, $from, $to, $where, $travel, $online, $owner, $others, $customer, $order, $stage, $checklist]) {
    $appointmentsRepo->insert([
        'title' => $title, 'kind' => $kind, 'start_at' => "{$date} {$from}:00", 'end_at' => "{$date} {$to}:00",
        'location' => $where, 'travel_minutes' => $travel ?: null, 'is_online' => $online, 'stage' => $stage,
        'assigned_user_id' => $owner, 'attendee_user_ids' => $others,
        'customer_id' => $customer !== null ? $c[$customer] : null,
        'entity_type' => $order !== null ? 'work_orders' : null, 'entity_id' => $order !== null ? $orderIds[$order] : null,
        'checklist' => $checklist, 'reminder_minutes_before' => $kind === 'call' ? 10 : 60, 'source' => 'uno',
    ]);
}

// --- orari: il socio commerciale e' spesso fuori, pianificabile solo al 40% ---
$schedulesRepo = $container->get(WorkScheduleRepository::class);
foreach ([
    [1, '09:30', '19:00', '13:30', '14:30', '1,2,3,4,5', 40],
    [2, '09:00', '18:00', '13:00', '14:00', '1,2,3,4,5', 85],
    [3, '09:00', '18:00', '13:00', '14:00', '1,2,3,4,5', 85],
] as [$userId, $start, $end, $breakStart, $breakEnd, $days, $focus]) {
    $schedulesRepo->insert(['user_id' => $userId, 'start_time' => $start, 'end_time' => $end, 'break_start' => $breakStart, 'break_end' => $breakEnd, 'workdays' => $days, 'focus_percent' => $focus]);
}

// --- attivita' sciolte, anche ricorrenti -------------------------------------
foreach ([
    ['Report mensile dei social', 'moretti', 9, 60, 2, 'normale', 'ogni mese'],
    ["Controllare l'andamento delle campagne attive", null, 0, 45, 2, 'normale', 'ogni settimana'],
    ['Preparare il preventivo e-commerce per Hotel Miramare', 'miramare', 0, 90, 1, 'alta', null],
    ['Chiamare Gelateria Polare per il saldo', 'polare', -1, 15, 1, 'alta', null],
    ['Aggiornare il listino servizi 2027', null, 12, 120, 1, 'normale', null],
    ['Backup mensile delle foto dei clienti', null, 3, 30, 3, 'bassa', 'ogni mese'],
    ['Proporre il pacchetto reel a Cantina Colle Verde', 'colleverde', 2, 30, 2, 'normale', null],
] as [$title, $customer, $offset, $minutes, $who, $priority, $recurrence]) {
    $tasksRepo->insert(['title' => $title, 'planned_date' => \App\Work\Workdays::adjust($day($offset)), 'estimated_minutes' => $minutes, 'assigned_user_id' => $who, 'priority' => $priority, 'recurrence' => $recurrence, 'stage' => 'da fare', 'waits_for_client' => 0, 'entity_type' => $customer !== null ? 'customers' : null, 'entity_id' => $customer !== null ? $c[$customer] : null]);
}

// --- registro: una voce per ogni lavoro fatto, e le risposte dei giorni scorsi --
$entriesRepo = $container->get(WorkEntryRepository::class);
foreach ($tasksRepo->findDoneWithTimeSince($day(-200)) as $task) {
    $customerId = null;
    if ($task->entityType === 'work_orders') {
        $customerId = $orders->find((int) $task->entityId)?->customerId;
    } elseif ($task->entityType === 'customers') {
        $customerId = $task->entityId;
    }
    $entriesRepo->insert([
        'user_id' => $task->assignedUserId ?? 1,
        'work_date' => substr((string) $task->completedAt, 0, 10),
        'minutes' => $task->actualMinutes,
        'title' => $task->title,
        'task_id' => $task->id,
        'customer_id' => $customerId,
        'source' => 'check-in',
    ]);
}
$checkinsRepo = $container->get(WorkCheckinRepository::class);
for ($back = 7; $back >= 1; $back--) {
    $date = $day(-$back);
    if ((int) date('N', strtotime($date)) >= 6) {
        continue;
    }
    foreach ([1, 2, 3] as $userId) {
        $checkinsRepo->insert(['user_id' => $userId, 'checked_at' => "{$date} 13:05:00", 'period' => 'mattina']);
        $checkinsRepo->insert(['user_id' => $userId, 'checked_at' => "{$date} 17:40:00", 'period' => 'pomeriggio']);
    }
}


// --- dati per le fatture: l'agenzia e la sede fiscale dei clienti ------------
$town = static function (string $name) use ($container): ?int {
    foreach ($container->get(MunicipalityRepository::class)->search($name, 5) as $row) {
        if (strcasecmp((string) $row['name'], $name) === 0) {
            return (int) $row['id'];
        }
    }

    return null;
};
$container->get(CompanyProfileRepository::class)->insert([
    'company_name' => 'Brains at Work Srl', 'vat_number' => '02987650345', 'tax_regime' => 'RF01',
    'address' => 'Via Emilia Est 40', 'postal_code' => '43123', 'city' => 'Parma', 'province' => 'PR', 'country' => 'IT',
    'email' => 'amministrazione@brainsatwork.example', 'pec' => 'brainsatwork@pec.example', 'phone' => '0521 000111',
    'iban' => 'IT60X0542811101000000123456', 'default_tax_rate' => '22.00',
]);
foreach ([
    'gino' => ['Strada Farini 12', '43121', 'Parma', '02345670349', 'M5UXCR1'],
    'bellavista' => ['Via Garibaldi 21', '43121', 'Parma', '01987650347', 'KRRH6B9'],
    'moretti' => ['Viale Mentana 8', '43121', 'Parma', '02456780341', null],
    'aurora' => ['Borgo Giacomo Tommasini 5', '43121', 'Parma', '02567890342', 'SUBM70N'],
    'colleverde' => ['Strada del Vino 3', '43039', 'Salsomaggiore Terme', '02678900343', null],
    'fitzone' => ['Via Langhirano 120', '43124', 'Parma', '02789010344', 'USAL8PV'],
    'miramare' => ['Viale Romagna 80', '48015', 'Cervia', '02890120395', 'T04ZHR3'],
    'polare' => ['Via Mazzini 3', '43121', 'Parma', '02901230346', null],
] as $key => [$street, $cap, $city, $vat, $sdi]) {
    $customers->update($c[$key], ['address' => $street, 'postal_code' => $cap, 'municipality_id' => $town($city), 'vat_number' => $vat, 'sdi_code' => $sdi, 'pec' => $sdi === null ? 'amministrazione@pec-' . $key . '.example' : null]);
}
// Come pagano: chi e' in SEPA ha l'addebito automatico; un link fisso per provare l'area cliente in sviluppo.
foreach (['gino' => ['addebito SEPA', 1], 'moretti' => ['addebito SEPA', 1], 'fitzone' => ['addebito SEPA', 0], 'miramare' => ['bonifico', 0], 'bellavista' => ['carta', 0], 'aurora' => ['carta', 0]] as $key => [$method, $auto]) {
    $customers->update($c[$key], ['preferred_payment_method' => $method, 'auto_pay' => $auto, 'payment_mandate' => $auto ? 'pm_demo_' . $key : null, 'stripe_customer_id' => $auto ? 'cus_demo_' . $key : null]);
}
$customers->update($c['gino'], ['portal_token' => str_repeat('b5', 24)]);

// --- fatture: gli incassi degli ultimi 2 mesi fatturati, pagati e no ---------
$invoiceService = $container->get(InvoiceService::class);
$invoicesRepo = $container->get(InvoiceRepository::class);
$toInvoice = array_values(array_filter($installments->findWithoutInvoice($day(-1), $day(-60)), static fn ($i) => (int) $i->customerId !== $c['poggio']));
foreach ($toInvoice as $index => $installment) {
    $invoice = $invoiceService->createFromInstallment((int) $installment->id);
    $issueDate = $installment->stage === 'incassato' ? min((string) $installment->dueDate, (string) $installment->paidAt) : (string) $installment->dueDate;
    $invoicesRepo->update((int) $invoice->id, ['issue_date' => $issueDate]);
    $invoice = $invoiceService->issue((int) $invoice->id);
    // Le piu' vecchie gia' consegnate allo SdI (caricate a mano), le ultime 2 ancora da inviare.
    $invoicesRepo->update((int) $invoice->id, ['stage' => $installment->stage === 'incassato' ? 'pagata' : 'emessa'] + ($index < count($toInvoice) - 2
        ? ['sdi_status' => 'consegnata', 'sdi_provider' => 'manuale', 'sent_at' => $issueDate . ' 18:00:00']
        : []));
}
// Una bozza: la consulenza di Miramare, da emettere dopo l'incontro.
$draftFor = $sold->findAll(['customer_id' => $c['miramare'], 'title' => 'Consulenza piano marketing 2027']);
if ($draftFor !== []) {
    foreach ($installments->findByService((int) $draftFor[0]->id) as $installment) {
        $invoiceService->createFromInstallment((int) $installment->id);
    }
}

// --- squadra interna dei clienti: chi fa i social, la grafica, i video ---------
$teamRepo = $container->get(CustomerTeamMemberRepository::class);
$socialOf = [];
foreach ([
    'gino' => [2, 3, 3], 'bellavista' => [3, 2, 3], 'polare' => [3, 2, 3], 'moretti' => [2, 3, 3],
    'aurora' => [3, 2, 3], 'colleverde' => [2, 3, 3], 'fitzone' => [3, 2, 3], 'poggio' => [2, 3, 3], 'miramare' => [3, 2, 3],
] as $key => [$social, $graphic, $video]) {
    $account = $customers->find($c[$key])->accountUserId;
    if ($account !== null) {
        $teamRepo->insert(['customer_id' => $c[$key], 'user_id' => $account, 'role' => 'referente']);
    }
    $teamRepo->insert(['customer_id' => $c[$key], 'user_id' => $social, 'role' => 'social']);
    $teamRepo->insert(['customer_id' => $c[$key], 'user_id' => $graphic, 'role' => 'grafica']);
    $teamRepo->insert(['customer_id' => $c[$key], 'user_id' => $video, 'role' => 'video']);
    $socialOf[$key] = $social;
}
$teamRepo->insert(['customer_id' => $c['poggio'], 'user_id' => 1, 'role' => 'sviluppo', 'notes' => 'sito con prenotazioni']);

// --- strategie social: da qui l'IA propone il piano del mese ---------------------
$strategiesRepo = $container->get(EditorialStrategyRepository::class);
$strategiesRepo->insert([
    'customer_id' => $c['gino'],
    'objectives' => "Piu' coperti a pranzo nei giorni feriali; far conoscere i piatti d'autunno.",
    'audience' => "Impiegati e professionisti della zona (pranzo), coppie e famiglie del quartiere (cena).",
    'tone' => 'familiare, caldo, un po\' ironico; mai tecnico',
    'pillars' => "Piatto della settimana\nDietro le quinte in cucina\nRecensioni dei clienti\nEventi e serate",
    'dos_donts' => 'Si: foto vere dei piatti, prezzi chiari. No: foto di repertorio, troppi hashtag.',
]);
$strategiesRepo->insert([
    'customer_id' => $c['miramare'],
    'objectives' => 'Riempire i weekend di bassa stagione; far conoscere le camere rinnovate.',
    'audience' => "Coppie 30-55 anni dell'Emilia-Romagna e della Lombardia.",
    'tone' => 'elegante e rilassato, frasi brevi',
    'pillars' => "Camere e servizi\nIl mare fuori stagione\nCucina dell'hotel\nOfferte del weekend",
    'dos_donts' => 'Si: luce naturale, tramonti. No: foto affollate, prezzi senza condizioni.',
]);

// --- piano editoriale: contratti social e contenuti in ogni stato -------------
$quotasRepo = $container->get(EditorialQuotaRepository::class);
foreach ([
    ['gino', 'Instagram', 3, '2 post e 1 reel', 2],
    ['bellavista', 'Instagram', 2, '1 carosello e 1 reel', 3],
    ['bellavista', 'TikTok', 1, '1 video', 3],
    ['colleverde', 'Instagram', 2, '2 post', 2],
    ['colleverde', 'Facebook', 1, '1 post', 2],
    ['fitzone', 'Instagram', 3, '2 post e 1 reel', 3],
    ['miramare', 'Instagram', 3, '2 caroselli e 1 reel', 3],
    ['aurora', 'Instagram', 3, '1 carosello, 1 post e 1 reel', 3],
] as [$key, $channel, $perWeek, $formats, $who]) {
    $quotasRepo->insert(['customer_id' => $c[$key], 'channel' => $channel, 'per_week' => $perWeek, 'formats' => $formats, 'default_user_id' => $who]);
}
$postsRepo = $container->get(EditorialPostRepository::class);
$editorial = $container->get(EditorialService::class);
foreach ([
    // [cliente, canale, formato, giorno lavorativo (negativo: passato), ora, tema, stato, chi, testo, materiale, commento cliente]
    ['gino', 'Instagram', 'post', -6, '12:30', 'Il menu del pranzo di lavoro', 'pubblicato', 2, "Pausa pranzo? Da noi in 40 minuti: primo, secondo e caffe' a 14 euro. Prenota al tavolo o chiamaci!\n#parma #pranzodilavoro #trattoria", '2 foto dei piatti', null],
    ['gino', 'Instagram', 'reel', -3, '19:00', 'Dietro le quinte: la sfoglia di Gino', 'pubblicato', 2, "Ogni mattina alle 7 si tira la sfoglia. Il segreto? Pazienza e mattarello.\n#pastafresca #parma #tradizione", 'video 20s verticale', null],
    ['gino', 'Instagram', 'post', 1, '12:30', 'Funghi porcini: arrivano i piatti d\'autunno', 'da approvare', 2, "E' tempo di porcini: tagliatelle, risotto e il nostro tortino. Solo per poche settimane.\n#porcini #autunno #parma", '3 foto dal nuovo menu', null],
    ['gino', 'Instagram', 'post', 3, '12:30', 'Recensione del mese', 'revisione interna', 3, "\u{ab}La carbonara piu' buona di Parma\u{bb}: grazie Marco per le belle parole!\n#recensioni #trattoria #parma", 'screenshot della recensione', null],
    ['gino', 'Instagram', 'reel', 6, '19:00', 'Serata vini del territorio', 'idea', 2, null, null, null],
    ['bellavista', 'Instagram', 'carosello', -4, '18:00', '5 montature per il viso tondo', 'pubblicato', 3, "Viso tondo? Ecco le 5 montature che lo slanciano. Scorri e salva il post!\n#occhiali #ottica #parma", '5 foto prodotto', null],
    ['bellavista', 'TikTok', 'video', 2, '18:30', 'Prova occhiali in 10 secondi', 'da modificare', 3, "10 montature in 10 secondi: qual e' la tua?\n#occhiali #tryon", 'video verticale 15s', 'Il ritmo e\' troppo veloce, e mettiamo anche le montature da sole.'],
    ['bellavista', 'Instagram', 'reel', 4, '18:00', 'Shooting collezione autunno: il backstage', 'idea', 3, null, 'clip dallo shooting', null],
    ['colleverde', 'Instagram', 'post', -2, '11:00', 'Vendemmia 2026: e\' finita!', 'pubblicato', 2, "Ultimo grappolo raccolto: grazie a tutta la squadra! Ora la parola passa alla cantina.\n#vendemmia2026 #vino #collinedelparmense", 'foto di gruppo', null],
    ['colleverde', 'Instagram', 'post', 2, '11:00', 'Il Malvasia frizzante incontra la torta fritta', 'approvato', 2, "Abbinamento perfetto per l'aperitivo: Malvasia frizzante e torta fritta calda.\n#malvasia #tortafritta #aperitivo", 'foto bottiglia in osteria', null],
    ['fitzone', 'Instagram', 'post', -5, '07:30', 'Orari nuovi corsi autunno', 'pubblicato', 3, "Da lunedi' nuovi orari: pilates alle 7, functional alle 13 e alle 19.\n#palestra #parma #fitness", 'grafica orari', null],
    ['fitzone', 'Instagram', 'reel', 1, '07:30', 'Il trainer risponde: quante volte allenarsi', 'programmato', 3, "3 volte a settimana o 5? Lo chiediamo a Davide.\n#fitness #allenamento", 'video intervista 30s', null],
    ['miramare', 'Instagram', 'carosello', -1, '17:00', 'Weekend d\'autunno al mare', 'pubblicato', 3, "Il mare d'autunno ha un altro sapore: spiaggia libera, tramonti e cene di pesce. Pacchetto 2 notti da 149 euro.\n#cervia #weekend #autunno", '6 foto hotel e spiaggia', null],
    ['miramare', 'Instagram', 'carosello', 3, '17:00', 'Le camere rinnovate', 'da approvare', 3, "Abbiamo rinnovato le camere vista mare: legno chiaro, luce e silenzio. Scorri per vederle.\n#hotel #cervia #vistamare", '5 foto camere', null],
    ['miramare', 'Instagram', 'reel', 5, '18:00', 'Una giornata in hotel in 30 secondi', 'revisione interna', 3, "Dalla colazione vista mare al tramonto in terrazza: una giornata da noi in 30 secondi.\n#cervia #hotel", 'riprese drone + interni', null],
    ['miramare', 'Instagram', 'carosello', 6, '17:00', 'Offerta weekend di novembre', 'in lavorazione', 2, null, 'grafica offerta + 4 foto', null],
    ['aurora', 'Instagram', 'reel', 0, '18:30', 'Come abbinare il cappotto cammello', 'programmato', 3, "Cappotto cammello: tre abbinamenti per tre occasioni. Quale preferisci?\n#moda #outfit #parma", 'video 3 outfit', null],
    ['aurora', 'Instagram', 'post', 2, '12:00', 'Nuovi arrivi: maglieria', 'da approvare', 3, "Morbidi, caldi, colorati: sono arrivati i nuovi maglioni. Ti aspettiamo in boutique.\n#maglieria #nuoviarrivi", 'foto dal negozio', null],
] as [$key, $channel, $format, $offset, $time, $title, $stage, $who, $caption, $media, $feedback]) {
    $date = $offset >= 0 ? $workday($offset) : $day($offset);
    $clientApproved = in_array($stage, ['approvato', 'programmato', 'pubblicato'], true);
    // Testo dal social media manager della squadra, realizzazione da chi e' indicato; approvazioni registrate con chi e quando.
    $postId = $postsRepo->insert([
        'customer_id' => $c[$key], 'channel' => $channel, 'format' => $format, 'publish_date' => $date, 'publish_time' => $time,
        'title' => $title, 'caption' => $caption, 'media_notes' => $media, 'stage' => $stage,
        'writer_user_id' => $socialOf[$key] ?? null, 'assigned_user_id' => $who,
        'client_feedback' => $feedback, 'revision_count' => $feedback !== null ? 2 : ($clientApproved && $offset % 2 === 0 ? 1 : 0),
        'internal_approved_by' => $clientApproved || $stage === 'da approvare' ? 1 : null,
        'internal_approved_at' => $clientApproved || $stage === 'da approvare' ? $day(min(-2, $offset - 3)) . ' 17:00:00' : null,
        'approved_at' => $clientApproved ? $day(min(-1, $offset - 2)) . ' 10:00:00' : null,
        'approved_via' => $clientApproved ? ($offset % 2 === 0 ? 'area cliente' : 'WhatsApp, segnato da agenzia') : null,
    ]);
    $editorial->syncTask($postId);
}
// Le bozze che mancano ai contratti, tranne due lasciati scoperti per vedere l'avviso.
foreach ($quotasRepo->findAll() as $quota) {
    if (!in_array([(int) $quota->customerId, $quota->channel], [[$c['colleverde'], 'Facebook'], [$c['fitzone'], 'Instagram']], true)) {
        $editorial->fillGaps((int) $quota->customerId, (string) $quota->channel, $today);
    }
}

// --- richieste dei clienti: da smistare, in corso, un extra da mettere in conto ---
$requestsRepo = $container->get(ClientRequestRepository::class);
$requestService = $container->get(\App\ClientRequests\ClientRequestService::class);
foreach ([
    ['gino', 'Cambiare il prezzo del menu del pranzo sul sito', 'assistenza tecnica', 'whatsapp', 0, 'normale', null, false, null, false],
    ['aurora', 'Una storia per i saldi di sabato', 'nuovo contenuto', 'whatsapp', 0, 'alta', 1, false, null, false],
    ['moretti', 'Informazioni sulla fattura di ottobre', 'amministrativa', 'telefono', -1, 'normale', null, true, null, false],
    ['miramare', 'Locandina A3 per la serata di San Martino', 'nuovo contenuto', 'email', -2, 'normale', 3, false, 120, false],
    ['bellavista', 'Banner del sito per la promo occhiali da sole', 'modifica', 'telefono', -6, 'normale', null, false, 90, true],
    ['fitzone', 'Lamenta pochi contatti dalle sponsorizzate', 'altro', 'telefono', -3, 'alta', null, true, null, false],
] as [$key, $summary, $kind, $channel, $offset, $priority, $dueIn, $unassigned, $extra, $done]) {
    $id = $requestsRepo->insert([
        'customer_id' => $c[$key], 'summary' => $summary, 'kind' => $kind, 'channel' => $channel,
        'received_at' => $day($offset) . ' 10:15:00', 'received_by_user_id' => 1, 'stage' => 'nuova', 'priority' => $priority,
        'due_date' => $dueIn !== null ? $workday($dueIn) : null, 'estimated_minutes' => 30,
        'is_extra' => $extra !== null ? 1 : 0, 'extra_amount' => $extra,
    ]);
    if (!$unassigned) {
        $requestService->sync($id);
    }
    if ($done) {
        $requestService->complete($id, 'Fatto e mandato al cliente per conferma');
    }
}

// --- preventivi: una bozza, uno dal cliente, uno rifiutato ------------------------
$quotesRepo = $container->get(QuoteRepository::class);
$quoteLines = $container->get(QuoteLineRepository::class);
$quoteService = $container->get(\App\Quotes\QuoteService::class);
foreach ([
    ['miramare', 'Piano marketing 2027: sito, social e campagne', 'inviato', -5, [['sito', 'Restyling del sito con prenotazione diretta', 1, 2400, 'acconto e saldo'], ['social_adv', 'Gestione social + campagne ADV', 1, 950, 'unica soluzione'], ['shooting', 'Shooting camere e spazi comuni', 1, 900, 'unica soluzione']], 5],
    ['poggio', 'Social per la stagione 2027', 'bozza', 0, [['social', 'Gestione social - 3 post a settimana', 1, 650, 'unica soluzione'], ['reel', 'Pacchetto 4 video reel', 1, 1200, 'rate']], 0],
    ['colleverde', 'Video di Natale e campagna', 'rifiutato', -20, [['reel', 'Video emozionale di Natale', 1, 1500, 'unica soluzione'], ['adv', 'Campagna ADV - impostazione', 1, 600, 'unica soluzione']], 0],
] as [$key, $title, $stage, $offset, $lines, $discount]) {
    $quoteId = $quotesRepo->insert([
        'customer_id' => $c[$key], 'title' => $title, 'quote_date' => $day($offset), 'valid_until' => $day($offset + 30), 'stage' => 'bozza',
        'owner_user_id' => 1, 'included_revisions' => 2, 'discount_percent' => $discount ?: null,
        'intro' => 'Come concordato nel nostro incontro, ecco la proposta per il prossimo anno.',
        'conditions' => "Tempi: partenza entro 15 giorni dall'accettazione.\nServono dal cliente: logo, accessi ai profili, materiali fotografici.",
    ]);
    $quoteService->initialize($quoteId);
    foreach ($lines as $position => [$service, $description, $qty, $price, $plan]) {
        $catalog = $services->find($s[$service]);
        $quoteLines->insert([
            'quote_id' => $quoteId, 'service_id' => $s[$service], 'description' => $description, 'quantity' => $qty, 'unit_price' => $price,
            'billing_type' => $catalog->billingType, 'recurrence' => $catalog->recurrence, 'payment_plan' => $plan,
            'deposit_percent' => $plan === 'acconto e saldo' ? 40 : null, 'installments_count' => $plan === 'rate' ? 3 : null, 'position' => $position + 1,
        ]);
    }
    $quoteService->recalc($quoteId);
    if ($stage !== 'bozza') {
        $quotesRepo->update($quoteId, ['stage' => 'approvato', 'approved_by' => 1, 'approved_at' => $day($offset) . ' 18:00:00']);
        $quotesRepo->update($quoteId, ['stage' => 'inviato', 'sent_at' => $day($offset) . ' 18:30:00']);
    }
    if ($stage === 'rifiutato') {
        $quotesRepo->update($quoteId, ['stage' => 'rifiutato', 'rejected_reason' => 'Budget di Natale gia\' speso in fiera']);
    }
}

// --- ferie, permessi e spese -------------------------------------------------------
$leavesRepo = $container->get(LeaveRequestRepository::class);
$leavesRepo->insert(['user_id' => 2, 'kind' => 'ferie', 'start_date' => $workday(12), 'end_date' => $workday(16), 'stage' => 'richiesta', 'note' => 'Ponte di dicembre']);
$leavesRepo->insert(['user_id' => 3, 'kind' => 'permesso', 'start_date' => $workday(2), 'start_time' => '09:00', 'end_time' => '11:00', 'stage' => 'approvata', 'decided_by' => 1, 'decided_at' => $day(-2) . ' 12:00:00', 'note' => 'Visita medica']);
$expensesRepo = $container->get(ExpenseRepository::class);
$shootingOrder = $orderIds['bellavista'] ?? null;
foreach ([
    [3, -9, 'carburante', 38.50, 'Sopralluogo per lo shooting', 'bellavista', 'soldi miei', 'approvata'],
    [3, -9, 'parcheggio', 6.00, 'Sopralluogo per lo shooting', 'bellavista', 'soldi miei', 'rimborsata'],
    [2, -1, 'materiali', 24.90, 'Fondali e nastro per i prodotti', 'bellavista', 'carta aziendale', 'da approvare'],
    [3, 0, 'pedaggio', 12.40, 'Riprese in cantina', 'colleverde', 'soldi miei', 'da approvare'],
] as [$who, $offset, $kind, $amount, $description, $key, $paidBy, $stage]) {
    $expensesRepo->insert([
        'user_id' => $who, 'expense_date' => $day($offset), 'kind' => $kind, 'amount' => $amount, 'description' => $description,
        'customer_id' => $c[$key], 'work_order_id' => $key === 'bellavista' ? $shootingOrder : null, 'paid_by' => $paidBy, 'rebill' => 0, 'stage' => $stage,
        'decided_by' => $stage !== 'da approvare' ? 1 : null, 'decided_at' => $stage !== 'da approvare' ? $day($offset + 1) . ' 09:00:00' : null,
        'reimbursed_at' => $stage === 'rimborsata' ? $day($offset + 3) : null,
    ]);
}

// --- temperatura: contatti registrati ed esiti dei servizi --------------------
$touchRepo = $container->get(CustomerTouchpointRepository::class);
foreach ([
    ['bellavista', -6, 'incontro', 3, 'contento', 'Visto il calendario dello shooting, contenta dei risultati delle campagne', null, null],
    ['gino', -12, 'telefono', 2, 'neutro', 'Grafica del sito: vuole vedere una seconda proposta per la home', 'Mandare la seconda proposta', 2],
    ['moretti', -5, 'videochiamata', 2, 'contento', 'Primi risultati della campagna sbiancamento: 14 richieste in una settimana', null, null],
    ['aurora', -30, 'whatsapp', 3, 'neutro', 'Chiede se possiamo anticipare lo shooting di primavera', null, null],
    ['colleverde', -20, 'incontro', 2, 'contento', 'In cantina per la vendemmia: vogliono un video anche per Natale', 'Preventivo video di Natale', 6],
    ['fitzone', -50, 'telefono', 3, 'freddo', 'Si lamenta che dai social arrivano pochi nuovi iscritti', null, null],
    ['miramare', -8, 'telefono', 3, 'contento', 'Confermato l\'incontro per il piano marketing 2027', null, null],
    ['polare', -15, 'incontro', 3, 'scontento', 'Non contento dei tempi di consegna dei reel: dice che sono arrivati a fine estate', null, null],
] as [$key, $offset, $channel, $who, $mood, $summary, $next, $nextIn]) {
    $touchRepo->insert(['customer_id' => $c[$key], 'contact_date' => $day($offset), 'channel' => $channel, 'user_id' => $who, 'mood' => $mood, 'summary' => $summary, 'next_step' => $next, 'next_date' => $nextIn !== null ? $workday($nextIn) : null]);
}
foreach ($sold->findActive() as $service) {
    $result = match (true) {
        $service->title === 'Gestione social - 3 post a settimana' && (int) $service->customerId === $c['fitzone'] => ['scarsi', 'Copertura in calo da 3 mesi, pochi contatti dai post.'],
        $service->title === 'Gestione social - 3 post a settimana' && (int) $service->customerId === $c['gino'] => ['parziali', 'Bene le storie, poco i post del menu.'],
        str_contains((string) $service->title, 'ADV') => ['buoni', 'Costo per contatto sotto obiettivo.'],
        default => null,
    };
    if ($result !== null) {
        $sold->update((int) $service->id, ['results' => $result[0], 'results_note' => $result[1], 'results_updated_at' => $day(-10) . ' 12:00:00']);
    }
}

$counts = [];
foreach ($tables as $table) {
    $counts[] = $table . ': ' . count($container->get(\App\Service\DbService::class)->fetchAll("SELECT id FROM `{$table}`"));
}
fwrite(STDOUT, 'Dati demo creati - ' . implode(', ', $counts) . "\n");

// --- esportazione per il preset --------------------------------------------
$header = "-- Dati demo del preset Brains at Work (agenzia di comunicazione), generati da\n"
    . "-- generate-demo.php il {$today}: nomi inventati. All'import le date vengono spostate\n"
    . "-- in avanti di quanto e' passato da 'demoReferenceDate' (preset.php).\n\n";
$sql = $demo->exportInserts(
    // Ferie e spese no: sono delle persone, in un progetto nuovo le persone sono diverse.
    ['customers', 'customer_contacts', 'services', 'customer_services', 'installments', 'invoices', 'work_order_types', 'work_order_type_steps', 'work_orders', 'tasks', 'appointments', 'work_entries', 'editorial_quotas', 'editorial_strategies', 'editorial_posts', 'customer_touchpoints', 'client_requests', 'quotes', 'quote_lines'],
    // Gli orari no: in un progetto nuovo le persone sono diverse.
    // In un progetto nuovo esiste solo l'amministratore.
    [
        // Niente link dell'area cliente ne' riferimenti Stripe della demo in un progetto vero.
        'customers' => ['account_user_id' => 1, 'created_by' => 1, 'updated_by' => 1, 'portal_token' => null, 'payment_mandate' => null, 'stripe_customer_id' => null, 'auto_pay' => 0],
        'work_orders' => ['owner_user_id' => 1],
        'tasks' => ['assigned_user_id' => 1],
        'appointments' => ['assigned_user_id' => 1, 'attendee_user_ids' => ''],
        'work_entries' => ['user_id' => 1],
        'editorial_quotas' => ['default_user_id' => 1],
        'customer_touchpoints' => ['user_id' => 1],
        'editorial_posts' => ['assigned_user_id' => 1, 'writer_user_id' => 1, 'internal_approved_by' => 1],
        'client_requests' => ['assigned_user_id' => 1, 'received_by_user_id' => 1],
        'quotes' => ['owner_user_id' => 1, 'approved_by' => 1],
    ]
);
file_put_contents(__DIR__ . '/demo.sql', $header . $sql);
fwrite(STDOUT, "Esportati in demo.sql\n");
