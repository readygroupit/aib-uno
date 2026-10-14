<?php

declare(strict_types=1);

namespace App\Import;

use App\Core\Container;
use App\Service\AiService;

/**
 * Capisce cos'e' una scheda del foglio e a cosa corrisponde ogni colonna,
 * anche se le intestazioni cambiano: una chiamata all'IA per scheda con le
 * SOLE intestazioni e i valori ricorrenti delle colonne "a categorie"
 * (mai nomi, email o telefoni). Se l'IA non risponde, abbinamento per
 * parole chiave.
 */
final class TabMapper
{
    public const ROLES = [
        'leads' => ['submission_id', 'source', 'created_at', 'first_name', 'last_name', 'full_name', 'email', 'phone', 'need', 'flight_date', 'flight_number', 'departure', 'arrival', 'layover', 'delay', 'lead_status', 'assignee', 'next_call', 'outcome', 'notes'],
        'cases' => ['submission_id', 'first_name', 'last_name', 'full_name', 'email', 'phone', 'documents', 'power_of_attorney', 'formal_notice', 'conciliation', 'conciliation_outcome', 'litigation', 'payment', 'client_payment', 'invoice', 'notes'],
        'refunds' => ['submission_id', 'first_name', 'last_name', 'full_name', 'email', 'phone', 'amount', 'date', 'notes'],
        'documents' => ['submission_id', 'created_at', 'first_name', 'last_name', 'full_name', 'email', 'phone', 'id_document', 'ticket', 'receipts', 'description'],
    ];

    public const NEEDS = ['cancellazione', 'ritardo', 'negato imbarco', 'bagaglio', 'spese documentate', 'richiamo', 'altro'];
    public const STAGES = ['nuovo', 'da valutare', 'da richiamare', 'richiamato', 'whatsapp inviato', 'documenti richiesti', 'in lavorazione', 'pratica avviata', 'non idoneo', 'duplicato', 'non contattare', 'interessato'];

    private const RULES = <<<'TXT'
Abbini le schede di un foglio di lavoro (gestione reclami voli) alle tabelle di un gestionale. Ricevi il nome della scheda, le intestazioni e, per le colonne con pochi valori diversi, quei valori.

Rispondi con JSON:
{"kind": "leads"|"leads_raw"|"cases"|"refunds"|"documents"|"skip", "columns": {"intestazione esatta": "ruolo"}, "values": {"ruolo": {"valore del foglio": "valore del gestionale"}}}

Tipi di scheda:
- leads: registro principale dei contatti con stato/esito/assegnatario (es. "Gestione Lead")
- leads_raw: risposte grezze di un modulo (es. "Form responses", "Richiami"): stessi ruoli di leads
- cases: pratiche legali (diffida, conciliazione, contenzioso, pagamenti)
- refunds: rimborsi accettati con importo
- documents: documenti caricati dai clienti (foto documenti, biglietti, ricevute)
- skip: copie di backup, schede vuote o non pertinenti

Ruoli per tipo (usa solo questi; le colonne senza ruolo adatto: "other"; colonne inutili come termini e condizioni o date di aggiornamento: null):
- leads/leads_raw: submission_id (ID invio / Submission ID), source (fonte), created_at (data ingresso / submission date), first_name, last_name, full_name (nome e cognome insieme), email, phone, need (esigenza / tipo di disservizio), flight_date, flight_number, departure (partenza), arrival (arrivo / aeroporto di atterraggio), layover (scali), delay (ore di ritardo), lead_status (stato lead), assignee (assegnatario), next_call (prossimo richiamo), outcome (esito), notes
- cases: submission_id, first_name, last_name, full_name, email, phone, documents (documenti ricevuti), power_of_attorney (procura alle liti), formal_notice (diffida), conciliation, conciliation_outcome (esito conciliazione), litigation (contenzioso), payment (pagamento), client_payment (pagamento al cliente), invoice (fattura), notes
- refunds: submission_id, first_name, last_name, full_name, email, phone, amount (importo), date, notes
- documents: submission_id, created_at, first_name, last_name, full_name, email, phone, id_document (foto documenti), ticket (biglietti), receipts (ricevute spese), description (descrizione del problema)

Traduzione dei valori ("values"), solo se la colonna ha valori elencati:
- need -> uno tra: cancellazione | ritardo | negato imbarco | bagaglio | spese documentate | richiamo | altro (piu' disservizi insieme: il primo)
- lead_status e outcome -> uno tra: nuovo | da valutare | da richiamare | richiamato | whatsapp inviato | documenti richiesti | in lavorazione | pratica avviata | non idoneo | duplicato | non contattare | interessato
TXT;

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param string[] $headers
     * @param array<string, string[]> $categories intestazione => valori ricorrenti
     * @return array{kind: string, columns: array<string, ?string>, values: array<string, array<string, string>>, by: string}
     */
    public function map(string $tab, array $headers, array $categories): array
    {
        if (preg_match('/backup|copia|old|vecchi/i', $tab)) {
            return ['kind' => 'skip', 'columns' => [], 'values' => [], 'by' => 'nome scheda'];
        }

        try {
            $answer = $this->container->get(AiService::class)->json(
                self::RULES,
                "Scheda: {$tab}\nIntestazioni: " . json_encode(array_values($headers), JSON_UNESCAPED_UNICODE)
                    . "\nValori ricorrenti: " . json_encode($categories, JSON_UNESCAPED_UNICODE),
                3000
            );

            return $this->clean($answer, $headers) + ['by' => 'IA'];
        } catch (\Throwable) {
            return $this->guess($tab, $headers) + ['by' => 'parole chiave'];
        }
    }

    private function clean(array $answer, array $headers): array
    {
        $kind = in_array($answer['kind'] ?? '', ['leads', 'leads_raw', 'cases', 'refunds', 'documents', 'skip'], true) ? $answer['kind'] : 'skip';
        $roles = self::ROLES[$kind === 'leads_raw' ? 'leads' : $kind] ?? [];
        $columns = [];
        foreach ((array) ($answer['columns'] ?? []) as $header => $role) {
            if (in_array($header, $headers, true)) {
                $columns[$header] = in_array($role, $roles, true) || $role === 'other' ? $role : null;
            }
        }
        $values = [];
        foreach ((array) ($answer['values'] ?? []) as $role => $map) {
            $allowed = $role === 'need' ? self::NEEDS : (in_array($role, ['lead_status', 'outcome'], true) ? self::STAGES : null);
            foreach ((array) $map as $from => $to) {
                if (is_string($to) && ($allowed === null || in_array($to, $allowed, true))) {
                    $values[$role][mb_strtolower(trim((string) $from))] = $to;
                }
            }
        }

        return ['kind' => $kind, 'columns' => $columns, 'values' => $values];
    }

    /** Ripiego senza IA: tipo dal nome della scheda, ruoli da parole nelle intestazioni. */
    private function guess(string $tab, array $headers): array
    {
        $name = mb_strtolower($tab);
        $kind = match (true) {
            str_contains($name, 'legal') => 'cases',
            str_contains($name, 'rimbors') => 'refunds',
            str_contains($name, 'document') => 'documents',
            str_contains($name, 'gestione lead') => 'leads',
            str_contains($name, 'form') || str_contains($name, 'richiam') => 'leads_raw',
            default => 'skip',
        };
        $keywords = [
            'submission_id' => ['id invio', 'submission id'], 'created_at' => ['data ingresso', 'submission date'], 'source' => ['fonte'],
            'first_name' => ['nome'], 'last_name' => ['cognome'], 'email' => ['email', 'e-mail'], 'phone' => ['telefono'],
            'need' => ['esigenza', 'disservizio'], 'flight_date' => ['data volo', 'data di partenza'], 'flight_number' => ['numero volo', 'numero del tuo volo'],
            'departure' => ['partenza'], 'arrival' => ['arrivo', 'atterrato'], 'layover' => ['scali'], 'lead_status' => ['stato lead'],
            'assignee' => ['assegnatario'], 'next_call' => ['prossimo richiamo'], 'outcome' => ['esito'], 'notes' => ['note'],
            'formal_notice' => ['diffida'], 'conciliation' => ['conciliazione'], 'litigation' => ['contenzioso'], 'documents' => ['documenti ricevuti'],
            'amount' => ['rimborso', 'importo'], 'date' => ['data della mail', 'data'], 'id_document' => ['foto dei tuoi documenti'],
            'ticket' => ['biglietti'], 'receipts' => ['ricevute'], 'description' => ['descrivi'],
        ];
        $roles = self::ROLES[$kind === 'leads_raw' ? 'leads' : $kind] ?? [];
        $columns = [];
        foreach ($headers as $header) {
            $h = mb_strtolower($header);
            $columns[$header] = null;
            foreach ($keywords as $role => $words) {
                if (in_array($role, $roles, true) && !in_array($role, $columns, true)) {
                    foreach ($words as $word) {
                        if (str_contains($h, $word) && !($role === 'first_name' && str_contains($h, 'cognome'))) {
                            $columns[$header] = $role;
                            continue 3;
                        }
                    }
                }
            }
        }

        return ['kind' => $kind, 'columns' => $columns, 'values' => []];
    }
}
