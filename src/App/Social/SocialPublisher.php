<?php

declare(strict_types=1);

namespace App\Social;

use App\Connector\ConnectorException;
use App\Connector\ConnectorRegistry;
use App\Connector\MetaConnector;
use App\Core\Container;
use App\Editorial\EditorialService;
use App\Model\EditorialPost;
use App\Model\SocialAccount;
use App\Repository\EditorialPostRepository;
use App\Repository\SocialAccountRepository;
use App\TestMode\TestModeService;

/**
 * Un contenuto del piano editoriale sui social del cliente (Facebook e
 * Instagram, MetaConnector):
 * - i file da pubblicare si caricano nel contenuto (public/uploads/social/
 *   <id>/, indirizzo completo salvato, cosi' anche il cron sa dove sono);
 *   le foto PNG/WebP diventano JPG, l'unico formato che Instagram accetta;
 * - "Pubblica ora" pubblica subito; "Programma la pubblicazione" lo segna
 *   programmato e il job 'publish_social_posts' (ogni 5 minuti) lo
 *   pubblica all'ora di uscita;
 * - pubblicato: stato "pubblicato" con il link al post (EditorialService),
 *   un errore resta sul contenuto e la programmazione si ferma.
 * In modalita' prova (TestModeService) non esce niente: si registra e il
 * contenuto risulta pubblicato in prova.
 */
final class SocialPublisher
{
    public const NETWORKS = ['Instagram' => 'instagram', 'Facebook' => 'facebook'];
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const VIDEO_TYPES = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov'];
    private const MAX_IMAGE = 8 * 1024 * 1024;
    private const MAX_VIDEO = 300 * 1024 * 1024;

    public function __construct(private readonly Container $container)
    {
    }

    public function isConnected(): bool
    {
        return $this->meta()?->isConnected() === true;
    }

    /** La pagina o il profilo del cliente per il canale del contenuto, se assegnato. */
    public function accountFor(EditorialPost $post): ?SocialAccount
    {
        $network = self::NETWORKS[$post->channel] ?? null;
        if ($network === null || $post->customerId === null) {
            return null;
        }
        try {
            return $this->container->get(SocialAccountRepository::class)->findForCustomer((int) $post->customerId, $network);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Per la pagina del contenuto: dove uscira', i file, se si puo'
     * pubblicare e perche' no.
     *
     * @return array<string, mixed>
     */
    public function panel(EditorialPost $post, string $base): array
    {
        $test = $this->container->get(TestModeService::class);
        $simulated = $test->simulates('social');
        $supported = isset(self::NETWORKS[$post->channel]);
        $account = $this->accountFor($post);
        $files = $post->publishFileList();
        $blocker = match (true) {
            !$supported => "{$post->channel} non si pubblica dal gestionale: segnalo pubblicato a mano.",
            !$this->isConnected() && !$simulated => 'Facebook e Instagram non sono collegati: si collegano dalla Configurazione.',
            $account === null && !$simulated => "Il cliente non ha una " . ($post->channel === 'Instagram' ? 'pagina Instagram' : 'pagina Facebook') . " assegnata: Configurazione, \"Pagine e profili social\".",
            $files === [] && $post->channel === 'Instagram' => 'Carica la foto o il video da pubblicare.',
            $post->format === 'carosello' && count($files) < 2 && $post->channel === 'Instagram' => 'Un carosello ha almeno 2 foto o video.',
            default => null,
        };

        return [
            'supported' => $supported,
            'account' => $account?->label() ?? ($simulated && $supported ? "{$post->channel} del cliente (prova)" : null),
            'files' => array_map(static fn (array $f, int $i) => ['url' => $f['url'], 'name' => $f['name'], 'video' => !empty($f['video']), 'remove' => "{$base}/file?rimuovi={$i}"], $files, array_keys($files)),
            'uploadAction' => $supported && $post->isOpen() ? "{$base}/file" : null,
            'canPublish' => $blocker === null && $post->isOpen() && in_array($post->stage, ['approvato', 'programmato'], true),
            'blocker' => $post->isOpen() ? $blocker : null,
            'scheduled' => (int) $post->autoPublish === 1 && $post->stage === 'programmato',
            'error' => $post->publishError,
            'publishedAt' => $post->publishedAt,
            'test' => $simulated,
        ];
    }

    /**
     * @param array $file caricato (Request::file())
     * @return ?string errore
     */
    public function addFile(int $postId, array $file, string $baseUrl): ?string
    {
        $post = $this->post($postId);
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            return 'Il file non e\' arrivato (forse e\' troppo grande per il server): riprova.';
        }
        $type = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $video = isset(self::VIDEO_TYPES[$type]);
        if (!$video && !isset(self::IMAGE_TYPES[$type])) {
            return 'Si caricano foto (JPG, PNG, WebP) o video (MP4, MOV).';
        }
        if ((int) $file['size'] > ($video ? self::MAX_VIDEO : self::MAX_IMAGE)) {
            return $video ? 'Il video supera i 300 MB.' : 'La foto supera gli 8 MB.';
        }
        $files = $post->publishFileList();
        if (count($files) >= 10) {
            return 'Al massimo 10 file per contenuto.';
        }
        $dir = ROOT_PATH . "/public/uploads/social/{$postId}";
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return 'Impossibile salvare il file (cartella uploads non scrivibile).';
        }
        $name = bin2hex(random_bytes(6));
        if ($video) {
            $path = "{$dir}/{$name}." . self::VIDEO_TYPES[$type];
            if (!move_uploaded_file($tmp, $path)) {
                return 'Impossibile salvare il file.';
            }
        } else {
            $path = "{$dir}/{$name}.jpg";
            $error = self::toJpeg($tmp, $type, $path);
            if ($error !== null) {
                return $error;
            }
        }
        $relative = substr($path, strlen(ROOT_PATH . '/public'));
        $files[] = ['path' => $relative, 'url' => rtrim($baseUrl, '/') . $relative, 'name' => mb_substr((string) ($file['name'] ?? basename($path)), 0, 120), 'video' => $video];
        $this->posts()->update($postId, ['publish_files' => json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'publish_error' => null]);

        return null;
    }

    public function removeFile(int $postId, int $index): void
    {
        $post = $this->post($postId);
        $files = $post->publishFileList();
        if (!isset($files[$index])) {
            return;
        }
        $path = ROOT_PATH . '/public' . $files[$index]['path'];
        if (str_starts_with($files[$index]['path'], "/uploads/social/{$postId}/") && is_file($path)) {
            unlink($path);
        }
        array_splice($files, $index, 1);
        $this->posts()->update($postId, ['publish_files' => $files === [] ? null : json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    }

    /** Programmato: esce da solo all'ora di uscita (job 'publish_social_posts'). */
    public function schedule(int $postId, int $userId): string
    {
        $post = $this->post($postId);
        $panel = $this->panel($post, '');
        if ($panel['blocker'] !== null) {
            throw new \InvalidArgumentException($panel['blocker']);
        }
        if (!in_array($post->stage, ['approvato', 'programmato'], true)) {
            throw new \InvalidArgumentException('Si programma un contenuto approvato dal cliente.');
        }
        if ($post->stage !== 'programmato') {
            $this->container->get(EditorialService::class)->advance($postId, 'programmato', $userId, true);
        }
        $this->posts()->update($postId, ['auto_publish' => 1, 'publish_error' => null]);

        return 'Programmato: esce da solo ' . self::whenText($post) . ($panel['test'] ? ' (in modalita\' prova: simulato).' : '.');
    }

    public function unschedule(int $postId): string
    {
        $this->posts()->update($postId, ['auto_publish' => 0]);

        return 'Programmazione tolta: non esce da solo.';
    }

    /**
     * Pubblica adesso.
     *
     * @return string cosa e' successo
     */
    public function publish(int $postId, ?int $userId): string
    {
        $post = $this->post($postId);
        $panel = $this->panel($post, '');
        if ($panel['blocker'] !== null) {
            throw new \InvalidArgumentException($panel['blocker']);
        }
        if (!$post->isOpen()) {
            throw new \InvalidArgumentException('Questo contenuto e\' gia\' pubblicato o scartato.');
        }
        $files = $post->publishFileList();
        $caption = trim((string) $post->caption);
        $test = $this->container->get(TestModeService::class);

        if ($test->simulates('social')) {
            $test->record('social', "Pubblicazione su {$post->channel} (" . ($panel['account'] ?? 'cliente') . "): {$post->title}", $caption . ($files !== [] ? "\n\nFile: " . implode(', ', array_column($files, 'url')) : ''), null, 'editorial_posts', $postId);
            $this->posts()->update($postId, ['published_at' => date('Y-m-d H:i:s'), 'external_post_id' => 'prova', 'auto_publish' => 0, 'publish_error' => null]);
            $this->container->get(EditorialService::class)->advance($postId, 'pubblicato', (int) $userId, true);

            return 'Modalita\' prova: pubblicazione simulata, sul social non e\' uscito niente. Il contenuto risulta pubblicato.';
        }

        $account = $this->accountFor($post);
        $meta = $this->meta();
        try {
            if ($account === null || $meta === null) {
                throw new ConnectorException('Pagina o profilo del cliente non collegato.');
            }
            $result = $account->network === 'instagram'
                ? $meta->publishInstagram($account, $files, $caption, (string) $post->format)
                : $meta->publishFacebook($account, $files, $caption);
        } catch (ConnectorException $e) {
            $this->posts()->update($postId, ['publish_error' => mb_substr($e->getMessage(), 0, 255), 'auto_publish' => 0]);
            throw $e;
        }
        $this->posts()->update($postId, ['published_at' => date('Y-m-d H:i:s'), 'external_post_id' => $result['id'], 'auto_publish' => 0, 'publish_error' => null]);
        $this->container->get(EditorialService::class)->advance($postId, 'pubblicato', (int) $userId, true, (string) ($result['url'] ?? ''));

        return "Pubblicato su {$account->label()}." . ($result['url'] ? '' : ' Il link al post si vede sul social.');
    }

    /**
     * I programmati arrivati all'ora di uscita (job ogni 5 minuti).
     *
     * @return array{published:int, failed:int}
     */
    public function publishDue(): array
    {
        $outcome = ['published' => 0, 'failed' => 0];
        $now = date('Y-m-d H:i');
        foreach ($this->posts()->findAll(['stage' => 'programmato', 'auto_publish' => 1]) as $post) {
            /** @var EditorialPost $post */
            $at = $post->publishDate . ' ' . ($post->publishTime ?: '09:00');
            if ($post->publishDate === null || $at > $now) {
                continue;
            }
            try {
                $this->publish((int) $post->id, null);
                $outcome['published']++;
            } catch (\Throwable $e) {
                // L'errore resta sul contenuto (publish_error) e la programmazione si ferma.
                $this->posts()->update((int) $post->id, ['publish_error' => mb_substr($e->getMessage(), 0, 255), 'auto_publish' => 0]);
                $outcome['failed']++;
            }
        }

        return $outcome;
    }

    private static function toJpeg(string $tmp, string $type, string $target): ?string
    {
        if ($type === 'image/jpeg') {
            return move_uploaded_file($tmp, $target) ? null : 'Impossibile salvare il file.';
        }
        if (class_exists(\Imagick::class)) {
            try {
                $image = new \Imagick($tmp);
                $image->setImageBackgroundColor('white');
                $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality(90);

                return $image->writeImage($target) ? null : 'Impossibile convertire la foto in JPG.';
            } catch (\Throwable) {
                return 'Impossibile convertire la foto in JPG.';
            }
        }
        if (function_exists('imagecreatefrompng')) {
            $source = $type === 'image/png' ? @imagecreatefrompng($tmp) : (function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false);
            if ($source !== false) {
                $canvas = imagecreatetruecolor(imagesx($source), imagesy($source));
                imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
                imagecopy($canvas, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));

                return imagejpeg($canvas, $target, 90) ? null : 'Impossibile convertire la foto in JPG.';
            }
        }

        return 'Instagram accetta solo foto JPG: carica la foto in JPG.';
    }

    private static function whenText(EditorialPost $post): string
    {
        return 'il ' . date('d/m', strtotime((string) $post->publishDate)) . ' alle ' . ($post->publishTime ?: '09:00');
    }

    private function post(int $postId): EditorialPost
    {
        $post = $this->posts()->find($postId);
        if (!$post instanceof EditorialPost) {
            throw new \InvalidArgumentException('Contenuto non trovato.');
        }

        return $post;
    }

    private function posts(): EditorialPostRepository
    {
        return $this->container->get(EditorialPostRepository::class);
    }

    private function meta(): ?MetaConnector
    {
        $registry = $this->container->get(ConnectorRegistry::class);
        $meta = $registry->has('meta') ? $registry->get('meta') : null;

        return $meta instanceof MetaConnector ? $meta : null;
    }
}
