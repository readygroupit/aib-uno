<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;
use App\Repository\UserRepository;

/**
 * La foto di una persona: ritagliata quadrata e ridotta (256 px, JPEG) in
 * public/uploads/avatars/<id>.jpg; nel database l'indirizzo con la versione
 * (?v=) perche' il browser non tenga quella vecchia. Senza GD si salva com'e'.
 */
final class UserAvatarService
{
    private const SIZE = 256;
    private const MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(private readonly Container $container)
    {
    }

    /** @return ?string l'errore, null se e' andata */
    public function store(int $userId, array $file): ?string
    {
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return 'La foto e\' troppo grande (al massimo 8 MB).';
        }
        $info = @getimagesize((string) $file['tmp_name']);
        $mime = $info['mime'] ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return 'Serve una foto JPG, PNG o WebP.';
        }
        $dir = ROOT_PATH . '/public/uploads/avatars';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return 'Impossibile salvare la foto (cartella uploads non scrivibile).';
        }
        $target = "{$dir}/{$userId}.jpg";

        if (function_exists('imagecreatetruecolor')) {
            $source = match ($mime) {
                'image/png' => @imagecreatefrompng((string) $file['tmp_name']),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp((string) $file['tmp_name']) : false,
                default => @imagecreatefromjpeg((string) $file['tmp_name']),
            };
            if ($source === false) {
                return 'Non riesco a leggere la foto.';
            }
            [$w, $h] = [imagesx($source), imagesy($source)];
            $side = min($w, $h);
            $square = imagecreatetruecolor(self::SIZE, self::SIZE);
            imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));
            imagecopyresampled($square, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);
            $saved = imagejpeg($square, $target, 86);
            imagedestroy($source);
            imagedestroy($square);
            if (!$saved) {
                return 'Impossibile salvare la foto.';
            }
        } elseif (!move_uploaded_file((string) $file['tmp_name'], $target) && !copy((string) $file['tmp_name'], $target)) {
            return 'Impossibile salvare la foto.';
        }

        $url = "/uploads/avatars/{$userId}.jpg?v=" . time();
        $this->container->get(UserRepository::class)->update($userId, ['avatar_path' => $url]);
        if ((int) ($_SESSION['user']['id'] ?? 0) === $userId) {
            $_SESSION['user']['avatar'] = $url;
        }

        return null;
    }

    public function remove(int $userId): void
    {
        @unlink(ROOT_PATH . "/public/uploads/avatars/{$userId}.jpg");
        $this->container->get(UserRepository::class)->update($userId, ['avatar_path' => null]);
        if ((int) ($_SESSION['user']['id'] ?? 0) === $userId) {
            $_SESSION['user']['avatar'] = null;
        }
    }

    /** L'indirizzo della foto, se il file c'e' davvero. */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        $file = ROOT_PATH . '/public' . strtok($path, '?');

        return str_starts_with($path, '/uploads/avatars/') && is_file($file) ? $path : null;
    }
}
