<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Package\PackageCatalogService;
use App\Package\PackageManifest;
use App\Prompt\PromptToolInterface;
use App\Repository\AbstractRepository;
use App\Repository\CustomerRepository;
use App\Service\AuthService;
use App\Support\ReferenceResolver;
use App\View\Component\DataTableComponent;
use App\View\Component\FormComponent;

/**
 * Base comune per "apri/cerca/modifica una entita'" - era EditUserTool e
 * EditLeadTool quasi identici (ricerca per id o testo libero, 0/1/N
 * risultati, form costruito dal manifest). Vedi la stessa nota di
 * AbstractListEntityTool su Users/Permessi/Leads non retrofittati.
 *
 * fieldsFromManifest()/inferWidth() sono la stessa logica che era in
 * EditLeadTool, ora generica: legge packages/<package>/package.php e
 * costruisce i campi del form (label/valore/tipo/larghezza) leggendo la
 * proprieta' corrispondente del model via la stessa trasformazione
 * snake_case -> camelCase di AbstractModel::fromArray() - cosi' un
 * pacchetto nuovo non riscrive questa conversione un'altra volta.
 */
abstract class AbstractEditEntityTool implements PromptToolInterface
{
    protected AbstractRepository $repository;
    protected FormComponent $form;
    protected DataTableComponent $dataTable;
    protected AuthService $auth;

    private Container $toolContainer;

    public function __construct(Container $container)
    {
        $this->toolContainer = $container;
        $this->repository = $container->get($this->repositoryClass());
        $this->form = $container->get(FormComponent::class);
        $this->dataTable = $container->get(DataTableComponent::class);
        $this->auth = $container->get(AuthService::class);
    }

    /** @return class-string<AbstractRepository> */
    abstract protected function repositoryClass(): string;

    abstract protected function packageName(): string;

    /** Di norma coincide col nome del pacchetto (vedi packages/*\/package.php) - override solo se un pacchetto avesse piu' entita'. */
    protected function manifestEntityKey(): string
    {
        return $this->packageName();
    }

    abstract protected function entityTag(): string;

    /** Url base SENZA slash finale (es. '/contatti'): action = "{baseUrl}/{id}" o "{baseUrl}/nuovo". */
    abstract protected function baseUrl(): string;

    abstract protected function formTitle(bool $isNew): string;

    /**
     * Etichetta leggibile per il wizard "cosa posso fare?" (vedi
     * ShowWizardTool) - tutti i tool costruiti su questa base la
     * ottengono gratis, senza dover aggiungere una voce a mano in una
     * mappa da tenere sincronizzata (successo davvero: dimenticata una
     * volta su 9 pacchetti nuovi, vedi FALLBACK_LABELS li').
     */
    public function wizardLabel(): string
    {
        return $this->formTitle(false);
    }

    /** Colonne DB su cui cercare in ricerca libera (LIKE). */
    abstract protected function searchColumns(): array;

    /** Sottoinsieme di searchColumns() su cui riprovare con SOUNDEX se la ricerca esatta non trova nulla - [] per disattivarlo. */
    protected function phoneticColumns(): array
    {
        return [];
    }

    /** @return list<array{key:string,label:string}> colonne mostrate quando la ricerca libera trova piu' di un risultato */
    abstract protected function candidateColumns(): array;

    /** Campi del manifest esclusi dal form (gestiti da azioni dedicate, es. campi di conversione). */
    protected function systemManagedFields(): array
    {
        return [];
    }

    /** @return list<array{label:string, action:string, confirm?:string}> pulsanti extra sul form, oltre "Salva" - hook per azioni custom (vedi LeadsController::convertAction per il concetto, non ancora generalizzato). */
    protected function secondaryActions(AbstractModel $model): array
    {
        return [];
    }

    /**
     * "Elimina" e' automatico per ogni pacchetto su questa base (non un
     * hook come secondaryActions() sopra): compare solo se l'utente ha
     * davvero {package}.delete, cosi' un pacchetto nuovo non deve
     * ricordarsi di aggiungerlo. formaction posta su '.../elimina' (vedi
     * AbstractEntityController::deleteAction() + la rotta aggiunta da
     * entityRoutes()), confirm gestito gia' da form.js.
     */
    private function allSecondaryActions(AbstractModel $model): array
    {
        $actions = $this->secondaryActions($model);

        if ($this->auth->hasPermission($this->packageName() . '.delete')) {
            $actions[] = [
                'label' => 'Elimina',
                'action' => $this->baseUrl() . '/' . $model->id . '/elimina',
                'confirm' => 'Eliminare definitivamente questo elemento?',
                'variant' => 'delete',
            ];
        }

        return $actions;
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

    public function triggers(): array
    {
        return [];
    }

    /**
     * Oltre ad aprire un elemento esistente, apre il form per inserirne
     * uno nuovo ('new'), gia' compilato con quello che l'operatore ha
     * detto ('values': "nuovo appuntamento per Fabrizio lunedi' alle 10").
     */
    public function inputSchema(): array
    {
        // Una riga sola con i campi (non uno schema per campo): sono
        // decine di strumenti, ogni token in piu' rallenta ogni risposta.
        $hints = [];
        foreach ($this->creatableFields() as $key => $definition) {
            $hints[] = $key . ' (' . $this->valueHint($definition) . ')';
        }

        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => "Id, se gia' noto"],
                'query' => ['type' => 'string', 'description' => "Testo libero da cercare, se l'id non e' noto"],
                'new' => ['type' => 'boolean', 'description' => "true per aprire il form di inserimento di un elemento nuovo (es. 'nuovo', 'inserisci', 'aggiungi', 'crea')"],
                'values' => [
                    'type' => 'object',
                    'description' => "Con new: solo i valori detti dall'operatore, per precompilare il form. Campi: " . implode('; ', $hints),
                ],
            ],
        ];
    }

    /**
     * Permesso per il form di un elemento nuovo: "{pacchetto}.create" dove
     * c'e' la solita quaterna; dove lo strumento chiede un permesso suo
     * (orari: planner.team, registro: worklog.view) vale quello.
     */
    protected function createPermission(): string
    {
        $permission = (string) $this->requiredPermission();

        return str_ends_with($permission, '.edit') ? substr($permission, 0, -5) . '.create' : $permission;
    }

    public function execute(array $input): array
    {
        $id = isset($input['id']) ? (int) $input['id'] : null;
        $query = trim((string) ($input['query'] ?? ''));

        // Niente da cercare: chi chiede vuole inserirne uno nuovo (prima
        // rispondeva "Specifica un id o un testo da cercare", senza form).
        if (!empty($input['new']) || ($id === null && $query === '')) {
            return [$this->newFormLink(is_array($input['values'] ?? null) ? $input['values'] : [])];
        }

        if ($id === null && $query !== '') {
            $matches = $this->repository->searchFreeText($this->searchColumns(), $query, 10, $this->phoneticColumns());

            if (count($matches) === 0) {
                return [$this->emptyResult($query)];
            }
            if (count($matches) > 1) {
                return [$this->candidatesTable($matches, $query)];
            }

            $id = $matches[0]->id;
        }

        if ($id === null) {
            return [$this->emptyResult('')];
        }

        $model = $this->repository->find($id);
        if ($model === null) {
            return [$this->emptyResult((string) $id)];
        }

        return $this->buildPage($model);
    }

    /**
     * Il form di inserimento come pagina vera ('{baseUrl}/nuovo', con i
     * default del controller e ?campo=valore per precompilare): hero.js
     * lo apre come un link. I nomi detti a parole (un collega, un cliente)
     * diventano l'id; quelli non trovati si scelgono nel form.
     */
    protected function newFormLink(array $values): array
    {
        if (!$this->auth->hasPermission($this->createPermission())) {
            return $this->dataTable->toData([
                'title' => $this->formTitle(true),
                'columns' => [],
                'rows' => [],
                'emptyMessage' => 'Non hai il permesso di inserirne di nuovi.',
            ]);
        }

        $fields = $this->creatableFields();
        $resolver = $this->toolContainer->get(ReferenceResolver::class);
        $query = [];
        $missing = [];
        foreach ($values as $key => $value) {
            if (!isset($fields[$key]) || !is_scalar($value) || trim((string) $value) === '') {
                continue;
            }
            $definition = $fields[$key];
            $value = trim((string) $value);

            if (isset($definition['autocomplete']) && empty($definition['autocomplete']['freeText'])) {
                $id = $resolver->resolve((string) $definition['autocomplete']['source'], $value);
                if ($id === null) {
                    $missing[] = "{$definition['label']} \u{ab}{$value}\u{bb}";
                    continue;
                }
                $value = (string) $id;
            } elseif (($definition['optionsSource'] ?? null) === 'staff') {
                $ids = [];
                foreach (preg_split('/\s*(?:,|\be\b)\s*/u', $value, -1, PREG_SPLIT_NO_EMPTY) as $name) {
                    $id = $resolver->resolve('/riferimenti/staff/cerca', $name);
                    if ($id === null) {
                        $missing[] = "{$definition['label']} \u{ab}{$name}\u{bb}";
                    } else {
                        $ids[] = $id;
                    }
                }
                if ($ids === []) {
                    continue;
                }
                $value = implode(',', $ids);
            } elseif (isset($definition['options'])) {
                $match = null;
                foreach ($definition['options'] as $option) {
                    if (strcasecmp((string) $option, $value) === 0) {
                        $match = (string) $option;
                    }
                }
                if ($match === null) {
                    continue;
                }
                $value = $match;
            } else {
                $value = self::normalizeValue($definition['format'] ?? null, $value);
                if ($value === null) {
                    continue;
                }
            }

            $query[$key] = $value;
        }

        return [
            'type' => 'open',
            'href' => $this->baseUrl() . '/nuovo' . ($query !== [] ? '?' . http_build_query($query) : ''),
            'notice' => $missing !== [] ? 'Non ho trovato ' . implode(', ', $missing) . ': sceglilo nel form.' : null,
        ];
    }

    /** Date e ore dette a parole o in formati diversi, nel formato del campo; null se non si capisce. */
    private static function normalizeValue(?string $format, string $value): ?string
    {
        if (!in_array($format, ['date', 'datetime', 'time'], true)) {
            return $value;
        }
        $time = strtotime(str_replace('T', ' ', $value));
        if ($time === false) {
            return null;
        }

        return match ($format) {
            'date' => date('Y-m-d', $time),
            // Solo il giorno: si parte dalle 9.
            'datetime' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? date('Y-m-d', $time) . ' 09:00:00' : date('Y-m-d H:i:00', $time),
            'time' => date('H:i', $time),
        };
    }

    /** I campi che l'operatore compila in un form nuovo (non quelli di sistema o nascosti). */
    private function creatableFields(): array
    {
        $definitions = (new PackageManifest($this->packageName()))->entities()[$this->manifestEntityKey()]['fields'];
        $excluded = [...$this->systemManagedFields(), ...$this->hiddenFields()];

        return array_filter($definitions, static fn (string $key) => !in_array($key, $excluded, true), ARRAY_FILTER_USE_KEY);
    }

    private function valueHint(array $definition): string
    {
        $hint = (string) $definition['label'];
        if (isset($definition['options'])) {
            return $hint . ': ' . implode('/', $definition['options']);
        }
        if (isset($definition['autocomplete']) && empty($definition['autocomplete']['freeText'])) {
            return $hint . ', nome';
        }
        if (($definition['optionsSource'] ?? null) === 'staff') {
            return $hint . ', nomi separati da virgola';
        }

        return $hint . match ($definition['format'] ?? null) {
            'date' => ', AAAA-MM-GG',
            'datetime' => ', AAAA-MM-GG HH:MM',
            'time' => ', HH:MM',
            default => '',
        };
    }

    /**
     * La pagina di un elemento esistente: prima cio' che serve sapere
     * (detailComponents(), es. la scheda del cliente con servizi e
     * scadenze), poi il form per modificarlo. Un elemento nuovo ha solo
     * il form.
     *
     * @param array<string,string> $fieldErrors
     */
    public function buildPage(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        $detail = $model->id !== null ? $this->detailComponents($model) : [];

        // Con una scheda sopra, l'esito va in cima (si legge subito) e il
        // form resta chiuso; con errori sui campi resta aperto sul form.
        if ($detail !== [] && $message !== null && $fieldErrors === []) {
            $notice = ['type' => 'notice', 'tone' => $messageType === 'error' ? 'error' : 'success', 'text' => $message];

            return [$notice, ...$detail, $this->buildForm($model)];
        }

        return [...$detail, $this->buildForm($model, $message, $messageType, $fieldErrors)];
    }

    /** Componenti mostrati sopra il form di un elemento esistente - nessuno di default. */
    protected function detailComponents(AbstractModel $model): array
    {
        return [];
    }

    /** Titolo del form sotto una scheda di dettaglio (es. "Dati anagrafici" invece di "Modifica cliente"). */
    protected function formTitleBelowDetail(): ?string
    {
        return null;
    }

    /**
     * @param array<string,string> $fieldErrors fieldKey => messaggio, vedi App\Controller\AbstractEntityController::collectAndValidate()
     */
    public function buildForm(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        $isNew = $model->id === null;

        $title = $this->formTitle($isNew);
        if (!$isNew && $this->formTitleBelowDetail() !== null) {
            $title = $this->formTitleBelowDetail();
        }

        $fields = $this->fieldsFromManifest($model, $fieldErrors);

        return $this->form->toData([
            'entity' => $this->entityTag(),
            'title' => $title,
            // Sotto una scheda il form parte chiuso: si apre per modificare.
            'collapsed' => !$isNew && $this->formTitleBelowDetail() !== null && $fieldErrors === [] && $message === null,
            'action' => $isNew ? $this->baseUrl() . '/nuovo' : $this->baseUrl() . '/' . $model->id,
            'submitLabel' => $isNew ? 'Crea' : 'Salva',
            'fields' => $fields,
            'layout' => (new PackageManifest($this->packageName()))->layout($this->manifestEntityKey()),
            'secondaryActions' => $isNew ? [] : $this->allSecondaryActions($model),
            'message' => $message,
            'messageType' => $messageType,
            'context' => $this->formContext($model, $fields),
        ]);
    }

    /**
     * Per chi si compila il form, in testa: il cliente, se l'elemento ne
     * ha uno (un contatto, un appuntamento, una richiesta...) - si deve
     * capire subito che si sta lavorando su quel cliente, non solo
     * dal campo in mezzo agli altri.
     */
    protected function formContext(AbstractModel $model, array $fields): ?array
    {
        if ($this->manifestEntityKey() === 'customers' || !in_array('customer_id', array_column($fields, 'key'), true)
            || !in_array('customers', PackageCatalogService::installedNames(), true)) {
            return null;
        }
        $id = $this->fieldValue($model, 'customer_id');
        $customer = $id ? $this->toolContainer->get(CustomerRepository::class)->find((int) $id) : null;

        return [
            'label' => 'Per il cliente',
            'id' => $customer?->id,
            'name' => $customer?->displayName(),
            'key' => 'customer_id',
            'hrefPattern' => '/clienti/{id}',
        ];
    }

    /**
     * @param array<string,string> $fieldErrors
     * @return list<array{key:string,label:string,value:mixed,type:string,required:bool,width:string,error:?string}>
     */
    protected function fieldsFromManifest(AbstractModel $model, array $fieldErrors = []): array
    {
        $manifest = new PackageManifest($this->packageName());
        $definitions = $manifest->entities()[$this->manifestEntityKey()]['fields'];
        $systemManaged = $this->systemManagedFields();

        $fields = [];
        foreach ($definitions as $key => $definition) {
            if (in_array($key, $systemManaged, true)) {
                continue;
            }

            $format = $definition['format'] ?? null;

            $fields[] = [
                'key' => $key,
                'label' => $definition['label'],
                // "2026-10-21 14:30:00" dal database, "2026-10-21T14:30" per il campo data e ora del browser.
                'value' => $format === 'datetime' && is_string($this->fieldValue($model, $key))
                    ? str_replace(' ', 'T', substr((string) $this->fieldValue($model, $key), 0, 16))
                    : $this->fieldValue($model, $key),
                'type' => match ($format) {
                    'email' => 'email',
                    'integer' => 'number',
                    'date' => 'date',
                    'datetime' => 'datetime-local',
                    'time' => 'time',
                    default => 'text',
                },
                // Un campo con 'autocomplete' nel manifest si digita/
                // cerca invece di scrivere l'id a mano - vedi
                // Index\Controller\GeoController per l'unico endpoint di
                // ricerca costruito finora (comuni), lo stesso schema
                // vale per qualunque altro riferimento futuro.
                'inputType' => match (true) {
                    in_array($key, $this->hiddenFields(), true) => 'hidden',
                    isset($definition['optionsSource']) => 'checkboxes',
                    isset($definition['autocomplete']) => 'autocomplete',
                    isset($definition['options']) => 'select',
                    $format === 'boolean' => 'toggle',
                    ($definition['input'] ?? null) === 'textarea' => 'textarea',
                    default => null,
                },
                'autocompleteSource' => $definition['autocomplete']['source'] ?? null,
                // Il valore e' il testo (es. il CAP): i suggerimenti aiutano a scriverlo.
                'autocompleteFreeText' => (bool) ($definition['autocomplete']['freeText'] ?? false),
                'options' => $definition['options'] ?? null,
                'choices' => isset($definition['optionsSource']) ? $this->choices((string) $definition['optionsSource']) : null,
                // Il campo compare solo quando un altro ha uno di quei
                // valori (es. "ogni quanto" solo per un servizio
                // ricorrente): meno campi davanti, meno dubbi.
                'showIf' => $definition['showIf'] ?? null,
                'help' => $definition['help'] ?? null,
                // 'base' da solo non basta: un campo 'base' + 'nullable'
                // (es. tasks.entity_id, sempre colonna ma non sempre
                // valorizzato - vedi App\Package\PackageInstaller) e'
                // sempre presente ma non e' detto sia obbligatorio.
                'required' => ($definition['base'] ?? false) && !($definition['nullable'] ?? false),
                'width' => $this->inferWidth($definition),
                'error' => $fieldErrors[$key] ?? null,
            ];
        }

        return $fields;
    }

    /** Nome del campo (snake_case) -> proprieta' del model (camelCase), stessa trasformazione di AbstractModel::fromArray(). */
    private function fieldValue(AbstractModel $model, string $key): mixed
    {
        $property = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
        return $model->$property ?? null;
    }

    /**
     * Scelte per un campo a scelta multipla ('optionsSource' nel manifest,
     * valore salvato come "2,3"). 'staff' = i colleghi.
     *
     * @return list<array{value:string, label:string}>
     */
    protected function choices(string $source): array
    {
        if ($source === 'weekdays') {
            return array_map(
                static fn (int $d, string $label) => ['value' => (string) $d, 'label' => $label],
                range(1, 7),
                ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom']
            );
        }
        if ($source !== 'staff') {
            return [];
        }

        return array_map(
            static fn ($user) => ['value' => (string) $user->id, 'label' => trim("{$user->firstName} {$user->lastName}") ?: (string) $user->username],
            $this->toolContainer->get(\App\Repository\UserRepository::class)->findAll([], 'first_name')
        );
    }

    /** Campi che viaggiano col form ma non si vedono (es. a cosa e' collegata un'attivita'). */
    protected function hiddenFields(): array
    {
        return [];
    }

    /**
     * Larghezza suggerita dedotta dal formato/tipo SQL del campo - vedi
     * la stessa logica originale in EditLeadTool per il ragionamento
     * completo (CAP stretto, note larghe, ecc.). Un campo puo' avere
     * 'width' esplicito nel manifest per un caso fuori norma.
     */
    protected function inferWidth(array $definition): string
    {
        if (isset($definition['width'])) {
            return $definition['width'];
        }

        // Un campo autocomplete mostra un'etichetta testuale (es. "Torino
        // (TO)"), non il numero - largo come un campo di testo normale,
        // a prescindere dal formato del valore vero sotto (qui 'integer').
        if (isset($definition['autocomplete'])) {
            return 'half';
        }

        $format = $definition['format'] ?? null;
        if (isset($definition['options'])) {
            return 'third';
        }
        if (isset($definition['optionsSource'])) {
            return 'full';
        }

        $byFormat = [
            'time' => 'quarter',
            'boolean' => 'third',
            'email' => 'half',
            'phone' => 'third',
            'datetime' => 'half',
            'date' => 'third',
            'decimal' => 'third',
            'integer' => 'quarter',
        ];
        if (isset($byFormat[$format])) {
            return $byFormat[$format];
        }

        $sql = $definition['sql'] ?? '';
        if (str_starts_with($sql, 'TEXT')) {
            return 'full';
        }

        if (preg_match('/VARCHAR\((\d+)\)/', $sql, $matches) === 1) {
            $length = (int) $matches[1];

            return match (true) {
                $length <= 10 => 'quarter',
                $length <= 50 => 'third',
                $length <= 150 => 'half',
                default => 'full',
            };
        }

        return 'half';
    }

    protected function candidatesTable(array $models, string $query): array
    {
        $rows = array_map(static fn (AbstractModel $m) => $m->toDisplayArray(), $models);

        return $this->dataTable->toData([
            'entity' => $this->entityTag(),
            'title' => "Piu' risultati corrispondono a \u{ab}{$query}\u{bb}",
            'columns' => $this->candidateColumns(),
            'rows' => $rows,
            'actions' => [
                ['label' => 'Apri', 'href' => $this->baseUrl() . '/{id}', 'permission' => $this->requiredPermission()],
            ],
        ]);
    }

    protected function emptyResult(string $query): array
    {
        return $this->dataTable->toData([
            'title' => $this->formTitle(false),
            'columns' => [],
            'rows' => [],
            'emptyMessage' => $query !== ''
                ? "Nessun risultato per \u{ab}{$query}\u{bb}."
                : "Specifica un id o un testo da cercare.",
        ]);
    }
}
