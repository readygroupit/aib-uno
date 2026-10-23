<?php

declare(strict_types=1);

namespace App\Media;

use App\Core\Config;
use App\Core\Container;
use App\Model\MediaAsset;

/**
 * Una cartella del server (o un NAS montato) come archivio: config
 * 'media' => ['localPath' => '/percorso']. Le sottocartelle diventano la
 * "cartella" del file (e da li' il cliente, se si chiama come lui).
 */
final class LocalFolderSource implements MediaSourceInterface
{
    public const EXTENSIONS = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic',
        'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'm4v' => 'video/mp4', 'webm' => 'video/webm',
    ];

    private ?string $root;

    public function __construct(Container $container)
    {
        $path = (string) ($container->get(Config::class)->get('media')['localPath'] ?? '');
        $this->root = $path !== '' && is_dir($path) ? rtrim($path, '/') : null;
    }

    public function sourceCode(): string
    {
        return 'cartella';
    }

    public function isReady(): bool
    {
        return $this->root !== null;
    }

    public function listFiles(): iterable
    {
        if ($this->root === null) {
            return;
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $mime = self::EXTENSIONS[strtolower($file->getExtension())] ?? null;
            if ($mime === null || !$file->isFile()) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($this->root) + 1);
            $folder = dirname($relative);
            yield [
                'id' => $relative,
                'name' => $file->getFilename(),
                'mime' => $mime,
                'folder' => $folder === '.' ? '' : $folder,
                'webLink' => null,
                'size' => $file->getSize(),
                'modifiedAt' => date('Y-m-d H:i:s', $file->getMTime()),
                'width' => null,
                'height' => null,
                'duration' => null,
            ];
        }
    }

    public function download(MediaAsset $asset, string $target, int $maxBytes): bool
    {
        $path = $this->path($asset);

        return $path !== null && filesize($path) <= $maxBytes && copy($path, $target);
    }

    public function previewImage(MediaAsset $asset): ?string
    {
        return null;
    }

    /** Il file vero, se esiste e sta davvero dentro la cartella d'archivio. */
    public function path(MediaAsset $asset): ?string
    {
        if ($this->root === null) {
            return null;
        }
        $path = realpath($this->root . '/' . $asset->externalId);

        return $path !== false && str_starts_with($path, realpath($this->root) . '/') ? $path : null;
    }
}
