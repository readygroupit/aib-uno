<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\AbstractModel;
use App\Package\PackageManifest;
use App\Prompt\Tool\AbstractEditEntityTool;
use App\Repository\AbstractRepository;
use App\Validation\FieldValidator;

/**
 * Base comune per "lista + crea + modifica" - era UsersController/
 * PermissionsController/LeadsController quasi identici (GET mostra,
 * POST rielabora sulla STESSA rotta, mai un redirect - vedi il commento
 * originale in UsersController per il perche'). Una sottoclasse dichiara
 * solo repository/tool/permesso/titoli; la validazione (obbligatorieta'
 * + formato) si legge dal manifest una volta sola qui, non ripetuta a
 * mano per ogni pacchetto.
 *
 * Stessa nota di AbstractListEntityTool: Users/Permessi/Leads non sono
 * stati riportati a questa base, hanno gia' una loro implementazione
 * funzionante - questa serve ai pacchetti da qui in avanti.
 */
abstract class AbstractEntityController extends AuthController
{
    abstract protected function repositoryClass(): string;

    /** @return class-string<AbstractEditEntityTool> */
    abstract protected function editToolClass(): string;

    /** @return class-string */
    abstract protected function listToolClass(): string;

    abstract protected function packageName(): string;

    protected function manifestEntityKey(): string
    {
        return $this->packageName();
    }

    abstract protected function listPageTitle(): string;

    abstract protected function newPageTitle(): string;

    abstract protected function editPageTitle(): string;

    /** Campi impostati d'ufficio alla creazione, non dal form (es. uno stato iniziale) - key => value. */
    protected function defaultsOnCreate(): array
    {
        return [];
    }

    protected function createdMessage(): string
    {
        return 'Creato con successo.';
    }

    protected function updatedMessage(): string
    {
        return 'Modifiche salvate.';
    }

    /**
     * Non tutti hanno gli stessi poteri su una CRUD (segnalato
     * dall'utente): index/new/edit/delete chiedono ciascuno il proprio
     * permesso granulare invece di un unico "X.manage" per l'intero
     * controller - vedi AuthController::requiredPermissionForAction().
     * Convenzione fissa: {packageName}.view/create/edit/delete, seminata
     * in db/seed.sql per ogni pacchetto su questa base.
     */
    protected function requiredPermissionForAction(string $action): ?string
    {
        $package = $this->packageName();

        return match ($action) {
            'index' => "{$package}.view",
            'new' => "{$package}.create",
            'edit' => "{$package}.edit",
            'delete' => "{$package}.delete",
            default => null,
        };
    }

    public function indexAction(): ?string
    {
        $page = max(1, (int) $this->param('page', 1));

        $components = $this->container->get($this->listToolClass())->execute(['page' => $page]);

        return $this->renderPage($components, ['title' => $this->listPageTitle()]);
    }

    public function newAction(): ?string
    {
        /** @var AbstractEditEntityTool $tool */
        $tool = $this->container->get($this->editToolClass());

        if ($this->request->getMethod() !== 'POST') {
            // defaultsOnCreate() precompila il form (es. stage='nuovo'),
            // non solo la riga inserita: un campo 'base' vuoto e'
            // obbligatorio (vedi collectAndValidate()) - se il default
            // fosse applicato solo dopo, alla insert(), la richiesta si
            // sarebbe gia' fermata prima per "campo obbligatorio mancante"
            // anche quando esiste un default sensato (successo davvero,
            // vedi il pacchetto 'tasks').
            // ?customer_id=5 dal pulsante "Nuovo servizio" della scheda
            // cliente: il form arriva gia' collegato, niente da cercare.
            $modelClass = $this->repository()->modelClass();
            $model = $modelClass::fromArray($this->prefillFromQuery() + $this->defaultsOnCreate());

            return $this->renderPage([$tool->buildForm($model)], ['title' => $this->newPageTitle()]);
        }

        [$data, $fieldErrors] = $this->collectAndValidate();

        if ($fieldErrors !== []) {
            $modelClass = $this->repository()->modelClass();
            $components = [$tool->buildForm($modelClass::fromArray($data), null, null, $fieldErrors)];
        } else {
            $repo = $this->repository();
            foreach ($this->defaultsOnCreate() as $key => $default) {
                $data[$key] ??= $default;
            }
            $id = $repo->insert($data);
            $this->afterCreate($id);
            $components = $this->componentsAfterCreate($id);
        }

        return $this->renderPage($components, ['title' => $this->newPageTitle()]);
    }

    public function editAction(): ?string
    {
        $id = (int) $this->param('id');

        /** @var AbstractEditEntityTool $tool */
        $tool = $this->container->get($this->editToolClass());
        $repo = $this->repository();

        if ($this->request->getMethod() !== 'POST') {
            return $this->renderPage($tool->execute(['id' => $id]), ['title' => $this->editPageTitle()]);
        }

        $model = $repo->find($id);
        if ($model === null) {
            return $this->renderPage($tool->execute(['id' => $id]), ['title' => $this->editPageTitle()]);
        }

        [$data, $fieldErrors] = $this->collectAndValidate();

        if ($fieldErrors !== []) {
            // I valori appena digitati (non quelli salvati prima),
            // altrimenti un errore su UN campo farebbe sparire anche le
            // modifiche fatte bene sugli altri.
            $modelClass = $repo->modelClass();
            $merged = $modelClass::fromArray(array_merge($model->toArray(), $data));
            $components = $tool->buildPage($merged, null, null, $fieldErrors);
        } else {
            $repo->update($id, $data);
            $message = $this->afterUpdate($id, $model) ?? $this->updatedMessage();
            $components = $tool->buildPage($repo->find($id), $message, 'success');
        }

        return $this->renderPage($components, ['title' => $this->editPageTitle()]);
    }

    /**
     * Solo POST (la rotta '{path}/:id/elimina' e' raggiunta esclusivamente
     * dal pulsante "Elimina" del form, un formaction+fetch POST - vedi
     * AbstractEditEntityTool::buildForm() - mai un link GET: cancellare
     * qualcosa non deve poter succedere per un semplice click/prefetch).
     * Soft delete (AbstractRepository::delete(), mai una DELETE SQL vera),
     * poi la stessa identica lista di indexAction() cosi' l'operatore
     * vede subito il risultato invece di restare su un form ormai vuoto.
     */
    public function deleteAction(): ?string
    {
        $id = (int) $this->param('id');
        $this->repository()->delete($id);
        $this->afterDelete($id);

        $components = $this->container->get($this->listToolClass())->execute(['page' => 1]);

        return $this->renderPage($components, ['title' => $this->listPageTitle()]);
    }

    /** Dopo l'inserimento (es. generare lo scadenzario di un servizio venduto). */
    protected function afterCreate(int $id): void
    {
    }

    /** Dopo l'eliminazione (logica). */
    protected function afterDelete(int $id): void
    {
    }

    /**
     * Controlli che legano piu' campi (es. la fine dopo l'inizio): campo =>
     * messaggio. $data ha solo i campi arrivati dal form.
     *
     * @return array<string,string>
     */
    protected function crossFieldErrors(array $data): array
    {
        return [];
    }

    /** Dopo il salvataggio: puo' tornare un messaggio diverso da quello standard. */
    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        return null;
    }

    /** Cosa mostrare dopo una creazione riuscita: di norma la scheda appena creata. */
    protected function componentsAfterCreate(int $id): array
    {
        /** @var AbstractEditEntityTool $tool */
        $tool = $this->container->get($this->editToolClass());

        return $tool->buildPage($this->repository()->find($id), $this->createdMessage(), 'success');
    }

    /** Solo campi del manifest, solo valori semplici: la query string non puo' scrivere altro. */
    private function prefillFromQuery(): array
    {
        if ($this->request->getMethod() !== 'GET') {
            return [];
        }

        $fields = (new PackageManifest($this->packageName()))->entities()[$this->manifestEntityKey()]['fields'];
        $prefill = [];
        foreach (array_keys($fields) as $key) {
            $value = $this->request->get($key);
            if (is_string($value) && $value !== '') {
                $prefill[$key] = $value;
            }
        }

        return $prefill;
    }

    /**
     * Il valore di un campo come arriva dal form (null se il form non lo
     * manda). Un controller puo' leggerlo a modo suo, es. gli orari: piu'
     * persone scelte insieme nello stesso campo.
     */
    protected function requestValue(string $key): mixed
    {
        return $this->request->get($key, null);
    }

    protected function repository(): AbstractRepository
    {
        return $this->container->get($this->repositoryClass());
    }

    /**
     * Un solo giro sul manifest per costruire sia i dati da salvare sia
     * gli errori per campo: un campo 'base' => true vuoto e' un errore
     * "obbligatorio", un campo con 'format' viene passato a
     * FieldValidator. Solo i campi REALMENTE presenti in questa
     * richiesta finiscono in $data (vedi il confronto con null, non ''):
     * un campo assente dal form (es. gestito da un'azione dedicata) non
     * va mai sovrascritto a null solo perche' il form che lo modifica
     * legittimamente non lo includeva.
     *
     * @return array{0: array<string,?string>, 1: array<string,string>}
     */
    protected function collectAndValidate(): array
    {
        $fields = (new PackageManifest($this->packageName()))->entities()[$this->manifestEntityKey()]['fields'];

        $data = [];
        $fieldErrors = [];
        foreach ($fields as $key => $definition) {
            $raw = $this->requestValue($key);
            if ($raw === null) {
                continue;
            }

            $value = trim((string) $raw);
            // "1.250,50" scritto all'italiana: si salva 1250.50.
            if (($definition['format'] ?? null) === 'decimal' && $value !== '') {
                $value = str_contains($value, ',') ? str_replace(['.', ','], ['', '.'], $value) : $value;
            }
            // Campo data e ora del browser: "2026-10-21T14:30" -> "2026-10-21 14:30:00".
            if (($definition['format'] ?? null) === 'datetime' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) === 1) {
                $value = str_replace('T', ' ', $value) . ':00';
            }
            if (($definition['format'] ?? null) === 'boolean') {
                $value = in_array($value, ['1', 'on', 'true'], true) ? '1' : '0';
            }
            $data[$key] = $value !== '' ? $value : null;

            if ($value === '') {
                // 'base' da solo non basta - vedi la stessa nota in
                // App\Prompt\Tool\AbstractEditEntityTool::fieldsFromManifest().
                if (($definition['base'] ?? false) && !($definition['nullable'] ?? false)) {
                    $fieldErrors[$key] = ($definition['label'] ?? $key) . " e' obbligatorio.";
                }
                continue;
            }

            if (isset($definition['options']) && !in_array($value, $definition['options'], true)) {
                $fieldErrors[$key] = 'Scegli uno dei valori proposti.';
                continue;
            }

            if (isset($definition['format'])) {
                $error = FieldValidator::validate($definition['format'], $value, $definition['formatOptions'] ?? []);
                if ($error !== null) {
                    $fieldErrors[$key] = $error;
                }
            }
        }

        if ($fieldErrors === []) {
            $fieldErrors = $this->crossFieldErrors($data);
        }

        return [$data, $fieldErrors];
    }
}
