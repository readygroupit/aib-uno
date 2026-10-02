<?php

declare(strict_types=1);

namespace App\Service;

use App\Connector\ConnectorRegistry;
use App\Core\Container;
use App\Package\PackageCatalogService;
use App\Repository\MessageTemplateRepository;

/**
 * Cosa serve ancora per rendere operativo il sistema - unica fonte per la
 * checklist mostrata sia nel tour di benvenuto sia nella pagina
 * Configurazione (stesso JSON, stesso renderer JS: public/js/setup.js).
 *
 * Un passaggio e' "obbligatorio" (conta nella percentuale) se senza di
 * lui il flusso descritto nel progetto non parte: i contatti devono
 * arrivare (Jotform), avere un archivio operativo (Google Sheets) e i
 * messaggi ricorrenti devono avere un testo. Gli altri sono facoltativi.
 * 'done' e' null per i passaggi che oggi non si possono verificare
 * (attivita' manuali): non contano ne' come fatti ne' come mancanti.
 */
final class SetupService
{
    /**
     * Testo mostrato in cima alla sezione a cui porta un passaggio non-
     * connettore (vedi AbstractController::renderPage() e ?setup=chiave).
     */
    private const HINTS = [
        'message_templates' => [
            'title' => 'Modelli di messaggio',
            'text' => 'Qui scrivi i testi che usi piu\' spesso: una conferma di ricezione, un sollecito documenti, un aggiornamento di stato. Crea almeno un modello e assegnagli un codice breve senza spazi (es. sollecito_documenti). Poi potrai richiamarlo quando registri una comunicazione.',
        ],
        'conciliaweb' => [
            'title' => 'ConciliaWeb',
            'text' => 'Non esiste ancora un collegamento automatico con il portale ConciliaWeb: invio e monitoraggio si fanno sul portale, e qui si tiene traccia a mano. Apri la pratica e imposta lo stato "In conciliazione" quando la procedura parte, poi aggiornalo all\'esito.',
        ],
        'classification' => [
            'title' => 'Classificazione del disservizio',
            'text' => 'Il tipo di disservizio (ritardo, cancellazione, negato imbarco, bagaglio, spese documentate) si sceglie a mano nel campo Categoria di ogni pratica. Non e\' ancora riconosciuto in automatico dalla richiesta del cliente.',
        ],
    ];

    private ConnectorRegistry $connectors;
    private MessageTemplateRepository $templates;
    private PackageCatalogService $packages;

    public function __construct(Container $container)
    {
        $this->connectors = $container->get(ConnectorRegistry::class);
        $this->templates = $container->get(MessageTemplateRepository::class);
        $this->packages = $container->get(PackageCatalogService::class);
    }

    /** @return array{items: list<array<string, mixed>>, progress: array{done: int, total: int, percent: int}} */
    public function payload(): array
    {
        $items = [
            $this->connectorItem('jotform', 'Collega Jotform', 'I moduli di raccolta contatti: qui si vedono quelli trovati.', true),
            $this->connectorItem('google_sheets', 'Collega Google Sheets', 'Il foglio usato come archivio operativo.', true),
            $this->templatesItem(),
            $this->connectorItem('whatsapp', 'Collega WhatsApp', 'Il numero aziendale da cui far partire i messaggi ai clienti.', false),
            $this->linkItem('conciliaweb', 'ConciliaWeb', 'Invio e monitoraggio delle pratiche in conciliazione: per ora si seguono a mano.', '/pratiche?setup=conciliaweb'),
            $this->linkItem('classification', 'Classificazione del disservizio', 'Il tipo di disservizio si sceglie a mano per ogni pratica.', '/pratiche?setup=classification'),
        ];

        $required = array_filter($items, static fn (array $item) => $item['required']);
        $done = count(array_filter($required, static fn (array $item) => $item['done'] === true));

        return [
            'items' => $items,
            'progress' => [
                'done' => $done,
                'total' => count($required),
                'percent' => $required === [] ? 100 : (int) round($done / count($required) * 100),
            ],
        ];
    }

    /** @return ?array{type: string, title: string, text: string, backHref: string} */
    public function hint(string $key): ?array
    {
        $hint = self::HINTS[$key] ?? null;
        if ($hint === null) {
            return null;
        }

        return ['type' => 'setup-hint', 'backHref' => '/configurazione'] + $hint;
    }

    /** @return array<string, mixed> */
    private function connectorItem(string $code, string $label, string $description, bool $required): array
    {
        $connector = $this->connectors->get($code);
        $connected = $connector->isConnected();

        return [
            'key' => $code,
            'label' => $label,
            'description' => $description,
            'required' => $required,
            'kind' => 'connector',
            'done' => $connected,
            'status' => $connected ? 'Collegato' : 'Da collegare',
            'summary' => $connected ? $connector->summary() : null,
            'spec' => $connector->setupSpec(),
        ];
    }

    /** @return array<string, mixed> */
    private function templatesItem(): array
    {
        $installed = $this->packages->isInstalled('message_templates');
        $count = $installed ? $this->templates->count() : 0;

        return [
            'key' => 'message_templates',
            'label' => 'Crea i modelli di messaggio',
            'description' => 'Testi pronti per solleciti e comunicazioni ricorrenti.',
            'required' => true,
            'kind' => 'link',
            'href' => '/modelli-messaggio?setup=message_templates',
            'done' => $count > 0,
            'status' => $count > 0 ? ($count === 1 ? '1 modello' : "{$count} modelli") : 'Da creare',
            'summary' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function linkItem(string $key, string $label, string $description, string $href): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'required' => false,
            'kind' => 'link',
            'href' => $href,
            'done' => null,
            'status' => 'Manuale',
            'summary' => null,
        ];
    }
}
