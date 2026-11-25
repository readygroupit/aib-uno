<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Assistant\ActionPlanService;
use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;

/**
 * Una richiesta con piu' cose da fare: l'IA scrive i passi, qui si
 * risolvono i nomi e si mostra il piano da confermare (ActionPlanService).
 */
final class PlanActionsTool implements PromptToolInterface, PromptConversationalInterface
{
    private ?array $lastPlan = null;

    public function __construct(private readonly Container $container)
    {
    }

    public function name(): string
    {
        return 'plan_actions';
    }

    public function description(): string
    {
        return "Per richieste che chiedono di FARE una o piu' cose insieme sui clienti e sui colleghi: registrare un incontro o una telefonata, "
            . "segnare un'idea, una decisione o una preferenza del cliente (anche nella roadmap del progetto), coinvolgere o avvisare colleghi "
            . "(scritti come @nome.cognome o per nome), mettere qualcuno nella squadra del cliente, creare attivita'. Scrivi TUTTI i passi: "
            . "l'operatore vede il piano e lo conferma prima che si faccia qualcosa. Esempio: \"sono stato con @alessio da Della Rocca, gli e' "
            . "piaciuta l'idea del bollino, mettila nella roadmap condivisa e coinvolgi @valentina\" = log_contact (incontro, people alessio) + "
            . "add_note (idea, roadmap true, roadmap_status 'piaciuta al cliente', share_with_team true, people valentina). Non usarlo per cercare o mostrare dati.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string', 'description' => "In una frase, cosa farai (in italiano, seconda persona: 'Registro l'incontro...')."],
                'steps' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'action' => ['type' => 'string', 'enum' => ActionPlanService::ACTIONS, 'description' => "add_note: idea/decisione/preferenza/nota nel diario del cliente (roadmap se e' un'idea da portare avanti). log_contact: un incontro, una call, un messaggio con il cliente. notify: avvisare colleghi. add_to_team: mettere colleghi nella squadra del cliente. create_task: un'attivita' da fare."],
                            'customer' => ['type' => 'string', 'description' => 'Nome del cliente come detto (es. "della rocca").'],
                            'project' => ['type' => 'string', 'description' => 'Titolo del progetto se nominato; vuoto = il progetto aperto del cliente, se ce n\'e\' uno.'],
                            'title' => ['type' => 'string', 'description' => "Titolo breve (es. 'Il bollino'). Per create_task cosa fare."],
                            'body' => ['type' => 'string', 'description' => 'I dettagli, con le parole dell\'operatore.'],
                            'kind' => ['type' => 'string', 'enum' => ['idea', 'decisione', 'preferenza', 'nota']],
                            'roadmap' => ['type' => 'boolean', 'description' => 'Va nella roadmap interna del progetto.'],
                            'roadmap_status' => ['type' => 'string', 'enum' => ['da valutare', 'piaciuta al cliente', 'approvata', 'in corso', 'fatta']],
                            'share_with_team' => ['type' => 'boolean', 'description' => 'Avvisa la squadra del cliente e del progetto ("condividi con tutti i collaboratori").'],
                            'people' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Colleghi coinvolti o da avvisare (es. "alessio.scicolone", "Valentina").'],
                            'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD: quando e\' successo (contatto) o entro quando (attivita\').'],
                            'channel' => ['type' => 'string', 'enum' => ['incontro', 'telefono', 'whatsapp', 'email', 'videochiamata']],
                            'mood' => ['type' => 'string', 'enum' => ['contento', 'neutro', 'freddo', 'scontento'], 'description' => 'Com\'era il cliente.'],
                            'next_step' => ['type' => 'string'],
                            'next_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                            'role' => ['type' => 'string', 'enum' => ['referente', 'social', 'grafica', 'video', 'sviluppo', 'adv', 'strategia', 'altro']],
                            'message' => ['type' => 'string', 'description' => 'Per notify: cosa dire ai colleghi.'],
                        ],
                        'required' => ['action'],
                    ],
                ],
            ],
            'required' => ['summary', 'steps'],
        ];
    }

    public function menuLabel(): ?string
    {
        return null;
    }

    public function menuSection(): ?string
    {
        return null;
    }

    public function menuCount(): ?string
    {
        return null;
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function triggers(): array
    {
        return [];
    }

    public function execute(array $input): array
    {
        $service = $this->container->get(ActionPlanService::class);
        $plan = $service->prepare(trim((string) ($input['summary'] ?? '')), is_array($input['steps'] ?? null) ? $input['steps'] : []);
        $this->lastPlan = $plan;

        return [[
            'type' => 'action-plan',
            'summary' => $plan['summary'],
            'steps' => array_map(static fn (array $s) => ['text' => $service->describe($s), 'action' => $s['action']], $plan['steps']),
            'problems' => $plan['problems'],
            'confirmAction' => $plan['steps'] !== [] ? '/prompt/piano/' . $plan['id'] . '/esegui' : null,
            'cancelAction' => '/prompt/piano/' . $plan['id'] . '/annulla',
        ]];
    }

    public function replySummary(array $input): ?string
    {
        if ($this->lastPlan === null || $this->lastPlan['steps'] === []) {
            return "Non riesco a preparare il piano: guarda cosa manca qui sotto.";
        }

        return 'Ecco cosa faccio. Confermi?';
    }

    public function followUpSuggestions(): array
    {
        return [];
    }
}
