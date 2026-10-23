<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Media\MediaOverviewService;
use App\Media\MediaSearchService;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Repository\MediaAssetRepository;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;
use App\View\Component\MediaLibraryComponent;

/**
 * Cercare foto e video a parole ("foto per una comunione senza persone"):
 * le descrizioni le ha gia' scritte l'IA, qui si leggono.
 */
final class ShowMediaLibraryTool implements PromptToolInterface, PromptConversationalInterface
{
    private MediaOverviewService $overview;
    private MediaSearchService $search;
    private MediaLibraryComponent $component;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->overview = $container->get(MediaOverviewService::class);
        $this->search = $container->get(MediaSearchService::class);
        $this->component = $container->get(MediaLibraryComponent::class);
    }

    public function name(): string
    {
        return 'search_media';
    }

    public function description(): string
    {
        return "Cerca foto e video nell'archivio dell'agenzia (Google Drive) leggendo le descrizioni fatte dall'IA: cosa si vede, persone si'/no, "
            . "occasione, luogo, cliente. 'query' e' la richiesta a parole (es. 'foto per una comunione senza persone', 'video del locale di Gino', "
            . "'immagini verticali di pizza'). Usalo per 'cercami foto', 'trova un video', 'immagini di repertorio', 'archivio'.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Cosa cercare, a parole'],
                'tipo' => ['type' => 'string', 'enum' => ['immagine', 'video']],
                'cliente' => ['type' => 'integer', 'description' => 'ID del cliente, se indicato'],
            ],
        ];
    }

    public function menuLabel(): ?string
    {
        return 'Archivio foto e video';
    }

    public function menuSection(): ?string
    {
        return 'contenuti';
    }

    public function menuCount(): ?string
    {
        $total = $this->overview->status()['total'];

        return $total > 0 ? (string) $total : null;
    }

    public function requiredPermission(): ?string
    {
        return 'media_library.view';
    }

    public function triggers(): array
    {
        return ['archivio', 'foto', 'video', 'cercami foto', 'trova un video', 'immagini di repertorio', 'materiali'];
    }

    public function execute(array $input): array
    {
        $query = trim((string) ($input['query'] ?? ''));
        $forced = array_filter([
            'kind' => in_array($input['tipo'] ?? null, ['immagine', 'video'], true) ? $input['tipo'] : null,
            'customerId' => !empty($input['cliente']) ? (int) $input['cliente'] : null,
            'noPeople' => !empty($input['senza_persone']),
        ]);
        $status = $this->overview->status();
        $canEdit = $this->container->get(AuthService::class)->hasPermission('media_library.edit');

        if ($query !== '' || $forced !== []) {
            $result = $this->search->search($query, $forced);
            $cards = $this->overview->cards($result['items']);
            $understood = $result['understood'];
        } else {
            // Senza ricerca: gli ultimi file arrivati.
            $latest = $this->container->get(MediaAssetRepository::class)->findAll([], 'modified_at DESC, id DESC');
            $cards = $this->overview->cards(array_map(static fn ($a) => ['asset' => $a, 'reason' => null], array_slice($latest, 0, 24)));
            $understood = null;
        }

        $actions = [];
        if ($canEdit && $status['sources'] !== []) {
            $actions[] = ['label' => 'Leggi i file nuovi', 'action' => '/materiali/sincronizza', 'variant' => 'secondary', 'icon' => 'arrows-rotate'];
            if ($status['pending'] > 0) {
                $actions[] = ['label' => 'Descrivi ora (' . min(10, $status['pending']) . ')', 'action' => '/materiali/descrivi', 'variant' => 'secondary', 'icon' => 'wand-magic-sparkles'];
            }
        }

        return [$this->component->toData([
            'url' => '/materiali' . ($query !== '' ? '?q=' . rawurlencode($query) : ''),
            'query' => $query,
            'filters' => $forced,
            'understood' => $understood,
            'cards' => $cards,
            'status' => $status,
            'actions' => $actions,
            'empty' => $status['total'] === 0
                ? ($status['sources'] === [] ? 'L\'archivio non e\' ancora collegato: collega Google Drive dalla Configurazione.' : 'Nessun file letto ancora: "Leggi i file nuovi".')
                : ($query !== '' ? 'Nessun file adatto. Prova con altre parole, o togli un filtro.' : null),
            'setupHref' => $status['sources'] === [] ? '/configurazione' : null,
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $query = trim((string) ($input['query'] ?? ''));
        if ($query === '') {
            $s = $this->overview->status();

            return F::plural($s['total'], 'file in archivio', 'file in archivio') . ($s['pending'] > 0 ? ', ' . $s['pending'] . ' ancora da descrivere' : '') . '.';
        }

        return 'Ecco cosa ho trovato per "' . $query . '": apri una scheda per i dettagli o il link al file.';
    }

    public function followUpSuggestions(): array
    {
        return ['Solo video', 'Foto senza persone', 'Piano editoriale'];
    }
}
