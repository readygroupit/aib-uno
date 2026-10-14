<?php

declare(strict_types=1);

namespace App\Analysis;

use App\Core\Container;
use App\Repository\AnalysisRepository;
use App\Service\AiService;
use App\Service\LoggerService;

/**
 * Analisi libera: quando nessuna schermata pronta risponde alla richiesta,
 * l'IA (1) decide quali dati servono con un linguaggio di interrogazione
 * controllato (mai SQL libero: lo traduce AnalysisRepository sul catalogo
 * ammesso), (2) riceve i risultati e compone la pagina con i mattoncini
 * dell'interfaccia secondo regole di gerarchia ed estetica (PageComposer).
 * Due chiamate all'IA per richiesta.
 */
final class AnalysisService
{
    private const MAX_QUERIES = 8;

    private const PLAN_RULES = <<<'TXT'
Sei l'analista dei dati di un gestionale. Ricevi una domanda dell'utente e la mappa delle tabelle che puoi interrogare. Decidi quali interrogazioni servono per rispondere bene, anche a domande insolite: incroci, confronti tra periodi, raggruppamenti, classifiche, totali.

Rispondi con un oggetto JSON:
{"title": "titolo breve della risposta", "queries": [ ... ]}
oppure, solo se la domanda e' incomprensibile o i dati non esistono proprio:
{"clarify": "domanda breve all'utente"}

Ogni interrogazione (massimo 8) e' un oggetto:
- "id": nome breve (es. "q_mese_2026")
- "purpose": a cosa serve, in poche parole
- "table": una tabella della mappa
- per un AGGREGATO:
  - "measure": {"fn": "count"} oppure {"fn": "sum"|"avg"|"min"|"max"|"count_distinct", "field": "campo"}
  - "groupBy" (facoltativo, massimo 2): [{"field": "campo"}] oppure per le date [{"field": "created_at", "by": "day"|"week"|"month"|"quarter"|"year"|"weekday"|"hour"}]. Un campo "collegato a" raggruppato restituisce gia' il nome dell'elemento collegato (es. created_by restituisce il nome dell'utente). Nei campi scrivi solo il nome del campo, es. "created_by".
  - "order": "value_desc" (default) | "value_asc" | "group_asc" | "group_desc"
- per un ELENCO di righe: "select": ["campo", ...] (massimo 8; i campi collegati diventano nomi), "orderBy": {"field": "campo", "dir": "desc"|"asc"}
- "where" (facoltativo): [{"field": "campo", "op": "="|"!="|">"|">="|"<"|"<="|"in"|"not_in"|"contains"|"is_null"|"not_null", "value": ..., "part": "year"|"month"|"day"|"weekday"|"hour"|"date" (facoltativo, applica la condizione a una parte della data)}]
- "period" (facoltativo): {"field": "campo data", "from": "AAAA-MM-GG", "to": "AAAA-MM-GG"} (estremi inclusi)
- "limit": massimo righe (default 50, massimo 200)

Percorsi tra tabelle: in qualunque punto dove va un campo puoi seguire i collegamenti con il punto, partendo dalla tabella dell'interrogazione: "case_id.lead_id.airline" = compagnia aerea del contatto della pratica del rimborso. Ogni passo intermedio deve essere un campo "collegato a". Usali per incroci tra tabelle (rimborsi per compagnia, pratiche per citta' del contatto, documenti per responsabile della pratica...).

Regole:
- Usa solo tabelle e campi della mappa, con i valori esatti indicati in "valori".
- Le righe eliminate sono gia' escluse.
- "Operato" o attivita' di un utente = record creati da lui (created_by) nelle tabelle di lavoro: contatti (leads), pratiche (cases), documenti richiesti (document_requests), comunicazioni (communications), rimborsi (refunds), attivita' (tasks). Non contare agenti, messaggi degli agenti o clienti. Una interrogazione per tabella raggruppata per created_by.
- Per confronti con gli stessi mesi di anni passati, filtra con "where" sul mese ("part": "month") e raggruppa per anno ("by": "year"): gli anni senza righe valgono 0.
- Per confrontare periodi (mesi, anni, settimane) usa una interrogazione per periodo con "period", oppure raggruppa per data con "by".
- Chiedi sempre anche i totali che servono a dare il dato principale e i dati per un confronto (periodo precedente) quando ha senso.
- Le date relative ("questo mese", "la settimana scorsa") si calcolano dalla data di oggi indicata.
TXT;

    private const COMPOSE_RULES = <<<'TXT'
Sei il designer delle risposte di un gestionale con un'interfaccia elegante. Ricevi la domanda, i risultati delle interrogazioni e componi la pagina che risponde, con i blocchi qui sotto. La pagina deve essere utile e d'impatto: chi la apre deve capire in 3 secondi la risposta, leggendo nell'ordine giusto.

Rispondi con un oggetto JSON:
{"reply": "una frase che risponde alla domanda col dato principale", "suggestions": ["2-3 domande di approfondimento brevi"], "blocks": [ ... ]}

Blocchi disponibili:
- {"type": "header", "eyebrow": "contesto breve (es. 'Operato utenti · ottobre')", "title": "titolo della risposta"} - sempre il primo.
- {"type": "kpis", "items": [{"label": "max 3 parole", "value": "valore gia' formattato", "note": "max 6 parole: cosa misura o il confronto", "badge": "variazione breve, es. '+12% vs 2025' (facoltativo)", "feature": true|false, "trend": [numeri in ordine di tempo, facoltativo], "color": "petrol"|"teal"|"moss"|"lilac"|"brass"}]} - da 2 a 6 contatori.
- {"type": "chart", "mode": "rows"|"columns", "title": "...", "subtitle": "...", "unit": "€"|"%"|"", "categories": ["..."], "series": [{"name": "2026", "values": [numeri]}], "highlight": indice della serie principale} - "rows" per confrontare categorie (es. utenti, compagnie), "columns" per l'andamento nel tempo (giorni, mesi). Fino a 4 serie (es. un anno per serie).
- {"type": "breakdown", "title": "...", "subtitle": "...", "groups": [{"title": "...", "counts": {"etichetta": numero}, "tones": {"etichetta": "success"|"warning"|"danger"|"info"|"neutral"}, "highlight": {"text": "dato chiave breve", "tone": "success"|"warning"|"danger"|"info"}}]} - ripartizioni per stato/categoria con anello; fino a 3 gruppi affiancati, massimo 8 voci per gruppo. "tones" (facoltativo) colora le voci per significato: success = buono/fatto, warning = in corso/attenzione, danger = problema, info = neutro avviato.
- {"type": "table", "title": "...", "columns": [{"key": "chiave", "label": "Intestazione"}], "rows": [{"chiave": "valore"}]} - dettaglio nominativo, massimo 15 righe e 5 colonne.
- {"type": "insight", "title": "In sintesi", "points": ["1-3 osservazioni di una frase"]} - cosa notare: picchi, cali, chi spicca, cosa fare.
- {"type": "group", "eyebrow": "...", "title": "...", "subtitle": "...", "blocks": [chart|breakdown|table|kpis]} - tiene insieme 2-3 elementi dello stesso tema, affiancati.

Regole di composizione (obbligatorie):
1. Ordine di lettura: header -> kpis -> visualizzazione principale (chart o breakdown) -> dettagli (group, table) -> insight.
2. Il dato piu' importante va nel primo kpi con "feature": true (card scura in evidenza). Una sola feature per pagina. Gli altri kpi sono di supporto e coerenti tra loro.
3. Se c'e' un confronto (periodi, persone), mostralo: badge nel kpi principale e un chart con una serie per periodo, la piu' recente in "highlight".
4. Scegli la visualizzazione dalla forma dei dati: andamento nel tempo -> chart columns; confronto tra elementi -> chart rows (ordinati dal maggiore); parti di un totale per stato -> breakdown; elenco di persone o record -> table.
5. Elementi affini (es. due ripartizioni, grafico e tabella dello stesso tema) vanno in un "group" con un titolo che li unisce.
6. Pagina compatta: da 3 a 7 blocchi in totale. Niente blocchi vuoti o ripetuti.
7. Numeri: solo quelli presenti nei risultati o calcolati da essi (somme, differenze, percentuali, tassi di conversione: calcolali tu quando aiutano), mai inventati o stimati; se un dato manca, non mostrarlo. Formato italiano: 1.234, 12,5%, 1.234,50 € per gli importi.
8. Testi in italiano, brevi e concreti, senza gergo tecnico (niente nomi di tabelle o campi).
9. Se i risultati sono vuoti o a zero, dillo nella reply e nell'insight, e mostra comunque i kpi con 0.
10. Una tabella solo se ha almeno 3 righe utili; con meno dati usa i kpi. Celle brevi: un numero o poche parole, mai frasi o note; massimo 5 colonne, l'ultima e' di solito il totale.
11. Nei grafici ordina dal valore maggiore e metti "non indicato" sempre per ultimo.
12. Un periodo richiesto che non compare nei risultati vale 0: mostralo come 0 (anche nei grafici, come serie a zero), non come "dato non disponibile".
13. Dati per persona o elemento con piu' periodi o misure: prima un chart rows (una serie per periodo, la piu' recente in highlight), poi la tabella di dettaglio.
14. "reply": una frase semplice con il dato principale, senza parole tecniche (niente "query", "metriche", "dataset").
15. "suggestions": domande che l'utente puo' fare al gestionale sui suoi dati (es. "Chi ha aperto piu' pratiche questo mese?"), mai proposte tecniche o domande su come elaborare i dati.
TXT;

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @return array{components: list<array>, reply: string, suggestions: string[]}
     */
    public function answer(string $question): array
    {
        @set_time_limit(180);
        $ai = $this->container->get(AiService::class);
        $catalog = $this->container->get(DataCatalog::class);
        $today = date('Y-m-d') . ' (' . ['domenica', 'lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato'][(int) date('w')] . ')';

        $plan = $ai->json(
            self::PLAN_RULES,
            "Data di oggi: {$today}\n\nMappa delle tabelle:\n" . $catalog->describe() . "\n\nDomanda: {$question}",
            6000
        );
        if (!empty($plan['clarify'])) {
            return ['components' => [], 'reply' => mb_substr((string) $plan['clarify'], 0, 300), 'suggestions' => []];
        }

        $queries = (array) ($plan['queries'] ?? []);
        $results = $this->execute($queries);
        $this->log('plan', ['question' => $question, 'plan' => $plan, 'results' => $results]);

        // Un solo giro di correzione: l'IA riceve gli errori e rifa' solo le
        // interrogazioni fallite (una chiamata in piu', solo quando serve).
        $failed = array_values(array_filter($results, static fn (array $r) => isset($r['error'])));
        if ($failed !== []) {
            $repair = $ai->json(
                self::PLAN_RULES,
                "Data di oggi: {$today}\n\nMappa delle tabelle:\n" . $catalog->describe() . "\n\nDomanda: {$question}"
                    . "\n\nAlcune interrogazioni sono fallite. Correggi SOLO queste, con gli stessi id, e rispondi con {\"queries\": [...]}."
                    . "\nInterrogazioni:\n" . json_encode(array_values(array_filter($queries, static fn ($q) => in_array($q['id'] ?? '', array_column($failed, 'id'), true))), JSON_UNESCAPED_UNICODE)
                    . "\nErrori:\n" . json_encode($failed, JSON_UNESCAPED_UNICODE),
                4000
            );
            $fixed = [];
            foreach ($this->execute((array) ($repair['queries'] ?? [])) as $result) {
                $fixed[$result['id']] = $result;
            }
            foreach ($results as $i => $result) {
                if (isset($result['error'], $fixed[$result['id']])) {
                    $results[$i] = $fixed[$result['id']];
                }
            }
            $this->log('repair', ['repair' => $repair, 'results' => $results]);
        }

        $layout = $ai->json(
            self::COMPOSE_RULES,
            "Data di oggi: {$today}\nDomanda: {$question}\nTitolo proposto: " . (string) ($plan['title'] ?? '')
                . "\n\nRisultati (JSON):\n" . json_encode($results, JSON_UNESCAPED_UNICODE),
            8000
        );
        $this->log('layout', ['layout' => $layout]);

        $components = $this->container->get(PageComposer::class)->compose((array) ($layout['blocks'] ?? []));

        return [
            'components' => $components,
            'reply' => mb_substr(trim((string) ($layout['reply'] ?? '')), 0, 300),
            'suggestions' => array_values(array_map(static fn ($s) => mb_substr((string) $s, 0, 90), array_slice(array_filter((array) ($layout['suggestions'] ?? []), 'is_string'), 0, 3))),
        ];
    }

    /**
     * Esegue le interrogazioni del piano. Una interrogazione non valida non
     * ferma le altre: il suo errore arriva all'IA insieme ai risultati.
     *
     * @return list<array>
     */
    public function execute(array $queries): array
    {
        $catalog = $this->container->get(DataCatalog::class);
        $tables = $catalog->tables();
        $repository = $this->container->get(AnalysisRepository::class);
        $results = [];

        foreach (array_slice(array_filter($queries, 'is_array'), 0, self::MAX_QUERIES) as $index => $query) {
            $id = (string) ($query['id'] ?? 'q' . ($index + 1));
            $table = (string) ($query['table'] ?? '');
            try {
                if (!$catalog->has($table)) {
                    throw new AnalysisException("Tabella non disponibile: {$table}");
                }
                $data = $repository->run($query, $table, $tables[$table], $tables);
                $results[] = ['id' => $id, 'purpose' => (string) ($query['purpose'] ?? ''), 'table' => $tables[$table]['label'], 'columns' => $data['columns'], 'rows' => $data['rows']];
            } catch (\Throwable $e) {
                $results[] = ['id' => $id, 'purpose' => (string) ($query['purpose'] ?? ''), 'error' => $e instanceof AnalysisException ? $e->getMessage() : 'interrogazione non eseguibile'];
            }
        }

        return $results;
    }

    private function log(string $step, array $data): void
    {
        $dir = ROOT_PATH . '/data/logs/' . date('Y-m-d');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/analysis.log', json_encode(['time' => date('H:i:s'), 'step' => $step] + $data, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    }
}
