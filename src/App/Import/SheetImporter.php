<?php

declare(strict_types=1);

namespace App\Import;

use App\Core\Container;
use App\Repository\CaseFileRepository;
use App\Repository\CustomerRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\ImportRepository;
use App\Repository\LeadRepository;
use App\Repository\RefundRepository;

/**
 * Importazione una tantum (o quando serve) di un foglio di lavoro: schede
 * riconosciute da TabMapper, valori puliti qui (telefoni, date, aeroporti ->
 * tratta), nessun doppione (ID invio, poi email, telefono, nome) e
 * collegamenti tra contatti, pratiche, rimborsi e documenti. Rilanciabile:
 * aggiorna invece di duplicare. Il foglio non viene mai modificato.
 */
final class SheetImporter
{
    private const ORDER = ['leads' => 1, 'leads_raw' => 2, 'cases' => 3, 'refunds' => 4, 'documents' => 5, 'skip' => 9];
    private const KIND_LABELS = ['leads' => 'Contatti', 'leads_raw' => 'Contatti (modulo)', 'cases' => 'Pratiche', 'refunds' => 'Rimborsi', 'documents' => 'Documenti', 'skip' => 'Saltata'];
    private const TEST_ROW = '/\b(test|prova|tizio|caio)\b|brains/i';

    private ImportRepository $import;
    private LeadRepository $leads;
    private CustomerRepository $customers;
    private CaseFileRepository $cases;
    private RefundRepository $refunds;
    private DocumentRequestRepository $documents;

    /** @var array<string, array<string, int>> indici per trovare un contatto: id|email|phone|name => chiave => lead id */
    private array $leadIndex = [];
    /** @var array<int, array> contatti gia' noti: id => campi */
    private array $leadData = [];
    private array $customerIndex = [];
    private array $caseByLead = [];
    private array $refundKeys = [];
    private array $documentKeys = [];
    private array $users = [];
    /** @var array<int, true> contatti creati senza data: la prende un'altra scheda con lo stesso ID invio */
    private array $undated = [];

    public function __construct(private readonly Container $container)
    {
        $this->import = $container->get(ImportRepository::class);
        $this->leads = $container->get(LeadRepository::class);
        $this->customers = $container->get(CustomerRepository::class);
        $this->cases = $container->get(CaseFileRepository::class);
        $this->refunds = $container->get(RefundRepository::class);
        $this->documents = $container->get(DocumentRequestRepository::class);
    }

    /**
     * @param array<string, list<list<string>>> $tabs nome scheda => righe
     * @return array{removed: array<string, int>, tabs: list<array>, totals: array<string, int>}
     */
    public function import(array $tabs, bool $removeDemo): array
    {
        @set_time_limit(600);
        $mapper = $this->container->get(TabMapper::class);

        // 1. Capire le schede (prima di toccare qualunque dato)
        $plans = [];
        foreach ($tabs as $name => $rows) {
            [$headers, $data] = self::table($rows);
            if ($headers === [] || $data === []) {
                $plans[] = ['tab' => $name, 'kind' => 'skip', 'by' => 'vuota', 'headers' => $headers, 'rows' => [], 'columns' => [], 'values' => []];
                continue;
            }
            $mapping = $mapper->map($name, $headers, self::categories($headers, $data));
            $plans[] = ['tab' => $name, 'headers' => $headers, 'rows' => $data] + $mapping;
        }
        usort($plans, static fn ($a, $b) => self::ORDER[$a['kind']] <=> self::ORDER[$b['kind']]);

        // 2. Dati demo via, se richiesto, poi indici di quello che c'e'
        $removed = $removeDemo ? $this->import->removeDemoData() : [];
        $this->loadIndexes();

        // 3. Importazione, scheda per scheda
        $report = [];
        $totals = ['nuovi' => 0, 'aggiornati' => 0, 'invariati' => 0, 'scartati' => 0];
        foreach ($plans as $plan) {
            $counts = ['nuovi' => 0, 'aggiornati' => 0, 'invariati' => 0, 'scartati' => 0];
            if ($plan['kind'] !== 'skip') {
                foreach ($plan['rows'] as $row) {
                    $record = self::record($plan['headers'], $row, $plan['columns'], $plan['values']);
                    $outcome = match ($plan['kind']) {
                        'leads', 'leads_raw' => $this->importLead($record, $plan['tab'], $plan['kind'] === 'leads'),
                        'cases' => $this->importCase($record),
                        'refunds' => $this->importRefund($record),
                        'documents' => $this->importDocument($record),
                        default => 'scartati',
                    };
                    $counts[$outcome]++;
                }
            }
            foreach ($counts as $key => $value) {
                $totals[$key] += $value;
            }
            $report[] = [
                'tab' => $plan['tab'],
                'kind' => self::KIND_LABELS[$plan['kind']],
                'by' => $plan['by'],
                'rows' => count($plan['rows']),
                'columns' => array_filter($plan['columns'], static fn ($role) => $role !== null),
            ] + $counts;
        }

        return ['removed' => $removed, 'tabs' => $report, 'totals' => $totals];
    }

    // ------------------------------------------------------------- contatti

    private function importLead(array $r, string $tab, bool $master): string
    {
        [$first, $last] = self::names($r);
        $email = self::email($r['email'] ?? '');
        $phone = self::phone($r['phone'] ?? '');
        $id = trim($r['submission_id'] ?? '');
        if (($first === '' && $last === '' && $email === '' && $phone === '' && $id === '') || preg_match(self::TEST_ROW, "{$first} {$last} {$email}")) {
            return 'scartati';
        }

        $need = $this->value($r, 'need', TabMapper::NEEDS);
        $stage = $this->value($r, 'outcome', TabMapper::STAGES) ?? $this->value($r, 'lead_status', TabMapper::STAGES);
        $route = self::route($r['departure'] ?? '', $r['arrival'] ?? '');
        $notes = self::notes($r, ['notes', 'other', 'flight_number', 'layover', 'delay', 'assignee', 'lead_status', 'outcome', 'need']);
        $fields = array_filter([
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => $phone,
            'original_submission_id' => $id,
            'source_channel' => mb_substr(mb_strtolower(trim($r['source'] ?? '')) ?: mb_strtolower($tab), 0, 50),
            'disservice_type' => $need,
            'flight_date' => self::date($r['flight_date'] ?? '', 'Y-m-d'),
            'flight_route' => $route,
            'next_follow_up_at' => self::date($r['next_call'] ?? '', 'Y-m-d'),
            'assigned_user_id' => $this->userId($r['assignee'] ?? ''),
            'notes' => $notes,
        ], static fn ($v) => $v !== null && $v !== '');
        $created = self::date($r['created_at'] ?? '', 'Y-m-d H:i:s') ?? self::jotformDate($id);

        // Contatti: solo l'ID invio dice che e' la stessa riga. Due invii della
        // stessa persona restano due contatti (doppioni da rivedere, mai uniti
        // in automatico: lo chiede il progetto).
        $existing = $id !== '' ? ($this->leadIndex['id'][$id] ?? null) : null;
        if ($existing !== null) {
            $dated = false;
            if ($created !== null && isset($this->undated[$existing])) {
                $this->import->setCreatedAt('leads', $existing, $created);
                $this->leadData[$existing]['created_at'] = $created;
                unset($this->undated[$existing]);
                $dated = true;
            }
            // Il registro principale aggiorna; i moduli grezzi completano solo i campi vuoti.
            $current = $this->leadData[$existing];
            $update = $master ? $fields : array_filter($fields, static fn ($v, $k) => empty($current[$k]), ARRAY_FILTER_USE_BOTH);
            if ($master && $stage !== null) {
                $update['stage'] = $stage;
            }
            if ($update === [] || array_intersect_assoc(array_map('strval', $update), array_map('strval', $current)) === array_map('strval', $update)) {
                return $dated ? 'aggiornati' : 'invariati';
            }
            $this->leads->update($existing, $update);
            $this->leadData[$existing] = $update + $current;

            return 'aggiornati';
        }

        $leadId = $this->leads->insert($fields + ['stage' => $stage ?? 'nuovo', 'email' => $email]);
        if ($created !== null) {
            $this->import->setCreatedAt('leads', $leadId, $created);
        } else {
            $this->undated[$leadId] = true;
        }
        $this->remember($leadId, $fields + ['email' => $email] + ($created !== null ? ['created_at' => $created] : []));

        return 'nuovi';
    }

    // ------------------------------------------------------------- pratiche

    private function importCase(array $r): string
    {
        [$first, $last] = self::names($r);
        $email = self::email($r['email'] ?? '');
        $phone = self::phone($r['phone'] ?? '');
        $id = trim($r['submission_id'] ?? '');
        if ($first === '' && $last === '' && $email === '' && $phone === '' && $id === '') {
            return 'scartati';
        }
        $leadId = $this->findLead($id, $email, $phone, $first, $last);
        if ($leadId === null) {
            $leadId = $this->leads->insert(array_filter(['first_name' => $first, 'last_name' => $last, 'phone' => $phone, 'original_submission_id' => $id, 'source_channel' => 'gestione legale'], 'strlen') + ['email' => $email, 'stage' => 'pratica avviata']);
            $this->remember($leadId, ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $phone, 'original_submission_id' => $id]);
        } else {
            $this->leads->update($leadId, ['stage' => 'pratica avviata']);
        }
        $lead = $this->leadData[$leadId];

        $stage = match (true) {
            trim($r['litigation'] ?? '') !== '' => 'contenzioso',
            trim($r['conciliation_outcome'] ?? '') !== '' => 'conciliazione conclusa',
            trim($r['conciliation'] ?? '') !== '' => 'in conciliazione',
            (bool) preg_match('/inviat|\d{4}-\d{2}-\d{2}|\d{1,2}[\/.-]\d{1,2}/i', $r['formal_notice'] ?? '') => 'diffida inviata',
            trim($r['formal_notice'] ?? '') !== '' => 'diffida da inviare',
            default => 'raccolta documenti',
        };
        $need = $lead['disservice_type'] ?? null;
        $fields = [
            'title' => ($need ? ucfirst($need) . ' · ' : 'Pratica · ') . trim(($lead['first_name'] ?? $first) . ' ' . ($lead['last_name'] ?? $last)),
            'category' => $need,
            'stage' => $stage,
            'lead_id' => $leadId,
            'primary_customer_id' => $this->customerFor($lead),
            'notes' => self::notes($r, ['documents', 'power_of_attorney', 'formal_notice', 'conciliation', 'conciliation_outcome', 'litigation', 'payment', 'client_payment', 'invoice', 'notes', 'other']),
        ];

        if (isset($this->caseByLead[$leadId])) {
            $this->cases->update($this->caseByLead[$leadId], array_filter($fields, static fn ($v) => $v !== null && $v !== ''));

            return 'aggiornati';
        }
        $caseId = $this->cases->insert(array_filter($fields, static fn ($v) => $v !== null && $v !== '') + ['opened_at' => $lead['created_at'] ?? date('Y-m-d H:i:s')]);
        $this->caseByLead[$leadId] = $caseId;

        return 'nuovi';
    }

    // ------------------------------------------------------------- rimborsi

    private function importRefund(array $r): string
    {
        [$first, $last] = self::names($r);
        $email = self::email($r['email'] ?? '');
        $amount = self::amount($r['amount'] ?? '');
        if ($amount === null || ($first === '' && $last === '' && $email === '')) {
            return 'scartati';
        }
        $date = self::date($r['date'] ?? '', 'Y-m-d H:i:s') ?? date('Y-m-d H:i:s');
        $leadId = $this->findLead(trim($r['submission_id'] ?? ''), $email, self::phone($r['phone'] ?? ''), $first, $last, true);
        if ($leadId === null) {
            $leadId = $this->leads->insert(array_filter(['first_name' => $first, 'last_name' => $last, 'source_channel' => 'rimborsi accettati'], 'strlen') + ['email' => $email, 'stage' => 'pratica avviata']);
            $this->import->setCreatedAt('leads', $leadId, $date);
            $this->remember($leadId, ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'created_at' => $date]);
        }
        $caseId = $this->caseByLead[$leadId] ?? null;
        if ($caseId === null) {
            $lead = $this->leadData[$leadId];
            $caseId = $this->cases->insert([
                'title' => 'Rimborso · ' . trim(($lead['first_name'] ?? $first) . ' ' . ($lead['last_name'] ?? $last)),
                'category' => $lead['disservice_type'] ?? null,
                'stage' => 'rimborsato',
                'lead_id' => $leadId,
                'primary_customer_id' => $this->customerFor($lead),
                'opened_at' => $lead['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
            $this->caseByLead[$leadId] = $caseId;
        }
        $key = $caseId . '|' . $amount . '|' . substr($date, 0, 10);
        if (isset($this->refundKeys[$key])) {
            return 'invariati';
        }
        $refundId = $this->refunds->insert([
            'case_id' => $caseId,
            'amount_claimed' => $amount,
            'amount_accepted' => $amount,
            'payment_status' => 'non pagato',
            'notes' => self::notes($r, ['notes', 'other']) ?: 'Rimborso accettato (dal foglio "Rimborsi accettati"): stato del pagamento da verificare.',
        ]);
        $this->import->setCreatedAt('refunds', $refundId, $date);
        $this->refundKeys[$key] = true;

        return 'nuovi';
    }

    // ------------------------------------------------------------- documenti

    private function importDocument(array $r): string
    {
        [$first, $last] = self::names($r);
        $email = self::email($r['email'] ?? '');
        $phone = self::phone($r['phone'] ?? '');
        if (($first === '' && $last === '' && $email === '' && $phone === '') || preg_match(self::TEST_ROW, "{$first} {$last} {$email}")) {
            return 'scartati';
        }
        $date = self::date($r['created_at'] ?? '', 'Y-m-d H:i:s') ?? date('Y-m-d H:i:s');
        $files = array_filter([
            'documento identita' => self::links($r['id_document'] ?? ''),
            "carta d'imbarco" => self::links($r['ticket'] ?? ''),
            'ricevute' => self::links($r['receipts'] ?? ''),
        ]);
        $leadId = $this->findLead(trim($r['submission_id'] ?? ''), $email, $phone, $first, $last);
        if ($leadId === null) {
            $leadId = $this->leads->insert(array_filter(['first_name' => $first, 'last_name' => $last, 'phone' => $phone, 'source_channel' => 'registrazione documenti', 'notes' => trim($r['description'] ?? '')], 'strlen') + ['email' => $email, 'stage' => 'documenti richiesti']);
            $this->import->setCreatedAt('leads', $leadId, $date);
            $this->remember($leadId, ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $phone, 'created_at' => $date]);
        }

        $caseId = $this->caseByLead[$leadId] ?? null;
        if ($caseId === null) {
            // Senza pratica i documenti restano annotati sul contatto.
            $summary = 'Documenti caricati il ' . date('d/m/Y', strtotime($date)) . ': '
                . implode(', ', array_map(static fn ($type, $links) => $type . ' (' . count($links) . ')', array_keys($files), $files));
            $current = (string) ($this->leadData[$leadId]['notes'] ?? '');
            if (str_contains($current, $summary)) {
                return 'invariati';
            }
            $notes = trim($current . "\n" . $summary);
            $this->leads->update($leadId, ['notes' => $notes]);
            $this->leadData[$leadId]['notes'] = $notes;

            return 'aggiornati';
        }

        $created = 0;
        foreach ($files as $type => $links) {
            $key = $caseId . '|' . $type . '|' . substr($date, 0, 10);
            if (isset($this->documentKeys[$key])) {
                continue;
            }
            $this->documents->insert([
                'case_id' => $caseId,
                'document_type' => $type,
                'completeness_status' => 'completo',
                'requested_at' => $date,
                'notes' => implode("\n", $links),
            ]);
            $this->documentKeys[$key] = true;
            $created++;
        }

        return $created > 0 ? 'nuovi' : 'invariati';
    }

    // ------------------------------------------------------------- abbinamenti

    private function loadIndexes(): void
    {
        foreach ($this->import->leadsIndex() as $row) {
            $this->remember((int) $row['id'], $row);
        }
        foreach ($this->import->customersIndex() as $row) {
            $this->indexCustomer((int) $row['id'], $row);
        }
        $this->caseByLead = $this->import->casesByLead();
        $this->refundKeys = $this->import->refundKeys();
        $this->documentKeys = $this->import->documentKeys();
        $this->users = $this->import->users();
    }

    private function remember(int $id, array $row): void
    {
        $this->leadData[$id] = array_merge($this->leadData[$id] ?? [], array_filter($row, static fn ($v) => $v !== null && $v !== ''));
        if (!empty($row['original_submission_id'])) {
            $this->leadIndex['id'][trim((string) $row['original_submission_id'])] = $id;
        }
        if (!empty($row['email'])) {
            $this->leadIndex['email'][mb_strtolower(trim((string) $row['email']))] ??= $id;
        }
        foreach (['phone', 'mobile_phone'] as $field) {
            $key = self::phoneKey((string) ($row[$field] ?? ''));
            if ($key !== '') {
                $this->leadIndex['phone'][$key] ??= $id;
            }
        }
        $name = self::nameKey((string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? ''));
        if ($name !== '') {
            $this->leadIndex['name'][$name] ??= $id;
        }
    }

    private function findLead(string $id, string $email, string $phone, string $first, string $last, bool $nameFirst = false): ?int
    {
        $byName = $this->leadIndex['name'][self::nameKey($first, $last)] ?? null;
        if ($id !== '' && isset($this->leadIndex['id'][$id])) {
            return $this->leadIndex['id'][$id];
        }
        // Rimborsi: la stessa email puo' essere di un familiare (vedi il foglio), prima il nome.
        if ($nameFirst && $byName !== null) {
            return $byName;
        }
        if ($email !== '' && isset($this->leadIndex['email'][mb_strtolower($email)])) {
            return $this->leadIndex['email'][mb_strtolower($email)];
        }
        $phoneKey = self::phoneKey($phone);
        if ($phoneKey !== '' && isset($this->leadIndex['phone'][$phoneKey])) {
            return $this->leadIndex['phone'][$phoneKey];
        }

        return $byName;
    }

    private function customerFor(array $lead): int
    {
        $email = mb_strtolower(trim((string) ($lead['email'] ?? '')));
        $phone = self::phoneKey((string) ($lead['phone'] ?? ''));
        $name = self::nameKey((string) ($lead['first_name'] ?? ''), (string) ($lead['last_name'] ?? ''));
        foreach ([['email', $email], ['phone', $phone], ['name', $name]] as [$type, $key]) {
            if ($key !== '' && isset($this->customerIndex[$type][$key])) {
                return $this->customerIndex[$type][$key];
            }
        }
        $data = ['first_name' => (string) ($lead['first_name'] ?? ''), 'last_name' => (string) ($lead['last_name'] ?? ''), 'email' => (string) ($lead['email'] ?? ''), 'phone' => (string) ($lead['phone'] ?? '')];
        $id = $this->customers->insert(array_filter($data, 'strlen') + ['email' => '']);
        $this->indexCustomer($id, $data);

        return $id;
    }

    private function indexCustomer(int $id, array $row): void
    {
        $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
        if ($email !== '') {
            $this->customerIndex['email'][$email] ??= $id;
        }
        $phone = self::phoneKey((string) ($row['phone'] ?? ''));
        if ($phone !== '') {
            $this->customerIndex['phone'][$phone] ??= $id;
        }
        $name = self::nameKey((string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? ''));
        if ($name !== '') {
            $this->customerIndex['name'][$name] ??= $id;
        }
    }

    private function userId(string $assignee): ?int
    {
        $assignee = mb_strtolower(trim($assignee));
        if ($assignee === '') {
            return null;
        }
        foreach ($this->users as $user) {
            foreach ([$user['first_name'], $user['last_name'], $user['username'], $user['first_name'] . ' ' . $user['last_name']] as $candidate) {
                if ($candidate !== '' && mb_strtolower((string) $candidate) === $assignee) {
                    return (int) $user['id'];
                }
            }
        }

        return null;
    }

    private function value(array $r, string $role, array $allowed): ?string
    {
        $raw = trim((string) ($r[$role] ?? ''));
        if ($raw === '') {
            return null;
        }
        $mapped = $r['@values'][$role][mb_strtolower($raw)] ?? null;
        if ($mapped !== null) {
            return $mapped;
        }
        $lower = mb_strtolower($raw);
        foreach ($allowed as $value) {
            if (str_contains($lower, $value)) {
                return $value;
            }
        }
        if ($role === 'need') {
            return match (true) {
                str_contains($lower, 'cancell') => 'cancellazione',
                str_contains($lower, 'ritard') => 'ritardo',
                str_contains($lower, 'bagagl') => 'bagaglio',
                str_contains($lower, 'imbarco') || str_contains($lower, 'overbooking') => 'negato imbarco',
                str_contains($lower, 'richiam') => 'richiamo',
                default => 'altro',
            };
        }

        return null;
    }

    // ------------------------------------------------------------- pulizia

    /** @return array{0: list<string>, 1: list<list<string>>} intestazioni e righe non vuote */
    private static function table(array $rows): array
    {
        foreach (array_slice($rows, 0, 6, true) as $i => $row) {
            $filled = array_filter($row, static fn ($c) => trim((string) $c) !== '');
            if (count($filled) >= 2) {
                $headers = array_map(static fn ($h) => trim((string) $h), $row);
                $data = [];
                foreach (array_slice($rows, $i + 1) as $line) {
                    if (array_filter($line, static fn ($c) => trim((string) $c) !== '') !== []) {
                        $data[] = $line;
                    }
                }

                return [$headers, $data];
            }
        }

        return [[], []];
    }

    /** Valori ricorrenti per colonna (pochi valori diversi, niente dati personali). */
    private static function categories(array $headers, array $rows): array
    {
        $result = [];
        foreach ($headers as $i => $header) {
            if ($header === '' || str_starts_with($header, 'sk-')) {
                continue;
            }
            $values = [];
            foreach ($rows as $row) {
                $value = trim((string) ($row[$i] ?? ''));
                if ($value !== '') {
                    $values[mb_strtolower($value)] = mb_substr($value, 0, 60);
                }
                if (count($values) > 25) {
                    continue 2;
                }
            }
            $values = array_filter($values, static fn ($v) => !preg_match('/@|https?:|\d{6,}/', $v));
            if ($values !== [] && count($rows) >= 5) {
                $result[$header] = array_values($values);
            }
        }

        return $result;
    }

    private static function record(array $headers, array $row, array $columns, array $values): array
    {
        $record = ['@values' => $values, '@labels' => []];
        foreach ($headers as $i => $header) {
            $role = $columns[$header] ?? null;
            $value = trim((string) ($row[$i] ?? ''));
            if ($role === null || $value === '') {
                continue;
            }
            if ($role === 'other' || isset($record[$role])) {
                $record['other'] = trim(($record['other'] ?? '') . "\n" . $header . ': ' . $value);
                continue;
            }
            $record[$role] = $value;
            $record['@labels'][$role] = $header;
        }

        return $record;
    }

    private static function notes(array $r, array $roles): ?string
    {
        $lines = [];
        foreach ($roles as $role) {
            $value = trim((string) ($r[$role] ?? ''));
            if ($value === '') {
                continue;
            }
            $lines[] = $role === 'other' || $role === 'notes' ? $value : ($r['@labels'][$role] ?? $role) . ': ' . $value;
        }

        return $lines === [] ? null : mb_substr(implode("\n", $lines), 0, 4000);
    }

    /** @return array{0: string, 1: string} */
    private static function names(array $r): array
    {
        $first = self::cleanName($r['first_name'] ?? '');
        $last = self::cleanName($r['last_name'] ?? '');
        if ($first === '' && $last === '' && !empty($r['full_name'])) {
            $parts = preg_split('/\s+/', self::cleanName($r['full_name'])) ?: [];
            $first = (string) array_shift($parts);
            $last = implode(' ', $parts);
        }

        return [mb_substr($first, 0, 100), mb_substr($last, 0, 100)];
    }

    private static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name !== '' && (mb_strtoupper($name) === $name || mb_strtolower($name) === $name)) {
            $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
        }

        return $name;
    }

    private static function email(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? mb_substr($value, 0, 190) : '';
    }

    private static function phone(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/\.0$/', '', $value) ?? $value;
        $plus = str_starts_with(ltrim($value, '( '), '+') || str_starts_with($value, '00');
        $digits = preg_replace('/\D/', '', $value) ?? '';
        if (strlen($digits) < 6) {
            return '';
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return mb_substr(($plus ? '+' : '') . $digits, 0, 30);
    }

    private static function phoneKey(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return strlen($digits) >= 6 ? substr($digits, -9) : '';
    }

    private static function nameKey(string $first, string $last): string
    {
        $key = mb_strtolower(trim($first . ' ' . $last));
        $key = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $key);
        $key = preg_replace('/[^a-z ]/', '', $key) ?? '';

        return strlen(str_replace(' ', '', $key)) >= 5 && str_contains(trim($key), ' ') ? trim(preg_replace('/\s+/', ' ', $key) ?? '') : '';
    }

    private static function date(string $value, string $format): ?string
    {
        $value = trim(preg_replace('/\.\d+$/', '', trim($value)) ?? '');
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd-m-Y H:i', 'd-m-Y H:i:s', 'd-m-Y', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'd.m.Y', 'j/n/Y', 'd/m/y'] as $pattern) {
            $date = \DateTime::createFromFormat('!' . $pattern, $value);
            if ($date !== false && $date->format('Y') >= 2000 && $date->format('Y') <= 2100) {
                return $date->format($format);
            }
        }

        return null;
    }

    private static function amount(string $value): ?float
    {
        $value = str_replace(['€', ' '], '', trim($value));
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $value)) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /** "LICC/CTA: Catania-Fontanarossa Airport, Italy, Catania" + arrivo -> "CTA-BLQ" */
    private static function route(string $from, string $to): ?string
    {
        $code = static function (string $airport): string {
            $airport = trim($airport);
            if (preg_match('/^[A-Z0-9]{3,4}\/([A-Z0-9]{3})\b/', $airport, $m)) {
                return $m[1];
            }

            return mb_convert_case(mb_strtolower(trim(explode(',', $airport)[0])), MB_CASE_TITLE);
        };
        $a = $code($from);
        $b = $code($to);
        if ($a === '' && $b === '') {
            return null;
        }

        return mb_substr(trim($a . '-' . $b, '-'), 0, 150);
    }

    /**
     * Data di invio ricavata da un ID invio di Jotform (19 cifre: le prime
     * 10 sono decimi di secondo da un'origine fissa). Verificata sul foglio di
     * Assilevi: coincide con "Data ingresso" entro pochi secondi. Ripiego per
     * le righe senza data.
     */
    private static function jotformDate(string $id): ?string
    {
        if (preg_match('/^\d{19}$/', $id) !== 1) {
            return null;
        }
        $time = intdiv((int) substr($id, 0, 10), 10) + 1124190801;

        return $time > strtotime('2015-01-01') && $time < time() + 86400 ? date('Y-m-d H:i:s', $time) : null;
    }

    /** @return string[] link a file (uno per riga nel foglio) */
    private static function links(string $value): array
    {
        preg_match_all('#https?://\S+#', $value, $m);

        return $m[0];
    }
}
