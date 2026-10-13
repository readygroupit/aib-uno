<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Renderer .phtml minimale con supporto a layout. Dentro il template
 * "$this" e' l'istanza di View (stessa convenzione dei view helper Laminas),
 * quindi nei .phtml si scrive $this->e($valore) per l'escaping.
 */
final class View
{
    public function render(string $templateFile, array $vars = []): string
    {
        return $this->renderFile($templateFile, $vars);
    }

    public function renderWithLayout(string $templateFile, array $vars, string $layoutFile): string
    {
        $content = $this->renderFile($templateFile, $vars);

        return $this->renderFile($layoutFile, $vars + ['content' => $content]);
    }

    public function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Cache-busting per asset statici referenziati nell'HTML (link/script),
     * stesso meccanismo gia' in uso in Core (Common\Helper\URLHelper::getPublicResourceURL):
     * appende '?v=' + filemtime del file, cosi' ogni modifica cambia l'URL
     * e il browser non puo' servire una copia in cache dalla richiesta
     * precedente. $path e' relativo a public/ (es. '/css/tokens.css').
     */
    public function asset(string $path): string
    {
        $filePath = ROOT_PATH . '/public' . $path;
        $version = is_file($filePath) ? filemtime($filePath) : time();

        return $path . '?v=' . $version;
    }

    /**
     * Import map con TUTTI i file JS di public/js, ognuno con la sua versione
     * (?v=filemtime, come asset()): i moduli importati da altri moduli
     * (import './x.js') non passano da un tag <script>, e senza questa mappa
     * il browser terrebbe in cache la copia vecchia. Va stampata prima del
     * primo <script type="module"> (vedi layout.phtml).
     */
    public function importMap(): string
    {
        $base = ROOT_PATH . '/public';
        $imports = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base . '/js', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'js') {
                continue;
            }
            $path = substr(str_replace('\\', '/', $file->getPathname()), strlen($base));
            $imports[$path] = $path . '?v=' . $file->getMTime();
        }
        ksort($imports);

        return (string) json_encode(['imports' => $imports], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }

    /** Campo nascosto pronto da inserire in ogni <form> nativo (vedi Csrf). */
    public function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . $this->e(Csrf::token()) . '">';
    }

    private function renderFile(string $file, array $vars): string
    {
        if (!is_file($file)) {
            throw new \RuntimeException("Template non trovato: $file");
        }

        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;

        return ob_get_clean();
    }
}
