<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Package\PackageManifest;
use App\Prompt\PromptToolInterface;
use App\Repository\AbstractRepository;
use App\Service\AuthService;
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

    public function __construct(Container $container)
    {
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

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => "Id, se gia' noto"],
                'query' => ['type' => 'string', 'description' => "Testo libero da cercare, se l'id non e' noto"],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $id = isset($input['id']) ? (int) $input['id'] : null;
        $query = trim((string) ($input['query'] ?? ''));

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

        return [$this->buildForm($model)];
    }

    /**
     * @param array<string,string> $fieldErrors fieldKey => messaggio, vedi App\Controller\AbstractEntityController::collectAndValidate()
     */
    public function buildForm(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        $isNew = $model->id === null;

        return $this->form->toData([
            'entity' => $this->entityTag(),
            'title' => $this->formTitle($isNew),
            'action' => $isNew ? $this->baseUrl() . '/nuovo' : $this->baseUrl() . '/' . $model->id,
            'submitLabel' => $isNew ? 'Crea' : 'Salva',
            'fields' => $this->fieldsFromManifest($model, $fieldErrors),
            'layout' => (new PackageManifest($this->packageName()))->layout($this->manifestEntityKey()),
            'secondaryActions' => $isNew ? [] : $this->allSecondaryActions($model),
            'message' => $message,
            'messageType' => $messageType,
        ]);
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
                'value' => $this->fieldValue($model, $key),
                'type' => $format === 'email' ? 'email' : ($format === 'integer' ? 'number' : 'text'),
                // Un campo con 'autocomplete' nel manifest si digita/
                // cerca invece di scrivere l'id a mano - vedi
                // Index\Controller\GeoController per l'unico endpoint di
                // ricerca costruito finora (comuni), lo stesso schema
                // vale per qualunque altro riferimento futuro.
                'inputType' => isset($definition['autocomplete'])
                    ? 'autocomplete'
                    : (($definition['input'] ?? null) === 'textarea' ? 'textarea' : null),
                'autocompleteSource' => $definition['autocomplete']['source'] ?? null,
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
        $byFormat = [
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
