<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Analysis\AnalysisService;
use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;

/**
 * Analisi libera (App\Analysis\AnalysisService): per tutte le domande sui
 * dati a cui nessuna schermata pronta risponde esattamente. L'IA interroga
 * i dati e compone la pagina con i mattoncini dell'interfaccia.
 */
final class AnalyzeDataTool implements PromptToolInterface, PromptConversationalInterface
{
    private string $lastReply = '';
    private array $lastSuggestions = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function name(): string
    {
        return 'analyze_data';
    }

    public function description(): string
    {
        return "Analizza i dati e costruisce una pagina su misura (numeri principali, grafici, ripartizioni, tabelle, "
            . "osservazioni). Usalo per qualunque domanda sui dati che nessun altro strumento copre esattamente: "
            . "confronti tra periodi o anni, classifiche, incroci tra tabelle, raggruppamenti, statistiche per "
            . "utente, compagnia, stato, mese, andamenti, totali particolari. Nel campo 'request' riporta la "
            . "richiesta dell'utente completa, con tutti i dettagli (periodi, confronti, raggruppamenti).";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'request' => ['type' => 'string', 'description' => 'La richiesta dell\'utente, completa e con tutti i dettagli.'],
            ],
            'required' => ['request'],
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
        return 'reports.view';
    }

    public function triggers(): array
    {
        return [];
    }

    public function execute(array $input): array
    {
        $request = trim((string) ($input['request'] ?? ''));
        if ($request === '') {
            $this->lastReply = 'Dimmi pure cosa vuoi analizzare: un periodo, un confronto, una classifica...';

            return [];
        }

        try {
            $answer = $this->container->get(AnalysisService::class)->answer($request);
        } catch (\Throwable $e) {
            $this->lastReply = 'Non sono riuscito a completare l\'analisi (' . $e->getMessage() . '). Prova a riformulare la richiesta.';

            return [];
        }

        $this->lastReply = $answer['reply'] !== '' ? $answer['reply'] : 'Ecco cosa ho trovato.';
        $this->lastSuggestions = $answer['suggestions'];

        return $answer['components'];
    }

    public function replySummary(array $input): ?string
    {
        return $this->lastReply !== '' ? $this->lastReply : null;
    }

    public function followUpSuggestions(): array
    {
        return $this->lastSuggestions;
    }
}
