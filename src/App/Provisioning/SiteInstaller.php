<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Core\Config;
use App\Core\Container;

/**
 * Produzione: vhost Apache + certificato per un progetto generato, come
 * gli script di Core (new_readyservices_site.sh). Copia il prototipo
 * (config provisioning.vhost.prototype) sostituendo host e cartella, lo
 * attiva, controlla la configurazione (se e' sbagliata lo toglie),
 * ricarica Apache e chiede il certificato a certbot.
 *
 * Gira come root dentro bin/provision-queue.php, mai nella richiesta web.
 * Fatto se esiste il file -le-ssl.conf che certbot crea; se certbot
 * fallisce riprova al massimo una volta l'ora (limiti di Let's Encrypt).
 */
final class SiteInstaller
{
    private const CERTBOT_RETRY_SECONDS = 3600;

    private Config $config;

    public function __construct(Container $container)
    {
        $this->config = $container->get(Config::class);
    }

    public function enabled(): bool
    {
        return !empty($this->config->get('provisioning')['vhost']);
    }

    /**
     * @return string[] log (vuoto se non c'era niente da fare)
     */
    public function ensure(string $slug, string $dir, string $url): array
    {
        $vhost = $this->config->get('provisioning')['vhost'];
        $host = (string) parse_url($url, PHP_URL_HOST);
        $file = rtrim($vhost['sitesDir'], '/') . '/' . sprintf($vhost['filePattern'], str_replace('-', '_', $slug));
        $sslFile = substr($file, 0, -5) . '-le-ssl.conf';

        if (is_file($sslFile)) {
            return [];
        }
        if (!is_dir("{$dir}/public")) {
            return ["{$host}: manca {$dir}/public, vhost rimandato."];
        }

        $log = [];
        if (!is_file($file)) {
            $prototype = (string) file_get_contents(ROOT_PATH . '/' . $vhost['prototype']);
            file_put_contents($file, str_replace(['__HOST__', '__DIR__'], [$host, rtrim($dir, '/')], $prototype));
            $this->run('/usr/sbin/a2ensite ' . escapeshellarg(basename($file)));
            if (!$this->succeeds('/usr/sbin/apachectl configtest')) {
                $this->succeeds('/usr/sbin/a2dissite ' . escapeshellarg(basename($file)));
                unlink($file);

                throw new \RuntimeException("{$host}: configurazione Apache non valida, vhost tolto.");
            }
            $this->run('/usr/bin/systemctl reload apache2');
            $log[] = "{$host}: vhost creato e Apache ricaricato.";
        } elseif (time() - (int) filemtime($file) < self::CERTBOT_RETRY_SECONDS) {
            return [];
        }

        touch($file);
        if (!$this->succeeds(escapeshellarg($vhost['certbot']) . ' --apache --redirect -d ' . escapeshellarg($host)
            . ' --non-interactive --agree-tos -m ' . escapeshellarg($vhost['email']))) {
            $log[] = "{$host}: certbot non e' riuscito, riprovo tra un'ora.";

            return $log;
        }
        $log[] = "{$host}: certificato installato (https attivo).";

        return $log;
    }

    private function run(string $command): void
    {
        exec($command . ' 2>&1', $output, $code);
        if ($code !== 0) {
            throw new \RuntimeException('Comando fallito: ' . $command . ' - ' . implode(' ', $output));
        }
    }

    private function succeeds(string $command): bool
    {
        exec($command . ' 2>&1', $output, $code);

        return $code === 0;
    }
}
