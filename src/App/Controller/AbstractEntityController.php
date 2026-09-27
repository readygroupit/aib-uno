<?php

declare(strict_types=1);

namespace App\Controller;

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
            $modelClass = $this->repository()->modelClass();
            $model = $modelClass::fromArray($this->defaultsOnCreate());

            return $this->renderPage([$tool->buildForm($model)], ['title' => $this->newPageTitle()]);
        }

        [$data, $fieldErrors] = $this->collectAndValidate();

        if ($fieldErrors !== []) {
            $modelClass = $this->repository()->modelClass();
            $components = [$tool->buildForm($modelClass::fromArray($data), null, null, $fieldErrors)];
        } else {
            $repo = $this->repository();
            $id = $repo->insert($data);
            $components = [$tool->buildForm($repo->find($id), $this->createdMessage(), 'success')];
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
            $components = [$tool->buildForm($merged, null, null, $fieldErrors)];
        } else {
            $repo->update($id, $data);
            $components = [$tool->buildForm($repo->find($id), $this->updatedMessage(), 'success')];
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

        $components = $this->container->get($this->listToolClass())->execute(['page' => 1]);

        return $this->renderPage($components, ['title' => $this->listPageTitle()]);
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
            $raw = $this->request->get($key, null);
            if ($raw === null) {
                continue;
            }

            $value = trim((string) $raw);
            $data[$key] = $value !== '' ? $value : null;

            if ($value === '') {
                // 'base' da solo non basta - vedi la stessa nota in
                // App\Prompt\Tool\AbstractEditEntityTool::fieldsFromManifest().
                if (($definition['base'] ?? false) && !($definition['nullable'] ?? false)) {
                    $fieldErrors[$key] = ($definition['label'] ?? $key) . " e' obbligatorio.";
                }
                continue;
            }

            if (isset($definition['format'])) {
                $error = FieldValidator::validate($definition['format'], $value, $definition['formatOptions'] ?? []);
                if ($error !== null) {
                    $fieldErrors[$key] = $error;
                }
            }
        }

        return [$data, $fieldErrors];
    }
}
