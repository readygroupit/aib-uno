<?php

declare(strict_types=1);

namespace App\Media;

use App\Connector\ConnectorRegistry;
use App\Core\Container;
use App\Model\Customer;
use App\Model\MediaAsset;
use App\Package\PackageCatalogService;
use App\Repository\CustomerRepository;
use App\Repository\MediaAssetRepository;

/**
 * L'archivio: legge le origini (Google Drive, cartella), tiene l'indice
 * aggiornato e fa descrivere dall'IA i file nuovi, a gruppi (dal job e da
 * "Descrivi ora").
 *
 * Un file cambiato si rianalizza; uno sparito dall'origine esce
 * dall'indice. Il cliente si deduce dalla cartella: una sottocartella che
 * si chiama come il cliente collega i suoi file.
 */
final class MediaLibraryService
{
    private MediaAssetRepository $assets;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->assets = $container->get(MediaAssetRepository::class);
    }

    /** @return MediaSourceInterface[] le origini pronte */
    public function sources(): array
    {
        $sources = [];
        if (in_array('connectors', PackageCatalogService::installedNames(), true)) {
            $registry = $this->container->get(ConnectorRegistry::class);
            $drive = $registry->has('google_drive') ? $registry->get('google_drive') : null;
            if ($drive instanceof MediaSourceInterface && $drive->isReady()) {
                $sources[] = $drive;
            }
        }
        $local = $this->container->get(LocalFolderSource::class);
        if ($local->isReady()) {
            $sources[] = $local;
        }

        return $sources;
    }

    public function source(string $code): ?MediaSourceInterface
    {
        foreach ($this->sources() as $source) {
            if ($source->sourceCode() === $code) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Allinea l'indice alle origini.
     *
     * @return array{added:int, changed:int, removed:int}
     */
    public function sync(): array
    {
        $outcome = ['added' => 0, 'changed' => 0, 'removed' => 0];
        $customers = $this->customerMatchers();
        foreach ($this->sources() as $source) {
            $seen = [];
            foreach ($source->listFiles() as $file) {
                $seen[$file['id']] = true;
                $existing = $this->assets->findByExternal($source->sourceCode(), $file['id']);
                $data = [
                    'name' => mb_substr($file['name'], 0, 255),
                    'mime_type' => $file['mime'],
                    'kind' => str_starts_with($file['mime'], 'video/') ? 'video' : 'immagine',
                    'folder' => mb_substr($file['folder'], 0, 500),
                    'web_link' => $file['webLink'],
                    'size_bytes' => $file['size'],
                    'modified_at' => $file['modifiedAt'],
                    'width' => $file['width'],
                    'height' => $file['height'],
                    'duration_seconds' => $file['duration'],
                ];
                if ($existing === null) {
                    $id = $this->assets->insert($data + [
                        'source' => $source->sourceCode(),
                        'external_id' => $file['id'],
                        'customer_id' => self::matchCustomer($file['folder'], $customers),
                        'analysis_status' => 'da analizzare',
                    ]);
                    if ($file['webLink'] === null) {
                        $this->assets->update($id, ['web_link' => '/materiali/' . $id . '/file']);
                    }
                    $outcome['added']++;
                    continue;
                }
                $changed = $file['modifiedAt'] !== null && $existing->modifiedAt !== null && $file['modifiedAt'] > $existing->modifiedAt;
                if ($changed || (int) $existing->status === 0) {
                    $this->assets->update((int) $existing->id, $data + ['status' => 1, 'analysis_status' => $changed ? 'da analizzare' : $existing->analysisStatus]);
                    $outcome['changed']++;
                }
            }
            // Sparito dall'origine: fuori dall'indice (resta nel database, spento).
            foreach ($this->assets->findAll(['source' => $source->sourceCode()]) as $asset) {
                if (!isset($seen[(string) $asset->externalId])) {
                    $this->assets->delete((int) $asset->id);
                    $outcome['removed']++;
                }
            }
        }
        self::saveState(['syncedAt' => date('Y-m-d H:i:s')] + $outcome);

        return $outcome;
    }

    /**
     * Descrive i file in attesa, al massimo $limit per volta (ogni file e'
     * una chiamata all'IA).
     *
     * @return array{done:int, failed:int, left:int}
     */
    public function analyzePending(int $limit): array
    {
        $outcome = ['done' => 0, 'failed' => 0];
        foreach ($this->assets->findPending($limit) as $asset) {
            $this->analyze($asset) ? $outcome['done']++ : $outcome['failed']++;
        }
        $outcome['left'] = $this->assets->countByStatus()['da analizzare'] ?? 0;

        return $outcome;
    }

    public function analyze(MediaAsset $asset): bool
    {
        $source = $this->source((string) $asset->source);
        if ($source === null) {
            $this->assets->update((int) $asset->id, ['analysis_status' => 'errore', 'analysis_error' => 'Origine non collegata.']);

            return false;
        }
        try {
            $result = $this->container->get(MediaAnalyzer::class)->analyze($asset, $source);
        } catch (\Throwable $e) {
            $this->assets->update((int) $asset->id, ['analysis_status' => 'errore', 'analysis_error' => mb_substr($e->getMessage(), 0, 255)]);

            return false;
        }
        $this->assets->update((int) $asset->id, [
            'analysis_status' => 'analizzato',
            'analysis_error' => null,
            'description' => $result['description'],
            'tags' => mb_substr($result['tags'], 0, 500),
            'people_count' => $result['people'],
            'setting' => $result['setting'],
            'occasion' => $result['occasion'],
            'orientation' => $result['orientation'],
            'analysis' => json_encode($result['analysis'], JSON_UNESCAPED_UNICODE),
            'thumb_path' => $result['thumb'],
            'width' => $result['width'],
            'height' => $result['height'],
            'duration_seconds' => $result['duration'],
            'analyzed_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /** @return array{syncedAt:?string, added?:int, changed?:int, removed?:int} */
    public static function state(): array
    {
        $file = ROOT_PATH . '/data/cache/media-sync.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? $data : ['syncedAt' => null];
    }

    private static function saveState(array $state): void
    {
        @file_put_contents(ROOT_PATH . '/data/cache/media-sync.json', json_encode($state));
    }

    /** @return array<int, string> cliente => nome normalizzato */
    private function customerMatchers(): array
    {
        $result = [];
        foreach ($this->container->get(CustomerRepository::class)->findAll() as $customer) {
            /** @var Customer $customer */
            $key = self::normalize($customer->displayName());
            if (strlen($key) >= 4) {
                $result[(int) $customer->id] = $key;
            }
        }

        return $result;
    }

    /** Il cliente della cartella: un pezzo del percorso che si chiama come lui (o lo contiene). */
    public static function matchCustomer(string $folder, array $customers): ?int
    {
        foreach (array_reverse(explode('/', $folder)) as $segment) {
            $segment = self::normalize($segment);
            if (strlen($segment) < 4) {
                continue;
            }
            foreach ($customers as $id => $name) {
                if ($segment === $name || str_contains($segment, $name) || (strlen($segment) >= 5 && str_contains($name, $segment))) {
                    return $id;
                }
            }
        }

        return null;
    }

    private static function normalize(string $text): string
    {
        $text = mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text);
        $text = preg_replace('/\b(srl|srls|spa|snc|sas|s\.r\.l\.)\b/', '', $text);

        return preg_replace('/[^a-z0-9]/', '', (string) $text);
    }
}
