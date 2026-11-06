<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;
use App\Repository\SettingRepository;

/**
 * I loghi dell'azienda che usa il gestionale (non quello di aiBrains):
 * - 'logo': la versione normale, per gli sfondi chiari (login, home sotto
 *   la casella, colonna della chat, preventivo stampato). E' quello del
 *   primo accesso.
 * - 'logo_light': la versione per gli sfondi scuri, di solito bianca (il
 *   riquadro scuro in cima all'area cliente). Facoltativa: senza, li' c'e'
 *   il nome dell'agenzia.
 *
 * File in public/uploads (logo.png, logo-light.png...), indirizzo con
 * ?v= nelle impostazioni 'project.logo' e 'project.logo_light'. Si
 * caricano dal primo accesso (solo il normale) e da Configurazione, "Logo
 * dell'azienda". Senza logo, o con il file sparito, si usa quello di
 * aiBrains (login) o niente (dentro il gestionale).
 */
final class BrandingService
{
    /** tipo => [impostazione, nome del file] */
    public const KINDS = [
        'logo' => ['project.logo', 'logo'],
        'logo_light' => ['project.logo_light', 'logo-light'],
    ];
    private const TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    private const MAX_BYTES = 2 * 1024 * 1024;

    private ?array $current = null;

    public function __construct(private readonly Container $container)
    {
    }

    /** @return array{name: string, logo: ?string, logoLight: ?string} */
    public function current(): array
    {
        if ($this->current !== null) {
            return $this->current;
        }
        $saved = [];
        try {
            $settings = $this->container->get(SettingRepository::class);
            $saved = $settings->tableExists() ? $settings->valuesByPrefix('project.') : [];
        } catch (\Throwable) {
            // Senza database (o senza tabella, come in Uno) niente loghi: si va avanti.
        }

        return $this->current = [
            'name' => (string) (($saved['project.name'] ?? '') ?: APP_NAME),
            'logo' => self::existing($saved['project.logo'] ?? null),
            'logoLight' => self::existing($saved['project.logo_light'] ?? null),
        ];
    }

    /**
     * @param array $file caricato (Request::file())
     * @return string|null errore da mostrare, null se salvato
     */
    public function store(string $kind, array $file): ?string
    {
        [$setting, $name] = self::KINDS[$kind] ?? throw new \InvalidArgumentException('Logo sconosciuto.');
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return 'Il file non e\' arrivato: riprova.';
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            return 'Il logo deve pesare al massimo 2 MB.';
        }
        $type = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extension = self::TYPES[$type] ?? null;
        if ($extension === null) {
            return 'Il logo deve essere un\'immagine PNG, JPG o WebP.';
        }

        $dir = ROOT_PATH . '/public/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return 'Impossibile salvare il logo (cartella uploads non scrivibile).';
        }
        foreach (glob("{$dir}/{$name}.*") ?: [] as $old) {
            unlink($old);
        }
        if (!move_uploaded_file((string) $file['tmp_name'], "{$dir}/{$name}.{$extension}")) {
            return 'Impossibile salvare il logo.';
        }
        $this->container->get(SettingRepository::class)->set($setting, "/uploads/{$name}.{$extension}?v=" . time());
        $this->current = null;

        return null;
    }

    public function remove(string $kind): void
    {
        [$setting, $name] = self::KINDS[$kind] ?? throw new \InvalidArgumentException('Logo sconosciuto.');
        foreach (glob(ROOT_PATH . "/public/uploads/{$name}.*") ?: [] as $old) {
            unlink($old);
        }
        $this->container->get(SettingRepository::class)->set($setting, null);
        $this->current = null;
    }

    /** L'indirizzo salvato, solo se il file c'e' davvero (un dump portato altrove non ha gli upload). */
    private static function existing(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);

        return str_starts_with($path, '/uploads/') && is_file(ROOT_PATH . '/public' . $path) ? $url : null;
    }
}
