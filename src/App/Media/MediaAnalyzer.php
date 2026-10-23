<?php

declare(strict_types=1);

namespace App\Media;

use App\Core\Container;
use App\Model\MediaAsset;
use App\Repository\CustomerRepository;
use App\Service\AiService;

/**
 * Guarda un file una volta e ne scrive la descrizione.
 *
 * - Immagine: si scarica, si riduce a 1280 px e la si da' all'IA.
 * - Video fino a 300 MB: si scarica e se ne estraggono alcuni fotogrammi
 *   (ffmpeg), uno ogni tanto, con il minuto: l'IA descrive il video e i
 *   momenti principali ("00:45 piano largo del locale"). Piu' grande: si
 *   usa l'anteprima dell'origine (un fotogramma solo).
 * - Sempre un'anteprima piccola in data/media-thumbs, per i risultati.
 *
 * L'audio non si ascolta: un video si descrive da quello che si vede.
 */
final class MediaAnalyzer
{
    public const MAX_IMAGE_BYTES = 40 * 1024 * 1024;
    public const MAX_VIDEO_BYTES = 300 * 1024 * 1024;
    private const MAX_FRAMES = 8;

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public static function thumbDir(): string
    {
        $dir = ROOT_PATH . '/data/media-thumbs';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    /**
     * @return array{description:string, tags:string, people:?int, setting:?string, occasion:?string, orientation:?string, analysis:array, thumb:?string, width:?int, height:?int, duration:?int}
     */
    public function analyze(MediaAsset $asset, MediaSourceInterface $source): array
    {
        $work = sys_get_temp_dir() . '/uno-media-' . $asset->id . '-' . bin2hex(random_bytes(4));
        mkdir($work, 0700, true);
        try {
            [$frames, $meta] = $asset->isVideo() ? $this->videoFrames($asset, $source, $work) : $this->imageFrames($asset, $source, $work);
            if ($frames === []) {
                throw new \RuntimeException('Non e\' stato possibile leggere il file.');
            }
            $thumb = $this->saveThumb((int) $asset->id, $frames[(int) floor(count($frames) / 2)]['data']);
            $result = $this->askAi($asset, $frames);
        } finally {
            foreach (glob($work . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($work);
        }

        $width = $meta['width'] ?? $asset->width;
        $height = $meta['height'] ?? $asset->height;
        $people = $result['persone']['numero'] ?? null;

        return [
            'description' => trim((string) ($result['descrizione'] ?? '')),
            'tags' => implode(', ', array_slice(array_unique(array_map(static fn ($t) => mb_strtolower(trim((string) $t)), (array) ($result['tag'] ?? []))), 0, 25)),
            'people' => is_numeric($people) ? (int) $people : null,
            'setting' => in_array($result['ambiente'] ?? null, ['interno', 'esterno', 'misto'], true) ? $result['ambiente'] : null,
            'occasion' => isset($result['occasione']) && is_string($result['occasione']) && $result['occasione'] !== '' ? mb_substr($result['occasione'], 0, 100) : null,
            'orientation' => self::orientation($width, $height),
            'analysis' => $result,
            'thumb' => $thumb,
            'width' => $width,
            'height' => $height,
            'duration' => $meta['duration'] ?? $asset->durationSeconds,
        ];
    }

    /** @return array{0: list<array{mime:string,data:string,at:?string}>, 1: array} */
    private function imageFrames(MediaAsset $asset, MediaSourceInterface $source, string $work): array
    {
        $file = $work . '/original';
        $binary = $source->download($asset, $file, self::MAX_IMAGE_BYTES) ? file_get_contents($file) : $source->previewImage($asset);
        if (!is_string($binary) || $binary === '') {
            return [[], []];
        }
        [$jpeg, $width, $height] = self::resize($binary, 1280);

        return [[['mime' => 'image/jpeg', 'data' => $jpeg, 'at' => null]], ['width' => $width, 'height' => $height]];
    }

    /** @return array{0: list<array{mime:string,data:string,at:?string}>, 1: array} */
    private function videoFrames(MediaAsset $asset, MediaSourceInterface $source, string $work): array
    {
        $file = $work . '/video';
        if (!$source->download($asset, $file, self::MAX_VIDEO_BYTES)) {
            $preview = $source->previewImage($asset);
            if ($preview === null) {
                return [[], []];
            }
            [$jpeg] = self::resize($preview, 1280);

            return [[['mime' => 'image/jpeg', 'data' => $jpeg, 'at' => null]], []];
        }

        $probe = json_decode((string) shell_exec('ffprobe -v quiet -print_format json -show_format -show_streams ' . escapeshellarg($file)), true) ?: [];
        $duration = (float) ($probe['format']['duration'] ?? 0);
        $stream = array_values(array_filter($probe['streams'] ?? [], static fn ($s) => ($s['codec_type'] ?? '') === 'video'))[0] ?? [];
        $width = isset($stream['width']) ? (int) $stream['width'] : null;
        $height = isset($stream['height']) ? (int) $stream['height'] : null;
        // Video girati in verticale col telefono: la rotazione e' nei metadati.
        $rotation = abs((int) ($stream['tags']['rotate'] ?? ($stream['side_data_list'][0]['rotation'] ?? 0)));
        if ($rotation === 90 || $rotation === 270) {
            [$width, $height] = [$height, $width];
        }

        $count = $duration > 0 ? max(3, min(self::MAX_FRAMES, (int) ceil($duration / 8))) : 1;
        $frames = [];
        for ($i = 0; $i < $count; $i++) {
            $at = $duration > 0 ? $duration * ($i + 0.5) / $count : 0;
            $out = "{$work}/frame{$i}.jpg";
            shell_exec(sprintf('ffmpeg -v quiet -y -ss %.2f -i %s -frames:v 1 -vf "scale=\'min(768,iw)\':-2" -q:v 4 %s', $at, escapeshellarg($file), escapeshellarg($out)));
            if (is_file($out) && filesize($out) > 0) {
                $frames[] = ['mime' => 'image/jpeg', 'data' => (string) file_get_contents($out), 'at' => sprintf('%02d:%02d', intdiv((int) $at, 60), (int) $at % 60)];
            }
        }

        return [$frames, ['width' => $width, 'height' => $height, 'duration' => $duration > 0 ? (int) round($duration) : null]];
    }

    private function askAi(MediaAsset $asset, array $frames): array
    {
        $customer = $asset->customerId !== null ? $this->container->get(CustomerRepository::class)->find((int) $asset->customerId) : null;
        $isVideo = $asset->isVideo() && count($frames) > 1;
        $system = "Descrivi foto e video dell'archivio di un'agenzia di comunicazione italiana, per poterli ritrovare con una ricerca a parole. "
            . 'Sii concreto e oggettivo: cosa si vede, dove, chi (senza riconoscere o nominare persone reali), che occasione sembra, '
            . 'atmosfera, colori, testi e marchi leggibili. Le parole chiave in italiano, al singolare, comprese quelle che userebbe '
            . 'chi cerca (es. "comunione", "matrimonio", "aperitivo", "prodotto", "staff", "esterno locale"). '
            . 'Rispondi solo con JSON: {"descrizione": "2-4 frasi", "tag": ["..."], "soggetti": ["..."], '
            . '"persone": {"numero": 0, "volti_in_primo_piano": false, "descrizione": "..."}, "ambiente": "interno|esterno|misto", '
            . '"luogo": "...", "occasione": "... o null", "periodo": "es. estate, Natale, o null", "colori": ["..."], "atmosfera": "...", '
            . '"testo_visibile": "... o null", "marchi": ["..."], "qualita": "buona|media|scarsa", "usi": ["post", "storia", "sito", "stampa"]'
            . ($isVideo ? ', "riassunto": "cosa racconta il video", "momenti": [{"t": "mm:ss", "descrizione": "..."}]' : '') . '}.';
        $user = 'File: ' . $asset->name
            . ($asset->folder ? "\nCartella: {$asset->folder}" : '')
            . ($customer !== null ? "\nCliente: " . $customer->displayName() . ($customer->sector ? " ({$customer->sector})" : '') : '')
            . ($isVideo ? "\nSono fotogrammi dello stesso video, nell'ordine, ai minuti: " . implode(', ', array_column($frames, 'at')) . '. Descrivi il video (non parlare di fotogrammi o immagini).' : '');

        $result = $this->container->get(AiService::class)->jsonWithImages($system, $user, array_map(static fn (array $f) => ['mime' => $f['mime'], 'data' => $f['data']], $frames), 2500);
        if ($isVideo && !empty($result['riassunto'])) {
            $result['descrizione'] = trim(((string) ($result['descrizione'] ?? '')) . ' ' . $result['riassunto']);
        }

        return $result;
    }

    private function saveThumb(int $assetId, string $binary): ?string
    {
        [$jpeg] = self::resize($binary, 480);
        $name = $assetId . '.jpg';

        return file_put_contents(self::thumbDir() . '/' . $name, $jpeg) !== false ? $name : null;
    }

    /** @return array{0:string, 1:?int, 2:?int} JPEG ridotto, larghezza e altezza originali */
    private static function resize(string $binary, int $max): array
    {
        $image = new \Imagick();
        $image->readImageBlob($binary);
        $image = $image->coalesceImages()->getImage();
        // Le foto del telefono portano la rotazione nei dati EXIF.
        $image->autoOrient();
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        if (max($width, $height) > $max) {
            $image->thumbnailImage($width >= $height ? $max : 0, $height > $width ? $max : 0);
        }
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(82);
        $image->stripImage();

        return [$image->getImageBlob(), $width, $height];
    }

    private static function orientation(?int $width, ?int $height): ?string
    {
        if (!$width || !$height) {
            return null;
        }
        $ratio = $width / $height;

        return $ratio > 1.1 ? 'orizzontale' : ($ratio < 0.9 ? 'verticale' : 'quadrato');
    }
}
