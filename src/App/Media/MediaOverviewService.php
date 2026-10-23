<?php

declare(strict_types=1);

namespace App\Media;

use App\Core\Container;
use App\Model\MediaAsset;
use App\Repository\CustomerRepository;
use App\Repository\MediaAssetRepository;
use App\Support\ItalianFormat as F;

/** L'archivio da mostrare: i risultati come schede, la scheda di un file, lo stato dell'indice. */
final class MediaOverviewService
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /** Stato dell'archivio: origini, quanti file, quanti da descrivere, ultima lettura. */
    public function status(): array
    {
        $counts = $this->container->get(MediaAssetRepository::class)->countByStatus();
        $sources = $this->container->get(MediaLibraryService::class)->sources();
        $state = MediaLibraryService::state();

        return [
            'total' => array_sum($counts),
            'analyzed' => $counts['analizzato'] ?? 0,
            'pending' => $counts['da analizzare'] ?? 0,
            'failed' => $counts['errore'] ?? 0,
            'sources' => array_map(static fn (MediaSourceInterface $s) => $s->sourceCode() === 'google_drive' ? 'Google Drive' : 'cartella del server', $sources),
            'syncedAt' => $state['syncedAt'] !== null ? F::shortDate(substr($state['syncedAt'], 0, 10), date('Y-m-d')) . ' alle ' . substr($state['syncedAt'], 11, 5) : null,
        ];
    }

    /** @return list<array> i file come schede dei risultati */
    public function cards(array $items): array
    {
        $names = [];
        foreach ($this->container->get(CustomerRepository::class)->findAll() as $customer) {
            $names[(int) $customer->id] = $customer->displayName();
        }

        return array_map(fn (array $item) => $this->card($item['asset'], $item['reason'] ?? null, $names), $items);
    }

    public function card(MediaAsset $asset, ?string $reason, array $names): array
    {
        return [
            'id' => (int) $asset->id,
            'href' => '/materiali/' . $asset->id,
            'thumb' => $asset->thumbPath !== null ? '/materiali/' . $asset->id . '/anteprima?v=' . strtotime((string) ($asset->analyzedAt ?? 'now')) : null,
            'name' => (string) $asset->name,
            'kind' => (string) $asset->kind,
            'duration' => $asset->durationSeconds ? sprintf('%d:%02d', intdiv($asset->durationSeconds, 60), $asset->durationSeconds % 60) : null,
            'description' => $asset->description !== null ? mb_strimwidth($asset->description, 0, 160, "\u{2026}") : null,
            'reason' => $reason,
            'customer' => $asset->customerId !== null ? ($names[(int) $asset->customerId] ?? null) : null,
            'folder' => $asset->folder ?: null,
            'people' => $asset->peopleCount,
            'status' => (string) $asset->analysisStatus,
            'openLink' => $asset->webLink,
        ];
    }

    /** Scheda di un file: anteprima, descrizione, cosa contiene, i momenti del video. */
    public function detail(MediaAsset $asset): array
    {
        $data = $asset->analysisData();
        $customer = $asset->customerId !== null ? $this->container->get(CustomerRepository::class)->find((int) $asset->customerId) : null;
        $people = $data['persone'] ?? [];
        $facts = array_values(array_filter([
            $asset->peopleCount !== null ? ['label' => 'Persone', 'value' => $asset->peopleCount === 0 ? 'nessuna' : $asset->peopleCount . (!empty($people['volti_in_primo_piano']) ? ', volti in primo piano' : '')] : null,
            $asset->setting ? ['label' => 'Ambiente', 'value' => $asset->setting . (!empty($data['luogo']) ? ' · ' . $data['luogo'] : '')] : null,
            $asset->occasion ? ['label' => 'Occasione', 'value' => $asset->occasion] : null,
            !empty($data['periodo']) ? ['label' => 'Periodo', 'value' => (string) $data['periodo']] : null,
            !empty($data['atmosfera']) ? ['label' => 'Atmosfera', 'value' => (string) $data['atmosfera']] : null,
            !empty($data['colori']) ? ['label' => 'Colori', 'value' => implode(', ', (array) $data['colori'])] : null,
            !empty($data['testo_visibile']) ? ['label' => 'Testo visibile', 'value' => (string) $data['testo_visibile']] : null,
            !empty($data['marchi']) ? ['label' => 'Marchi', 'value' => implode(', ', (array) $data['marchi'])] : null,
            !empty($data['usi']) ? ['label' => 'Va bene per', 'value' => implode(', ', (array) $data['usi'])] : null,
            $asset->orientation ? ['label' => 'Formato', 'value' => $asset->orientation . ($asset->width ? " ({$asset->width}x{$asset->height})" : '')] : null,
            $asset->durationSeconds ? ['label' => 'Durata', 'value' => sprintf('%d:%02d', intdiv($asset->durationSeconds, 60), $asset->durationSeconds % 60)] : null,
        ]));

        return [
            'name' => (string) $asset->name,
            'kind' => (string) $asset->kind,
            'thumb' => $asset->thumbPath !== null ? '/materiali/' . $asset->id . '/anteprima?v=' . strtotime((string) ($asset->analyzedAt ?? 'now')) : null,
            'openLink' => $asset->webLink,
            'folder' => $asset->folder,
            'customer' => $customer?->displayName(),
            'customerHref' => $customer !== null ? '/clienti/' . $customer->id : null,
            'status' => (string) $asset->analysisStatus,
            'error' => $asset->analysisError,
            'description' => $asset->description,
            'tags' => array_values(array_filter(array_map('trim', explode(',', (string) $asset->tags)))),
            'facts' => $facts,
            'moments' => array_values(array_filter((array) ($data['momenti'] ?? []), 'is_array')),
            'analyzedAt' => $asset->analyzedAt !== null ? F::longDate(substr($asset->analyzedAt, 0, 10)) : null,
            'reanalyzeAction' => '/materiali/' . $asset->id . '/analizza',
        ];
    }
}
