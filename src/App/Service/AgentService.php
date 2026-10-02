<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;
use App\Model\Agent;
use App\Model\AgentMessage;
use App\Model\Lead;
use App\Repository\AgentMessageRepository;
use App\Repository\AgentRepository;
use App\Repository\CaseFileRepository;
use App\Repository\CommunicationContentRepository;
use App\Repository\CommunicationRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\LeadRepository;
use App\Repository\RefundRepository;

/**
 * Agenti "colleghi digitali" - AMBIENTE DIMOSTRATIVO: nessun agente chiama
 * Claude. Ogni agente guarda dati veri (contatti fermi, documenti
 * mancanti, rimborsi non pagati...) e scrive all'operatore un messaggio
 * dal testo preparato; sostituire il testo con una risposta di Claude e'
 * l'unica cosa da cambiare per renderli veri (vedi i metodi gen*).
 *
 * Quello che governa DAVVERO il comportamento: 'is_enabled' (spento = non
 * produce messaggi) e 'autonomy':
 *  - suggest:  il messaggio propone, senza pulsante di azione;
 *  - approval: il messaggio ha il pulsante e l'azione parte all'approvazione;
 *  - auto:     l'azione parte subito e il messaggio racconta cosa e' successo.
 * 'schedule' e 'rule_text' sono salvati ma non pilotano nulla.
 *
 * Le azioni (perform()) scrivono davvero sul database (una comunicazione
 * "inviata", una classificazione), ma NON mandano nulla fuori.
 */
final class AgentService
{
    public const SCHEDULES = [
        'hourly' => 'Ogni ora',
        'daily_09' => 'Ogni mattina alle 9',
        'weekly_mon' => 'Ogni lunedi\'',
        'on_event' => 'Appena arriva una novita\'',
    ];

    public const AUTONOMIES = ['suggest', 'approval', 'auto'];

    private const DEFINITIONS = [
        'lead_intake' => [
            'name' => 'Sofia', 'role' => 'Lead Intake e Data Quality', 'schedule' => 'on_event', 'autonomy' => 'suggest',
            'bio' => 'Tiene in ordine i contatti in arrivo: segnala i duplicati e le pratiche dello stesso passeggero, senza mai cancellare nulla.',
            'rule' => 'Segnala due contatti con la stessa email, lo stesso telefono o lo stesso volo.',
        ],
        'qualification' => [
            'name' => 'Marco', 'role' => 'Qualificazione', 'schedule' => 'daily_09', 'autonomy' => 'approval',
            'bio' => 'Legge la richiesta e propone che tipo di disservizio e\' (ritardo, cancellazione, bagaglio...). La decisione finale e\' tua.',
            'rule' => 'Per ogni nuovo contatto senza classificazione, proponi il tipo di disservizio.',
        ],
        'follow_up' => [
            'name' => 'Marta', 'role' => 'Follow-up commerciale', 'schedule' => 'daily_09', 'autonomy' => 'approval',
            'bio' => 'Non lascia cadere nessun contatto: se qualcuno e\' fermo da troppo, prepara il messaggio per richiamarlo.',
            'rule' => 'Se un contatto e\' fermo da piu\' di 48 ore, prepara un messaggio WhatsApp.',
        ],
        'documents' => [
            'name' => 'Luca', 'role' => 'Document Manager', 'schedule' => 'hourly', 'autonomy' => 'approval',
            'bio' => 'Controlla i documenti di ogni pratica e scrive solo i solleciti che servono, senza chiedere due volte la stessa cosa.',
            'rule' => 'Se manca un documento a una pratica, scrivi al cliente chiedendo solo quello.',
        ],
        'legal' => [
            'name' => 'Elena', 'role' => 'Assistente legale', 'schedule' => 'daily_09', 'autonomy' => 'suggest',
            'bio' => 'Prepara cronologia e bozze di reclamo o diffida per le pratiche ferme. Non invia mai nulla: le decisioni legali restano a voi.',
            'rule' => 'Se un reclamo e\' partito e il vettore non risponde, prepara la bozza di diffida.',
        ],
        'conciliaweb' => [
            'name' => 'Davide', 'role' => 'ConciliaWeb Assistant', 'schedule' => 'daily_09', 'autonomy' => 'suggest',
            'bio' => 'Tiene d\'occhio le pratiche in conciliazione e ti riassume cosa e\' fermo e cosa richiede una risposta.',
            'rule' => 'Riassumi ogni mattina le pratiche in conciliazione e segnala quelle ferme.',
        ],
        'administration' => [
            'name' => 'Chiara', 'role' => 'Amministrazione e rimborsi', 'schedule' => 'daily_09', 'autonomy' => 'suggest',
            'bio' => 'Segue la parte economica: rimborsi accettati ma non ancora pagati, importi che non tornano. Non muove mai denaro.',
            'rule' => 'Segnala i rimborsi accettati che non risultano pagati.',
        ],
        'reporter' => [
            'name' => 'Giulia', 'role' => 'Management Reporter', 'schedule' => 'daily_09', 'autonomy' => 'suggest',
            'bio' => 'Ogni giorno ti manda il riepilogo di quello che e\' successo: nuovi contatti, documenti, rimborsi.',
            'rule' => 'Ogni mattina, riassumi la situazione per il management.',
        ],
    ];

    private const DISSERVICES = ['cancellazione', 'ritardo', 'negato imbarco', 'bagaglio', 'spese documentate'];

    private AgentRepository $agents;
    private AgentMessageRepository $messages;
    private LeadRepository $leads;
    private CaseFileRepository $cases;
    private DocumentRequestRepository $documents;
    private RefundRepository $refunds;
    private CommunicationRepository $communications;
    private CommunicationContentRepository $communicationContents;

    public function __construct(Container $container)
    {
        $this->agents = $container->get(AgentRepository::class);
        $this->messages = $container->get(AgentMessageRepository::class);
        $this->leads = $container->get(LeadRepository::class);
        $this->cases = $container->get(CaseFileRepository::class);
        $this->documents = $container->get(DocumentRequestRepository::class);
        $this->refunds = $container->get(RefundRepository::class);
        $this->communications = $container->get(CommunicationRepository::class);
        $this->communicationContents = $container->get(CommunicationContentRepository::class);
    }

    /** @return Agent[] */
    public function agents(): array
    {
        $this->ensureSeeded();

        return $this->agents->findAll([], 'id ASC');
    }

    /**
     * @return list<array<string, mixed>> dal piu' recente; un messaggio approvato resta dov'e', cosi' se ne vede l'esito
     */
    public function feed(int $limit = 8): array
    {
        $this->ensureSeeded();

        $byId = [];
        foreach ($this->agents->findAll() as $agent) {
            $byId[$agent->id] = $agent;
        }

        $items = [];
        foreach ($this->messages->findRecent(40) as $message) {
            $agent = $byId[$message->agentId] ?? null;
            if ($agent === null || $message->state === 'dismissed') {
                continue;
            }
            $items[] = [
                'id' => $message->id,
                'agentCode' => $agent->code,
                'agentName' => $agent->name,
                'agentRole' => $agent->role,
                'body' => $message->body,
                'detail' => $message->detail,
                'state' => $message->state,
                'actionType' => $message->actionType,
                'actionLabel' => $message->actionLabel,
                'linkHref' => $message->linkHref,
                'resultText' => $message->resultText,
                'createdAt' => $message->createdAt,
            ];
        }

        usort($items, static fn (array $a, array $b) => strcmp((string) $b['createdAt'], (string) $a['createdAt']));

        return array_slice($items, 0, $limit);
    }

    public function save(int $agentId, array $input): ?Agent
    {
        $agent = $this->agents->find($agentId);
        if ($agent === null) {
            return null;
        }

        $data = [];
        if (isset($input['is_enabled'])) {
            $data['is_enabled'] = $input['is_enabled'] ? 1 : 0;
        }
        if (isset($input['schedule']) && isset(self::SCHEDULES[$input['schedule']])) {
            $data['schedule'] = $input['schedule'];
        }
        if (isset($input['autonomy']) && in_array($input['autonomy'], self::AUTONOMIES, true)) {
            $data['autonomy'] = $input['autonomy'];
        }
        if (array_key_exists('rule_text', $input)) {
            $data['rule_text'] = trim((string) $input['rule_text']) ?: null;
        }

        if ($data !== []) {
            $this->agents->update($agentId, $data);
        }

        return $this->agents->find($agentId);
    }

    /** @return int quanti messaggi nuovi ha prodotto */
    public function run(int $agentId, bool $spreadInTime = false): int
    {
        $agent = $this->agents->find($agentId);
        if ($agent === null || !$agent->isEnabled) {
            return 0;
        }

        $generator = 'gen' . str_replace(' ', '', ucwords(str_replace('_', ' ', (string) $agent->code)));
        $created = 0;

        foreach ($this->$generator() as $item) {
            if ($this->messages->count(['dedupe_key' => $item['key']]) > 0) {
                continue;
            }

            $autonomy = (string) $agent->autonomy;
            $action = $item['action'] ?? null;
            $row = [
                'agent_id' => $agent->id,
                'dedupe_key' => $item['key'],
                'body' => $item['body'],
                'done_body' => $item['doneBody'] ?? null,
                'detail' => $item['detail'] ?? null,
                'link_href' => $item['link'] ?? null,
                'entity_type' => $item['entityType'] ?? null,
                'entity_id' => $item['entityId'] ?? null,
                'action_type' => null,
                'action_label' => null,
                'state' => 'pending',
                'result_text' => null,
            ];

            if ($action !== null && $autonomy === 'approval') {
                $row['action_type'] = $action;
                $row['action_label'] = $item['actionLabel'];
            } elseif ($action !== null && $autonomy === 'auto') {
                $row['body'] = $item['doneBody'];
                $row['action_type'] = $action;
                $row['state'] = 'done';
                $row['result_text'] = $this->perform($action, $item['entityType'] ?? null, (int) ($item['entityId'] ?? 0), $item['detail'] ?? null, $item['payload'] ?? null);
            }

            $id = $this->messages->insert($row);
            if ($spreadInTime) {
                $this->messages->update($id, ['created_at' => date('Y-m-d H:i:s', time() - random_int(8, 540) * 60)]);
            }
            $created++;
        }

        $this->agents->update($agent->id, ['last_run_at' => date('Y-m-d H:i:s')]);

        return $created;
    }

    /** @return int messaggi nuovi complessivi */
    public function runAll(bool $spreadInTime = false): int
    {
        $total = 0;
        foreach ($this->agents->findAll([], 'id ASC') as $agent) {
            $total += $this->run((int) $agent->id, $spreadInTime);
        }

        return $total;
    }

    /** Svuota il flusso e lo ripopola: serve a rifare la demo da capo. */
    public function restart(): int
    {
        foreach ($this->messages->findAll() as $message) {
            $this->messages->delete((int) $message->id);
        }

        return $this->runAll(true);
    }

    /** @return string|null il testo di esito, null se il messaggio non esiste o non e' approvabile */
    public function approve(int $messageId): ?string
    {
        $message = $this->messages->find($messageId);
        if ($message === null || $message->state !== 'pending' || $message->actionType === null) {
            return null;
        }

        $result = $this->perform($message->actionType, $message->entityType, (int) $message->entityId, $message->detail, null);
        $this->messages->update($messageId, ['state' => 'done', 'result_text' => $result, 'body' => $message->doneBody ?? $message->body]);

        return $result;
    }

    public function dismiss(int $messageId): bool
    {
        $message = $this->messages->find($messageId);
        if ($message === null) {
            return false;
        }
        $this->messages->update($messageId, ['state' => 'dismissed']);

        return true;
    }

    private function ensureSeeded(): void
    {
        if ($this->agents->count() > 0) {
            return;
        }

        foreach (self::DEFINITIONS as $code => $def) {
            $this->agents->insert([
                'code' => $code,
                'name' => $def['name'],
                'role' => $def['role'],
                'bio' => $def['bio'],
                'is_enabled' => 1,
                'schedule' => $def['schedule'],
                'autonomy' => $def['autonomy'],
                'rule_text' => $def['rule'],
            ]);
        }

        $this->runAll(true);
    }

    /**
     * Le uniche scritture "vere" degli agenti: lasciano traccia nel
     * gestionale (una comunicazione registrata come inviata, una
     * classificazione), ma non mandano nulla fuori da qui.
     */
    private function perform(string $action, ?string $entityType, int $entityId, ?string $detail, ?string $payload): string
    {
        if ($action === 'classify_lead') {
            $this->leads->update($entityId, ['disservice_type' => $payload]);

            return "Classificazione «{$payload}» salvata sul contatto.";
        }

        $channel = $action === 'send_whatsapp' ? 'whatsapp' : 'email';
        $subject = $action === 'document_reminder' ? 'Sollecito documenti mancanti' : 'Aggiornamento sulla sua richiesta';

        $communicationId = $this->communications->insert([
            'entity_type' => (string) $entityType,
            'entity_id' => (string) $entityId,
            'channel' => $channel,
            'direction' => 'out',
            'delivery_status' => 'inviato',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
        $this->communicationContents->insert([
            'communication_id' => (string) $communicationId,
            'subject' => $channel === 'whatsapp' ? '' : $subject,
            'body' => (string) $detail,
        ]);

        return ($channel === 'whatsapp' ? 'Messaggio WhatsApp registrato come inviato' : 'Email registrata come inviata') . ': lo trovi in Comunicazioni.';
    }

    private function name(Lead $lead): string
    {
        return trim("{$lead->firstName} {$lead->lastName}") ?: 'il contatto';
    }

    /** @return list<array<string, mixed>> */
    private function genLeadIntake(): array
    {
        $items = [];
        foreach ($this->leads->findDuplicateCandidates(2) as $dup) {
            $items[] = [
                'key' => "dup:{$dup['aId']}:{$dup['bId']}",
                'body' => "Ho trovato un possibile duplicato: {$dup['bLabel']} sembra la stessa persona di {$dup['aLabel']} ({$dup['reason']}). Non ho unito nulla, decidi tu.",
                'link' => '/contatti/duplicati/' . $dup['bId'],
            ];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function genQualification(): array
    {
        $items = [];
        foreach ($this->leads->findUnclassified(2) as $lead) {
            // Proposta dimostrativa: stabile per contatto, non letta da una vera richiesta.
            $type = self::DISSERVICES[(int) $lead->id % count(self::DISSERVICES)];
            $who = $this->name($lead);
            $items[] = [
                'key' => 'classify:' . $lead->id,
                'body' => "Per {$who} propongo di classificare la richiesta come «{$type}». Mi confermi?",
                'doneBody' => "Ho classificato la richiesta di {$who} come «{$type}».",
                'action' => 'classify_lead',
                'actionLabel' => "Conferma «{$type}»",
                'payload' => $type,
                'link' => '/contatti/' . $lead->id,
                'entityType' => 'leads',
                'entityId' => (int) $lead->id,
            ];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function genFollowUp(): array
    {
        $items = [];
        foreach ($this->leads->findStale(date('Y-m-d H:i:s', strtotime('-48 hours')), 3) as $lead) {
            $who = $this->name($lead);
            $hours = (int) floor((time() - strtotime((string) ($lead->lastContactedAt ?? $lead->createdAt))) / 3600);
            $first = $lead->firstName ?: 'buongiorno';
            $items[] = [
                'key' => 'stale:' . $lead->id,
                'body' => "{$who} e' fermo da {$hours} ore e nessuno l'ha ricontattato. Ho preparato un messaggio WhatsApp: lo mando?",
                'doneBody' => "{$who} era fermo da {$hours} ore: gli ho mandato un messaggio WhatsApp per riprendere il contatto.",
                'detail' => "Buongiorno {$first}, siamo " . APP_NAME . ". Abbiamo ricevuto la sua richiesta di assistenza e vorremmo aiutarla a ottenere il rimborso che le spetta. Quando possiamo sentirla per un minuto? Grazie!",
                'action' => 'send_whatsapp',
                'actionLabel' => 'Approva e invia',
                'link' => '/contatti/' . $lead->id,
                'entityType' => 'leads',
                'entityId' => (int) $lead->id,
            ];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function genDocuments(): array
    {
        $items = [];
        foreach ($this->documents->findMissingWithCase(2) as $doc) {
            $items[] = [
                'key' => 'doc:' . $doc['id'],
                'body' => "Alla pratica «{$doc['caseTitle']}» manca ancora: {$doc['documentType']}. Ho scritto il sollecito al cliente, chiedendo solo questo.",
                'doneBody' => "Alla pratica «{$doc['caseTitle']}» mancava: {$doc['documentType']}. Ho gia' mandato il sollecito al cliente.",
                'detail' => "Buongiorno, per proseguire con la sua pratica ci manca ancora: {$doc['documentType']}. Puo' inviarcelo rispondendo a questo messaggio? Grazie, " . APP_NAME . ".",
                'action' => 'document_reminder',
                'actionLabel' => 'Approva e invia',
                'link' => '/documenti-richiesti/' . $doc['id'],
                'entityType' => 'cases',
                'entityId' => $doc['caseId'],
            ];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function genLegal(): array
    {
        $items = [];
        foreach (array_slice($this->cases->findAll(['stage' => 'reclamo inviato'], 'opened_at ASC'), 0, 1) as $case) {
            $items[] = [
                'key' => 'legal:' . $case->id,
                'body' => "Per la pratica «{$case->title}» il reclamo e' partito e non risulta risposta. Ho preparato cronologia e bozza di diffida: guardale prima di decidere, io non invio nulla.",
                'detail' => "Oggetto: Diffida ad adempiere\n\nCon riferimento al reclamo gia' inviato, e in assenza di riscontro, si diffida la compagnia a corrispondere quanto dovuto entro 15 giorni, in difetto saremo costretti ad avviare la procedura di conciliazione.",
                'link' => '/pratiche/' . $case->id,
            ];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function genConciliaweb(): array
    {
        $cases = $this->cases->findAll(['stage' => 'in conciliazione'], 'opened_at ASC');
        if ($cases === []) {
            return [];
        }

        $oldest = $cases[0];
        $days = (int) floor((time() - strtotime((string) $oldest->openedAt)) / 86400);

        return [[
            'key' => 'conc:' . date('Y-m-d'),
            'body' => 'Ho controllato le ' . count($cases) . " pratiche in conciliazione. La piu' vecchia e' «{$oldest->title}», aperta da {$days} giorni: conviene guardarla per prima.",
            'link' => '/pratiche/' . $oldest->id,
        ]];
    }

    /** @return list<array<string, mixed>> */
    private function genAdministration(): array
    {
        $items = [];
        foreach (['non pagato', 'parziale'] as $status) {
            foreach ($this->refunds->findAll(['payment_status' => $status]) as $refund) {
                $case = $refund->caseId !== null ? $this->cases->find((int) $refund->caseId) : null;
                $title = $case?->title ?? 'una pratica';
                $amount = number_format((float) $refund->amountAccepted, 0, ',', '.');
                $state = $status === 'parziale' ? 'pagato solo in parte' : 'non ancora pagato';
                $items[] = [
                    'key' => 'refund:' . $refund->id,
                    'body' => "Il rimborso di {$amount} € per «{$title}» e' accettato ma risulta {$state}. Vale la pena un controllo con l'amministrazione.",
                    'link' => '/rimborsi/' . $refund->id,
                ];
            }
        }

        return array_slice($items, 0, 2);
    }

    /** @return list<array<string, mixed>> */
    private function genReporter(): array
    {
        $newLeads = $this->leads->countCreatedSince(date('Y-m-d H:i:s', strtotime('-7 days')));
        $missing = $this->documents->count(['completeness_status' => 'mancante']);
        $amount = number_format($this->refunds->sumAcceptedAmount(), 0, ',', '.');

        return [[
            'key' => 'report:' . date('Y-m-d'),
            'body' => "Riepilogo di oggi: {$newLeads} nuovi contatti negli ultimi 7 giorni, {$missing} documenti ancora mancanti, {$amount} € di rimborsi accettati.",
            'link' => '/report',
        ]];
    }
}
