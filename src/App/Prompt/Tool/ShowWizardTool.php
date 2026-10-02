<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Package\PackageCatalogService;
use App\Package\PackageManifest;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Prompt\PromptToolRegistry;
use App\Service\AuthService;
use App\Support\Sections;
use App\View\Component\WizardComponent;

/**
 * A differenza di ShowMenuTool (solo le capacita' con una menuLabel, le
 * voci cliccabili del menu), qui entra ogni tool permesso: anche
 * "modifica contatto" merita una spiegazione anche se non e' un'icona
 * del menu. Aggiunge anche le spiegazioni dei campi dell'entita' in
 * vista (parametro 'entity', lo stesso tag gia' usato da DataTable/
 * FormComponent per il contesto mandato a Claude) - solo i campi che il
 * manifest del pacchetto marca esplicitamente con 'help': un campo ovvio
 * come "Nome" non ha bisogno di essere rispiegato, uno come "Stato del
 * contatto" (vocabolario libero) si'.
 *
 * ENTITY_PACKAGES e' la mappa provvisoria tag-entita' -> nome-pacchetto:
 * necessaria perche' il tag usato lato client (es. 'lead') non coincide
 * col nome del pacchetto ('leads') ne' con la sua entity key interna -
 * vedi packages/leads/package.php. Cresce una voce alla volta, quando un
 * pacchetto ottiene una vera verticale con campi da spiegare (oggi solo
 * 'leads' - 'user'/'permission' sono entita' del framework, non
 * pacchetti, non hanno un manifest).
 */
final class ShowWizardTool implements PromptToolInterface, PromptConversationalInterface
{
    private const ENTITY_PACKAGES = ['lead' => 'leads'];

    /**
     * menuLabel() torna null apposta per i tool che non sono una voce di
     * menu cliccabile diretta (vedi la loro documentazione) - qui pero'
     * serve comunque una riga leggibile, non il nome tecnico interno
     * ('edit_user'). Soluzione provvisoria finche' non c'e' un secondo
     * caso d'uso che chiarisca se merita un metodo apposito
     * sull'interfaccia invece di questa mappa.
     */
    private const FALLBACK_LABELS = [
        'show_menu' => 'Mostra il menu',
        'edit_user' => 'Modifica utente',
        'edit_permission' => 'Modifica permesso',
        'edit_lead' => 'Modifica contatto',
        'install_package' => 'Installa un pacchetto',
        'show_home_dashboard' => 'Cruscotto home',
        'show_profile' => 'Il mio profilo',
        'edit_communication' => 'Modifica comunicazione',
    ];

    private PromptToolRegistry $registry;
    private WizardComponent $wizard;
    private PackageCatalogService $catalog;
    private AuthService $auth;

    public function __construct(Container $container)
    {
        $this->registry = $container->get(PromptToolRegistry::class);
        $this->wizard = $container->get(WizardComponent::class);
        $this->catalog = $container->get(PackageCatalogService::class);
        $this->auth = $container->get(AuthService::class);
    }

    public function name(): string
    {
        return 'show_wizard';
    }

    public function description(): string
    {
        return "Mostra nella pagina chi e' l'assistente, come si lavora insieme, le funzioni disponibili e le "
            . "spiegazioni dei campi meno ovvi. Usalo quando l'utente chiede 'cosa puoi fare', 'aiuto', 'chi sei' o simili.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity' => [
                    'type' => 'string',
                    'description' => "Entita' attualmente in vista (es. 'lead'), per le spiegazioni dei campi - opzionale",
                ],
            ],
        ];
    }

    public function menuLabel(): ?string
    {
        return null;
    }

    public function menuSection(): ?string
    {
        return null;
    }

    public function menuCount(): ?string
    {
        return null;
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function triggers(): array
    {
        return ['cosa posso fare', 'cosa puoi fare', 'dimmi cosa puoi fare', 'cosa sai fare', 'dimmi cosa sai fare', 'chi sei', 'aiuto', 'guida', 'wizard'];
    }

    /**
     * Raggruppate per sezione (stessa tassonomia del menu, vedi
     * App\Support\Sections) invece di un unico elenco piatto: con 20+
     * capacita' ormai registrate una lista sola era diventata lunga da
     * scorrere quanto lo era il vecchio menu a griglia prima di essere
     * riorganizzato - stessa correzione, applicata qui di riflesso senza
     * aspettare che venga segnalata una seconda volta. I tool senza
     * sezione (edit_* dinamici, install_package) finiscono in "Azioni",
     * ultimo gruppo.
     */
    public function execute(array $input): array
    {
        $entity = trim((string) ($input['entity'] ?? ''));

        $grouped = [];
        foreach ($this->registry->all() as $tool) {
            if ($tool->name() === $this->name()) {
                continue;
            }

            $permission = $tool->requiredPermission();
            if ($permission !== null && !$this->auth->hasPermission($permission)) {
                continue;
            }

            $label = $tool->menuLabel()
                ?? ($tool instanceof AbstractEditEntityTool ? $tool->wizardLabel() : null)
                ?? self::FALLBACK_LABELS[$tool->name()] ?? $tool->name();
            $sectionKey = $tool->menuSection();
            $grouped[$sectionKey ?? '_azioni'][] = [
                'label' => $label,
                'description' => $this->userFacingDescription($tool->description()),
            ];
        }

        $groups = [];
        foreach (Sections::ALL as $key => $def) {
            if (!isset($grouped[$key])) {
                continue;
            }
            $groups[] = ['label' => $def['label'], 'dotColor' => $def['color'], 'items' => $grouped[$key]];
        }
        if (isset($grouped['_azioni'])) {
            $groups[] = ['label' => 'Azioni', 'dotColor' => 'petrol', 'items' => $grouped['_azioni']];
        }

        return [$this->wizard->toData([
            'groups' => $groups,
            'fieldHelp' => $this->fieldHelp($entity),
            'guide' => $this->guideWithFigures(),
        ])];
    }

    public function replySummary(array $input): ?string
    {
        return $this->guide()['reply'] ?? "Ecco cosa posso fare per te. Da dove vuoi cominciare?";
    }

    public function followUpSuggestions(): array
    {
        return $this->guide()['suggestions'] ?? ['menu'];
    }

    /** La Guida con i segnaposto dei numeri in evidenza gia' risolti. */
    private function guideWithFigures(): array
    {
        $guide = $this->guide();
        $packages = (string) count(array_filter(
            $this->catalog->list(),
            static fn (array $package) => $package['name'] !== 'provisioning'
        ));
        foreach ($guide['highlights'] ?? [] as $i => $highlight) {
            $guide['highlights'][$i]['value'] = str_replace('{packages}', $packages, (string) $highlight['value']);
        }

        return $guide;
    }

    /** Testi della Guida di questo progetto (config/guide.php). */
    private function guide(): array
    {
        $file = CONFIG_PATH . '/guide.php';

        return is_file($file) ? require $file : [];
    }

    /**
     * description() e' scritta per Claude (vedi ClaudeService::converse()
     * - descrive QUANDO scegliere questo tool, non COSA fa per un
     * operatore che legge): la frase "Usalo quando..." e gli esempi tra
     * parentesi che la seguono hanno senso li', non in un pannello di
     * aiuto rivolto a una persona. Qui si mostra solo la parte
     * descrittiva iniziale.
     */
    private function userFacingDescription(string $description): string
    {
        return trim(explode(' Usalo quando', $description)[0]);
    }

    private function fieldHelp(string $entity): array
    {
        $packageName = self::ENTITY_PACKAGES[$entity] ?? null;
        if ($packageName === null) {
            return [];
        }

        $manifest = new PackageManifest($packageName);
        $help = [];
        foreach ($manifest->entities() as $entityDef) {
            foreach ($entityDef['fields'] as $field) {
                if (!empty($field['help'])) {
                    $help[] = ['label' => $field['label'], 'help' => $field['help']];
                }
            }
        }

        return $help;
    }
}
