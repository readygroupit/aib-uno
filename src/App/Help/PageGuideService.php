<?php

declare(strict_types=1);

namespace App\Help;

use App\Core\Container;
use App\Core\Router;

/**
 * La guida di ogni pagina ("Come funziona questa pagina", il pulsante in
 * basso a destra, vedi public/js/page-help.js). Si sceglie dall'indirizzo:
 * la rotta che risponde dice di che pagina si tratta.
 *
 * I testi sono in config/page-guides.php:
 * - 'pages': la guida completa di una pagina, per nome di rotta;
 * - 'entities': per gli elenchi e le schede (elenco, scheda, nuovo), il
 *   nome dell'elemento e cosa contiene; la guida si compone da qui.
 * Una pagina nuova si aggiunge li'; senza, resta una guida generica.
 */
final class PageGuideService
{
    public function __construct(private readonly Container $container)
    {
    }

    /** @return array{title:string, text:string, steps?:string[], tip?:string} */
    public function forPath(string $path): array
    {
        $guides = self::registry();
        $path = '/' . trim((string) parse_url($path, PHP_URL_PATH), '/');
        static $router = null;
        $router ??= new Router(require CONFIG_PATH . '/routes.php');
        $match = $router->match($path === '/' ? '/' : rtrim($path, '/'));
        $name = (string) ($match['name'] ?? '');

        if ($name !== '' && isset($guides['pages'][$name])) {
            return $guides['pages'][$name];
        }
        foreach (['-new' => 'new', '-edit' => 'edit', '' => 'list'] as $suffix => $kind) {
            $entity = $suffix === '' ? $name : (str_ends_with($name, $suffix) ? substr($name, 0, -strlen($suffix)) : null);
            if ($entity !== null && isset($guides['entities'][$entity])) {
                return self::compose($guides['entities'][$entity], $kind);
            }
        }

        return [
            'title' => 'Come funziona questa pagina',
            'text' => "In alto quello che serve subito; i pulsanti fanno le azioni del momento. L'icona \"i\" accanto ai titoli spiega ogni riquadro.",
            'steps' => ['I link aprono le schede; gli elementi collegati (righe, referenti, fasi...) si aprono nel pannello laterale.', 'I campi con * sono obbligatori.', 'Puoi anche scrivere nel prompt in alto quello che vuoi fare.'],
        ];
    }

    /** @param array{name:string, plural:string, what:string, tip?:string, new?:string, edit?:string} $e */
    private static function compose(array $e, string $kind): array
    {
        return match ($kind) {
            'list' => [
                'title' => $e['plural'],
                'text' => $e['what'],
                'steps' => [
                    'Cerca con il campo in alto o scrivilo al prompt (es. "' . mb_strtolower($e['plural']) . ' di questo mese").',
                    'Clicca una riga per aprire la scheda.',
                    '"+ Nuovo" ne aggiunge uno: i campi con * sono obbligatori.',
                ],
                'tip' => $e['tip'] ?? null,
            ],
            'new' => [
                'title' => 'Nuovo: ' . $e['name'],
                'text' => $e['new'] ?? ('Compila i dati di ' . $e['name'] . '. ' . $e['what']),
                'steps' => ['I campi con * sono obbligatori: senza, non si salva e il campo si colora.', 'L\'icona "i" accanto al titolo di un riquadro spiega cosa metterci.', '"Crea" salva; dopo si apre la scheda.'],
                'tip' => $e['tip'] ?? null,
            ],
            default => [
                'title' => 'La scheda: ' . $e['name'],
                'text' => $e['edit'] ?? $e['what'],
                'steps' => ['In alto i dati principali e le azioni del momento.', 'Le schede (se ci sono) dividono il lavoro dai dati: "Dettagli" o "Dati" contengono il form.', 'Modifica i campi e premi "Salva"; i campi con * sono obbligatori.'],
                'tip' => $e['tip'] ?? null,
            ],
        };
    }

    private static function registry(): array
    {
        static $registry = null;

        return $registry ??= (is_file(CONFIG_PATH . '/page-guides.php') ? require CONFIG_PATH . '/page-guides.php' : ['pages' => [], 'entities' => []]);
    }
}
