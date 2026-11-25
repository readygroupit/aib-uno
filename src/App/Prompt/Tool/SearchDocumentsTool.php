<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Documents\DocumentService;
use App\Model\Document;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Repository\CustomerRepository;
use App\Support\ItalianFormat as F;

/**
 * Cerca tra i documenti dalle informazioni salvate nel gestionale (nome,
 * descrizione, parole chiave, cartella): veloce, senza chiedere al Drive; il
 * file si apre sul Drive.
 */
final class SearchDocumentsTool implements PromptToolInterface, PromptConversationalInterface
{
    private int $found = 0;

    public function __construct(private readonly Container $container)
    {
    }

    public function name(): string
    {
        return 'search_documents';
    }

    public function description(): string
    {
        return "Cerca file nei Documenti (sul Drive dell'agenzia): preventivi, contratti, bozze, loghi, esecutivi, materiale dei clienti. "
            . "Es. \"il preventivo firmato di Della Rocca\", \"le bozze del logo\". Per foto e video da usare nei contenuti c'e' l'archivio foto e video.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Le parole da cercare (senza il nome del cliente se lo metti in customer).'],
                'customer' => ['type' => 'string', 'description' => 'Il cliente, se nominato.'],
            ],
        ];
    }

    public function menuLabel(): ?string
    {
        return 'Documenti';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function menuCount(): ?string
    {
        return null;
    }

    public function requiredPermission(): ?string
    {
        return 'documents.view';
    }

    public function triggers(): array
    {
        return ['documenti', 'cartelle', 'drive'];
    }

    public function execute(array $input): array
    {
        $service = $this->container->get(DocumentService::class);
        $query = trim((string) ($input['query'] ?? ''));
        $customerName = trim((string) ($input['customer'] ?? ''));
        if ($query === '' && $customerName === '') {
            return [$service->folderView(null)];
        }
        $customerId = null;
        if ($customerName !== '') {
            $match = $this->container->get(CustomerRepository::class)->searchFreeText(['company_name', 'first_name', 'last_name'], $customerName, 1);
            $customerId = $match[0]->id ?? null;
        }
        $results = $service->search($query, $customerId !== null ? (int) $customerId : null);
        $this->found = count($results);
        $today = date('Y-m-d');

        return [[
            'type' => 'document-results',
            'query' => trim($query . ($customerName !== '' ? " ({$customerName})" : '')),
            'results' => array_map(static fn (Document $d) => [
                'name' => (string) $d->name,
                'href' => '/documenti/file/' . $d->id,
                'open' => $d->webLink,
                'path' => $service->pathOf($d),
                'description' => $d->description,
                'when' => $d->driveModifiedAt !== null ? F::relativeDays(substr((string) $d->driveModifiedAt, 0, 10), $today) : null,
                'kind' => DocumentService::kind((string) $d->mimeType),
            ], $results),
            'browseHref' => '/documenti',
        ]];
    }

    public function replySummary(array $input): ?string
    {
        return match ($this->found) {
            0 => 'Non ho trovato file con queste parole. Prova con altre, o guarda nelle cartelle.',
            1 => 'Ecco il file.',
            default => "Ho trovato {$this->found} file.",
        };
    }

    public function followUpSuggestions(): array
    {
        return ['documenti'];
    }
}
