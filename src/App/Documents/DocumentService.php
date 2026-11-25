<?php

declare(strict_types=1);

namespace App\Documents;

use App\Connector\ConnectorException;
use App\Connector\GoogleDriveConnector;
use App\Core\Container;
use App\Model\Customer;
use App\Model\Document;
use App\Model\DocumentFolder;
use App\Model\WorkOrder;
use App\Model\WorkOrderType;
use App\Package\PackageCatalogService;
use App\Repository\CustomerRepository;
use App\Repository\DocumentFolderRepository;
use App\Repository\DocumentRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use App\Repository\WorkOrderTypeRepository;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;

/**
 * Cartelle e file del Drive dell'agenzia, gestiti dal gestionale.
 *
 * - Dal gestionale al Drive: cartelle di cliente e progetto (con le
 *   sottocartelle del processo), cartelle nuove, file caricati, nomi
 *   cambiati, cestino. Prima si fa sul Drive, poi si scrive qui: se il
 *   Drive dice di no, qui non cambia niente.
 * - Dal Drive al gestionale (sync()): il primo giro legge tutto l'albero
 *   della cartella principale, poi solo le modifiche (changes del Drive,
 *   punto di ripartenza in 'documents.page_token'). Nuovi, rinominati,
 *   spostati, nel cestino o tolti: qui si aggiornano, mai si cancellano
 *   righe (trashed_at), e un file ripristinato sul Drive torna.
 *
 * Cliente e progetto passano dalla cartella ai file che contiene; una
 * cartella in cima con il nome di un cliente e' la sua.
 */
final class DocumentService
{
    private const TOKEN_KEY = 'documents.page_token';

    private Container $container;
    private ?DocumentStoreInterface $store = null;
    private ?array $customerNames = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public static function isInstalled(): bool
    {
        return in_array('documents', PackageCatalogService::installedNames(), true);
    }

    /** Per le prove: un Drive finto al posto di quello vero. */
    public function setStore(DocumentStoreInterface $store): void
    {
        $this->store = $store;
    }

    public function store(): DocumentStoreInterface
    {
        return $this->store ??= $this->container->get(GoogleDriveConnector::class);
    }

    public function isReady(): bool
    {
        return self::isInstalled() && $this->store()->isReady() && $this->store()->rootFolderId() !== null;
    }

    /** La riga della cartella principale (la crea la prima volta). */
    public function root(): ?DocumentFolder
    {
        $externalId = $this->store()->rootFolderId();
        if ($externalId === null) {
            return null;
        }
        $folder = $this->folders()->findByExternalId($externalId);
        if ($folder === null) {
            $id = $this->folders()->insert(['external_id' => $externalId, 'parent_id' => null, 'name' => 'Documenti', 'synced_at' => date('Y-m-d H:i:s')]);
            $folder = $this->folders()->find($id);
        }

        return $folder;
    }

    /**
     * La cartella del cliente: quella indicata nella sua scheda (se c'e'),
     * oppure quella in cima con il suo nome, oppure la crea.
     */
    public function ensureCustomerFolder(int $customerId): ?DocumentFolder
    {
        $existing = $this->folders()->findForCustomerRoot($customerId);
        if ($existing !== null) {
            return $existing;
        }
        $customer = $this->container->get(CustomerRepository::class)->find($customerId);
        $root = $this->root();
        if (!$customer instanceof Customer || $root === null) {
            return null;
        }
        $name = $this->safeName($customer->displayName());

        $linked = property_exists($customer, 'driveFolderUrl') && trim((string) $customer->driveFolderUrl) !== ''
            ? GoogleDriveConnector::folderIdFrom((string) $customer->driveFolderUrl)
            : null;
        if ($linked !== null) {
            $row = $this->folders()->findByExternalId($linked);
            if ($row !== null) {
                $this->folders()->update((int) $row->id, ['customer_id' => $customerId, 'trashed_at' => null]);

                return $this->folders()->find((int) $row->id);
            }
            $id = $this->folders()->insert(['external_id' => $linked, 'parent_id' => $root->id, 'name' => $name, 'customer_id' => $customerId, 'synced_at' => date('Y-m-d H:i:s')]);

            return $this->folders()->find($id);
        }

        foreach ($this->folders()->children((int) $root->id) as $child) {
            if (mb_strtolower(trim((string) $child->name)) === mb_strtolower($name)) {
                $this->folders()->update((int) $child->id, ['customer_id' => $customerId]);

                return $this->folders()->find((int) $child->id);
            }
        }

        return $this->createFolder((int) $root->id, $name, ['customer_id' => $customerId]);
    }

    /**
     * Le cartelle di un progetto: Cliente / Progetto / sottocartelle del
     * processo. Quelle che ci sono gia' restano, si aggiungono le mancanti.
     */
    public function ensureProjectFolders(int $workOrderId): ?DocumentFolder
    {
        $order = $this->container->get(WorkOrderRepository::class)->find($workOrderId);
        if (!$order instanceof WorkOrder || $order->customerId === null) {
            return null;
        }
        $project = $this->folders()->findForWorkOrder($workOrderId);
        if ($project === null) {
            $customerFolder = $this->ensureCustomerFolder((int) $order->customerId);
            if ($customerFolder === null) {
                return null;
            }
            $project = $this->createFolder((int) $customerFolder->id, $this->safeName((string) $order->title), ['work_order_id' => $workOrderId]);
        }

        $type = $order->workOrderTypeId !== null ? $this->container->get(WorkOrderTypeRepository::class)->find((int) $order->workOrderTypeId) : null;
        $template = $type instanceof WorkOrderType ? array_values(array_filter(array_map('trim', explode("\n", (string) $type->folderTemplate)))) : [];
        $present = array_map(static fn (DocumentFolder $f) => mb_strtolower(trim((string) $f->name)), $this->folders()->children((int) $project->id));
        foreach ($template as $name) {
            if (!in_array(mb_strtolower($name), $present, true)) {
                $this->createFolder((int) $project->id, $this->safeName($name));
            }
        }

        return $project;
    }

    /** @param array<string,mixed> $extra campi in piu' (cliente, progetto) */
    public function createFolder(int $parentId, string $name, array $extra = []): DocumentFolder
    {
        $parent = $this->folders()->find($parentId);
        if (!$parent instanceof DocumentFolder) {
            throw new ConnectorException('Cartella non trovata.');
        }
        $name = $this->safeName($name);
        $item = $this->store()->createFolder($name, (string) $parent->externalId);
        $id = $this->folders()->insert($extra + [
            'external_id' => $item['id'],
            'parent_id' => $parent->id,
            'name' => $item['name'] ?: $name,
            'customer_id' => $parent->customerId,
            'work_order_id' => $parent->workOrderId,
            'web_link' => $item['webLink'] ?? null,
            'drive_modified_at' => $item['modifiedAt'] ?? null,
            'synced_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->folders()->find($id);
    }

    /** Il file va sul Drive; qui le sue informazioni. */
    public function upload(int $folderId, string $localPath, string $name, string $mime, ?string $description = null, ?string $tags = null): Document
    {
        $folder = $this->folders()->find($folderId);
        if (!$folder instanceof DocumentFolder) {
            throw new ConnectorException('Cartella non trovata.');
        }
        $name = $this->safeName($name);
        $item = $this->store()->uploadFile($localPath, $name, $mime !== '' ? $mime : 'application/octet-stream', (string) $folder->externalId);
        $id = $this->documents()->insert([
            'external_id' => $item['id'],
            'folder_id' => $folder->id,
            'name' => $item['name'] ?: $name,
            'mime_type' => $item['mime'] ?: $mime,
            'size_bytes' => $item['size'] ?? filesize($localPath),
            'web_link' => $item['webLink'] ?? null,
            'md5' => $item['md5'] ?? null,
            'drive_modified_at' => $item['modifiedAt'] ?? date('Y-m-d H:i:s'),
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
            'tags' => $tags !== null && trim($tags) !== '' ? trim($tags) : null,
            'customer_id' => $folder->customerId,
            'work_order_id' => $folder->workOrderId,
            'uploaded_by' => $this->container->get(AuthService::class)->currentUserId(),
            'origin' => 'sito',
            'synced_at' => date('Y-m-d H:i:s'),
        ]);
        if ($folder->workOrderId !== null) {
            $this->container->get(WorkOrderRepository::class)->update((int) $folder->workOrderId, ['last_activity_at' => date('Y-m-d H:i:s')]);
        }

        return $this->documents()->find($id);
    }

    /**
     * Un file caricato da una fase del progetto. Con il Drive collegato va
     * subito nella cartella della fase (la "Cartella dei file" del
     * processo, es. 03 Bozze, creata se manca); senza, resta qui in
     * attesa (storage/uploads, fuori dalla cartella pubblica) e parte da
     * solo quando il Drive c'e' (pushPending(), dal giro di sync).
     */
    public function uploadForTask(\App\Model\Task $task, string $localPath, string $name, string $mime, ?string $description = null): Document
    {
        if ($task->entityType !== 'work_orders' || $task->entityId === null) {
            throw new ConnectorException('Si caricano file solo dalle fasi di un progetto.');
        }
        $order = $this->container->get(WorkOrderRepository::class)->find((int) $task->entityId);
        if (!$order instanceof WorkOrder) {
            throw new ConnectorException('Progetto non trovato.');
        }
        if ($this->isReady()) {
            $folder = $this->taskFolder($task, $order);
            if ($folder !== null) {
                $document = $this->upload((int) $folder->id, $localPath, $name, $mime, $description);
                $this->documents()->update((int) $document->id, ['task_id' => $task->id]);

                return $this->documents()->find((int) $document->id);
            }
        }

        // Senza Drive: il file resta nel gestionale, in attesa.
        $dir = ROOT_PATH . '/storage/uploads/tasks/' . (int) $task->id;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new ConnectorException('Non riesco a salvare il file.');
        }
        $safe = $this->safeName($name);
        $target = $dir . '/' . bin2hex(random_bytes(6)) . '_' . preg_replace('/[^A-Za-z0-9._ -]/', '_', $safe);
        if (!(is_uploaded_file($localPath) ? move_uploaded_file($localPath, $target) : copy($localPath, $target))) {
            throw new ConnectorException('Non riesco a salvare il file.');
        }
        $id = $this->documents()->insert([
            'external_id' => 'local:' . bin2hex(random_bytes(8)),
            'folder_id' => null,
            'name' => $safe,
            'mime_type' => $mime !== '' ? $mime : (mime_content_type($target) ?: 'application/octet-stream'),
            'size_bytes' => filesize($target) ?: null,
            'web_link' => null,
            'drive_modified_at' => date('Y-m-d H:i:s'),
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
            'customer_id' => $order->customerId,
            'work_order_id' => $order->id,
            'task_id' => $task->id,
            'local_path' => $target,
            'uploaded_by' => $this->container->get(AuthService::class)->currentUserId(),
            'origin' => 'sito',
            'synced_at' => date('Y-m-d H:i:s'),
        ]);
        $this->container->get(WorkOrderRepository::class)->update((int) $order->id, ['last_activity_at' => date('Y-m-d H:i:s')]);

        return $this->documents()->find($id);
    }

    /**
     * I file caricati quando il Drive non c'era: ora partono, ognuno nella
     * cartella della sua fase. @return int file mandati
     */
    public function pushPending(): int
    {
        if (!$this->isReady()) {
            return 0;
        }
        $sent = 0;
        foreach ($this->documents()->findPendingLocal() as $document) {
            $task = $document->taskId !== null ? $this->container->get(\App\Repository\TaskRepository::class)->find((int) $document->taskId) : null;
            $order = $document->workOrderId !== null ? $this->container->get(WorkOrderRepository::class)->find((int) $document->workOrderId) : null;
            if (!$order instanceof WorkOrder || !is_file((string) $document->localPath)) {
                continue;
            }
            $folder = $task instanceof \App\Model\Task ? $this->taskFolder($task, $order) : $this->ensureProjectFolders((int) $order->id);
            if ($folder === null) {
                continue;
            }
            $item = $this->store()->uploadFile((string) $document->localPath, (string) $document->name, (string) ($document->mimeType ?: 'application/octet-stream'), (string) $folder->externalId);
            $this->documents()->update((int) $document->id, [
                'external_id' => $item['id'],
                'folder_id' => $folder->id,
                'web_link' => $item['webLink'] ?? null,
                'md5' => $item['md5'] ?? null,
                'local_path' => null,
                'synced_at' => date('Y-m-d H:i:s'),
            ]);
            @unlink((string) $document->localPath);
            $sent++;
        }

        return $sent;
    }

    /** I file di una fase, per la sua pagina. */
    public function filesForTask(int $taskId): array
    {
        $names = [];
        foreach ($this->container->get(UserRepository::class)->findAll() as $user) {
            $names[(int) $user->id] = trim("{$user->firstName} {$user->lastName}");
        }

        return array_map(static fn (Document $d) => [
            'name' => (string) $d->name,
            'href' => $d->localPath !== null ? '/documenti/file/' . $d->id . '/scarica' : ($d->webLink ?: '/documenti/file/' . $d->id . '/scarica'),
            'pending' => $d->localPath !== null,
            'description' => $d->description,
            'meta' => trim(implode(' \u{b7} ', array_filter([
                $d->uploadedBy !== null ? ($names[(int) $d->uploadedBy] ?? null) : null,
                $d->driveModifiedAt !== null ? F::shortDate(substr((string) $d->driveModifiedAt, 0, 10), date('Y-m-d')) : null,
                $d->sizeBytes ? self::size((int) $d->sizeBytes) : null,
            ]))),
            'image' => str_starts_with((string) $d->mimeType, 'image/'),
        ], $this->documents()->findForTask($taskId));
    }

    /** La cartella di una fase dentro quella del progetto (creata se manca). */
    private function taskFolder(\App\Model\Task $task, WorkOrder $order): ?DocumentFolder
    {
        $project = $this->ensureProjectFolders((int) $order->id);
        if ($project === null) {
            return null;
        }
        $step = $task->sourceStepId !== null ? $this->container->get(\App\Repository\WorkOrderTypeStepRepository::class)->find((int) $task->sourceStepId) : null;
        $name = trim((string) ($step?->folderName ?? ''));
        if ($name === '') {
            return $project;
        }
        foreach ($this->folders()->children((int) $project->id) as $child) {
            if (mb_strtolower(trim((string) $child->name)) === mb_strtolower($name) && $child->trashedAt === null) {
                return $child;
            }
        }

        return $this->createFolder((int) $project->id, $name);
    }

    public function renameFolder(int $id, string $name): void
    {
        $folder = $this->folders()->find($id);
        if ($folder instanceof DocumentFolder) {
            $this->store()->rename((string) $folder->externalId, $this->safeName($name));
            $this->folders()->update($id, ['name' => $this->safeName($name)]);
        }
    }

    public function renameFile(int $id, string $name): void
    {
        $file = $this->documents()->find($id);
        if ($file instanceof Document) {
            $this->store()->rename((string) $file->externalId, $this->safeName($name));
            $this->documents()->update($id, ['name' => $this->safeName($name)]);
        }
    }

    /** Nel cestino del Drive (si recupera da li' per 30 giorni), con tutto quello che contiene. */
    public function trashFolder(int $id): ?DocumentFolder
    {
        $folder = $this->folders()->find($id);
        $root = $this->root();
        if (!$folder instanceof DocumentFolder || ($root !== null && (int) $root->id === $id)) {
            return null;
        }
        $this->store()->trash((string) $folder->externalId);
        $this->trashLocally($folder);

        return $folder;
    }

    public function trashFile(int $id): ?Document
    {
        $file = $this->documents()->find($id);
        if (!$file instanceof Document) {
            return null;
        }
        $this->store()->trash((string) $file->externalId);
        $this->documents()->update($id, ['trashed_at' => date('Y-m-d H:i:s')]);

        return $file;
    }

    /**
     * Dal Drive al gestionale.
     *
     * @return array{mode:string, folders:int, files:int, trashed:int}
     */
    public function sync(bool $full = false): array
    {
        // Prima partono i file caricati quando il Drive non c'era.
        $this->pushPending();
        $root = $this->root();
        if ($root === null) {
            return ['mode' => 'nessuna cartella', 'folders' => 0, 'files' => 0, 'trashed' => 0];
        }
        $settings = $this->container->get(SettingRepository::class);
        $hasSettings = $settings->tableExists();
        $token = $hasSettings ? $settings->value(self::TOKEN_KEY) : null;

        if ($full || $token === null || $token === '') {
            // Il punto di ripartenza prima del giro: quello che cambia durante non si perde.
            $start = $this->store()->startPageToken();
            $result = $this->fullWalk($root);
            if ($hasSettings) {
                $settings->set(self::TOKEN_KEY, $start);
            }

            return ['mode' => 'tutto'] + $result;
        }

        try {
            $changes = $this->store()->changes($token);
        } catch (ConnectorException) {
            // Punto di ripartenza scaduto: si rilegge tutto.
            return $this->sync(true);
        }
        $result = $this->applyChanges($changes['items']);
        if ($hasSettings) {
            $settings->set(self::TOKEN_KEY, $changes['next']);
        }

        return ['mode' => 'modifiche'] + $result;
    }

    /** @return array{folders:int, files:int, trashed:int} */
    private function fullWalk(DocumentFolder $root): array
    {
        $counts = ['folders' => 0, 'files' => 0, 'trashed' => 0];
        $queue = [$root];
        $walked = [];
        while ($queue !== []) {
            /** @var DocumentFolder $folder */
            $folder = array_shift($queue);
            if (isset($walked[(int) $folder->id])) {
                continue;
            }
            $walked[(int) $folder->id] = true;
            try {
                $items = iterator_to_array($this->store()->listChildren((string) $folder->externalId), false);
            } catch (ConnectorException) {
                // Non c'e' piu' (o non e' piu' raggiungibile): nel cestino qui, con il contenuto.
                if ((int) $folder->id !== (int) $root->id) {
                    $this->trashLocally($folder);
                    $counts['trashed']++;
                }
                continue;
            }
            $seenFolders = [];
            $seenFiles = [];
            foreach ($items as $item) {
                if ($item['isFolder']) {
                    $row = $this->upsertFolder($item, $folder);
                    $seenFolders[(int) $row->id] = true;
                    $queue[] = $row;
                    $counts['folders']++;
                } else {
                    $row = $this->upsertFile($item, $folder);
                    $seenFiles[(int) $row->id] = true;
                    $counts['files']++;
                }
            }
            foreach ($this->folders()->children((int) $folder->id) as $child) {
                if (isset($seenFolders[(int) $child->id])) {
                    continue;
                }
                if ($this->isAnchor((string) $child->externalId) && (int) $folder->id === (int) $root->id) {
                    // La cartella del cliente scritta nella sua scheda puo' stare fuori da qui: si legge da sola.
                    $queue[] = $child;
                    continue;
                }
                $this->trashLocally($child);
                $counts['trashed']++;
            }
            foreach ($this->documents()->inFolder((int) $folder->id) as $file) {
                if (!isset($seenFiles[(int) $file->id])) {
                    $this->documents()->update((int) $file->id, ['trashed_at' => date('Y-m-d H:i:s')]);
                    $counts['trashed']++;
                }
            }
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return array{folders:int, files:int, trashed:int}
     */
    private function applyChanges(array $items): array
    {
        $counts = ['folders' => 0, 'files' => 0, 'trashed' => 0];
        $folders = [];
        $files = [];
        foreach ($items as $item) {
            if (!empty($item['removed']) || !empty($item['trashed'])) {
                $counts['trashed'] += $this->trashByExternalId((string) $item['id']);
            } elseif (!empty($item['isFolder'])) {
                $folders[] = $item;
            } else {
                $files[] = $item;
            }
        }

        // Le cartelle in piu' passaggi: una nuova dentro un'altra nuova arriva al giro dopo.
        do {
            $progress = false;
            $known = $this->folders()->liveExternalIds();
            foreach ($folders as $key => $item) {
                $parentId = $this->knownParent($item, $known);
                if ($parentId !== null) {
                    $this->upsertFolder($item, $this->folders()->find($parentId));
                    $counts['folders']++;
                    unset($folders[$key]);
                    $progress = true;
                }
            }
        } while ($progress && $folders !== []);
        // Quelle rimaste stanno fuori dalle cartelle che seguiamo: se le conoscevamo, sono state spostate via.
        foreach ($folders as $item) {
            if (!$this->isAnchor((string) $item['id'])) {
                $counts['trashed'] += $this->trashByExternalId((string) $item['id']);
            }
        }

        $known = $this->folders()->liveExternalIds();
        foreach ($files as $item) {
            $parentId = $this->knownParent($item, $known);
            if ($parentId !== null) {
                $this->upsertFile($item, $this->folders()->find($parentId));
                $counts['files']++;
            } else {
                $counts['trashed'] += $this->trashByExternalId((string) $item['id']);
            }
        }

        return $counts;
    }

    /** @param array<string,int> $known */
    private function knownParent(array $item, array $known): ?int
    {
        foreach ($item['parents'] ?? [] as $parent) {
            if (isset($known[(string) $parent])) {
                return $known[(string) $parent];
            }
        }

        return null;
    }

    /** Una cartella collegata a mano (quella di un cliente): resta anche se sta fuori dalla cartella principale. */
    private function isAnchor(string $externalId): bool
    {
        $row = $this->folders()->findByExternalId($externalId);
        if ($row === null || $row->customerId === null || $row->workOrderId !== null) {
            return false;
        }
        $customer = $this->container->get(CustomerRepository::class)->find((int) $row->customerId);

        return $customer instanceof Customer && property_exists($customer, 'driveFolderUrl')
            && GoogleDriveConnector::folderIdFrom((string) $customer->driveFolderUrl) === $externalId;
    }

    private function trashByExternalId(string $externalId): int
    {
        if ($externalId === $this->store()->rootFolderId()) {
            return 0;
        }
        $folder = $this->folders()->findByExternalId($externalId);
        if ($folder !== null && $folder->trashedAt === null) {
            $this->trashLocally($folder);

            return 1;
        }
        $file = $this->documents()->findByExternalId($externalId);
        if ($file !== null && $file->trashedAt === null) {
            $this->documents()->update((int) $file->id, ['trashed_at' => date('Y-m-d H:i:s')]);

            return 1;
        }

        return 0;
    }

    private function trashLocally(DocumentFolder $folder): void
    {
        $ids = $this->folders()->descendantIds((int) $folder->id);
        $now = date('Y-m-d H:i:s');
        $this->folders()->markTrashed($ids, $now);
        $this->documents()->markTrashedInFolders($ids, $now);
    }

    private function upsertFolder(array $item, DocumentFolder $parent): DocumentFolder
    {
        $existing = $this->folders()->findByExternalId((string) $item['id']);
        $data = [
            'name' => (string) $item['name'],
            'web_link' => $item['webLink'] ?? null,
            'drive_modified_at' => $item['modifiedAt'] ?? null,
            'trashed_at' => null,
            'synced_at' => date('Y-m-d H:i:s'),
        ];
        // Una cartella del cliente collegata a mano resta dov'e' nel gestionale.
        if (!($existing !== null && (int) $existing->parentId !== (int) $parent->id && $this->isAnchor((string) $item['id']))) {
            $data['parent_id'] = $parent->id;
        }
        if ($existing !== null) {
            if ($existing->trashedAt !== null) {
                // Ripristinata sul Drive: torna con il suo contenuto.
                $this->folders()->markTrashed($this->folders()->descendantIds((int) $existing->id), null);
                $this->documents()->markTrashedInFolders($this->folders()->descendantIds((int) $existing->id), null);
            }
            $this->folders()->update((int) $existing->id, $data);

            return $this->folders()->find((int) $existing->id);
        }
        $customerId = $parent->customerId;
        if ($customerId === null && $parent->parentId === null) {
            $customerId = $this->customerByName((string) $item['name']);
        }
        $id = $this->folders()->insert($data + [
            'external_id' => (string) $item['id'],
            'parent_id' => $parent->id,
            'customer_id' => $customerId,
            'work_order_id' => $parent->workOrderId,
        ]);

        return $this->folders()->find($id);
    }

    private function upsertFile(array $item, DocumentFolder $folder): Document
    {
        $existing = $this->documents()->findByExternalId((string) $item['id']);
        $data = [
            'folder_id' => $folder->id,
            'name' => (string) $item['name'],
            'mime_type' => $item['mime'] ?? null,
            'size_bytes' => $item['size'] ?? null,
            'web_link' => $item['webLink'] ?? null,
            'md5' => $item['md5'] ?? null,
            'drive_modified_at' => $item['modifiedAt'] ?? null,
            'trashed_at' => null,
            'synced_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing !== null) {
            // Spostato in una cartella di un altro cliente o progetto: li segue.
            if ((int) $existing->folderId !== (int) $folder->id) {
                $data['customer_id'] = $folder->customerId;
                $data['work_order_id'] = $folder->workOrderId;
            }
            $this->documents()->update((int) $existing->id, $data);

            return $this->documents()->find((int) $existing->id);
        }
        $id = $this->documents()->insert($data + [
            'external_id' => (string) $item['id'],
            'customer_id' => $folder->customerId,
            'work_order_id' => $folder->workOrderId,
            'origin' => 'drive',
        ]);

        return $this->documents()->find($id);
    }

    private function customerByName(string $name): ?int
    {
        if (!in_array('customers', PackageCatalogService::installedNames(), true)) {
            return null;
        }
        if ($this->customerNames === null) {
            $this->customerNames = [];
            foreach ($this->container->get(CustomerRepository::class)->findAll() as $customer) {
                $this->customerNames[mb_strtolower(trim($customer->displayName()))] = (int) $customer->id;
            }
        }

        return $this->customerNames[mb_strtolower(trim($name))] ?? null;
    }

    /**
     * Una cartella per la pagina dei Documenti: dove sei, cosa contiene.
     *
     * @return array<string,mixed>
     */
    public function folderView(?int $folderId): array
    {
        $root = $this->root();
        $folder = $folderId !== null ? $this->folders()->find($folderId) : $root;
        if (!$folder instanceof DocumentFolder) {
            $folder = $root;
        }
        $auth = $this->container->get(AuthService::class);
        $canWrite = $this->store()->canWrite();
        $scope = $this->scope();
        $today = date('Y-m-d');
        $users = [];
        foreach ($this->container->get(UserRepository::class)->findAll() as $user) {
            $users[(int) $user->id] = trim("{$user->firstName} {$user->lastName}") ?: (string) $user->username;
        }

        $crumbs = [];
        $cursor = $folder;
        $guard = 0;
        while ($cursor instanceof DocumentFolder && $guard++ < 20) {
            array_unshift($crumbs, ['name' => $cursor->parentId === null ? 'Documenti' : (string) $cursor->name, 'href' => '/documenti/cartella/' . $cursor->id]);
            $cursor = $cursor->parentId !== null ? $this->folders()->find((int) $cursor->parentId) : null;
        }

        $subfolders = $folder !== null ? array_values(array_filter($this->folders()->children((int) $folder->id), static fn (DocumentFolder $f) => $scope === null || $f->customerId === null || isset($scope[(int) $f->customerId]))) : [];
        $counts = $this->documents()->countInFolders(array_map(static fn (DocumentFolder $f) => (int) $f->id, $subfolders));
        $files = $folder !== null ? array_values(array_filter($this->documents()->inFolder((int) $folder->id), static fn (Document $d) => $scope === null || $d->customerId === null || isset($scope[(int) $d->customerId]))) : [];
        $base = $folder !== null ? '/documenti/cartella/' . $folder->id : '/documenti';

        return [
            'type' => 'document-browser',
            'ready' => $folder !== null,
            'canWrite' => $canWrite,
            'readOnlyNote' => $folder !== null && !$canWrite ? "Google Drive e' collegato in sola lettura: per creare cartelle e caricare file ricollegalo dalla Configurazione." : null,
            'title' => $folder !== null && $folder->parentId !== null ? (string) $folder->name : 'Documenti',
            'webLink' => $folder?->webLink,
            'crumbs' => $crumbs,
            'folders' => array_map(static fn (DocumentFolder $f) => [
                'name' => (string) $f->name,
                'href' => '/documenti/cartella/' . $f->id,
                'count' => $counts[(int) $f->id] ?? 0,
            ], $subfolders),
            'files' => array_map(fn (Document $d) => [
                'name' => (string) $d->name,
                'href' => '/documenti/file/' . $d->id,
                'open' => $d->webLink,
                'size' => $d->sizeBytes !== null ? self::size((int) $d->sizeBytes) : null,
                'when' => $d->driveModifiedAt !== null ? F::relativeDays(substr((string) $d->driveModifiedAt, 0, 10), $today) : null,
                'who' => $d->uploadedBy !== null ? ($users[(int) $d->uploadedBy] ?? null) : ($d->origin === 'drive' ? 'dal Drive' : null),
                'description' => $d->description,
                'kind' => self::kind((string) $d->mimeType),
                'trash' => $auth->hasPermission('documents.delete') && $canWrite ? '/documenti/file/' . $d->id . '/cestino' : null,
            ], $files),
            'newFolderAction' => $folder !== null && $canWrite && $auth->hasPermission('documents.create') ? $base . '/nuova' : null,
            'uploadAction' => $folder !== null && $canWrite && $auth->hasPermission('documents.create') ? $base . '/carica' : null,
            'renameAction' => $folder !== null && $folder->parentId !== null && $canWrite && $auth->hasPermission('documents.edit') ? $base . '/rinomina' : null,
            'trashAction' => $folder !== null && $folder->parentId !== null && $canWrite && $auth->hasPermission('documents.delete') ? $base . '/elimina' : null,
            'syncAction' => $auth->hasPermission('documents.edit') ? '/documenti/aggiorna' : null,
            'searchAction' => '/documenti/cerca',
        ];
    }

    /** La sezione "Documenti" della pagina di un progetto o di un cliente. */
    public function summaryFor(?int $workOrderId, ?int $customerId): ?array
    {
        if (!self::isInstalled() || !$this->store()->isReady()) {
            return null;
        }
        $folder = $workOrderId !== null ? $this->folders()->findForWorkOrder($workOrderId) : ($customerId !== null ? $this->folders()->findForCustomerRoot($customerId) : null);
        $children = $folder !== null ? $this->folders()->children((int) $folder->id) : [];
        $counts = $this->documents()->countInFolders(array_map(static fn (DocumentFolder $f) => (int) $f->id, $children));
        $canCreate = $this->store()->canWrite() && $this->container->get(AuthService::class)->hasPermission('documents.create');

        return [
            'type' => 'document-summary',
            'title' => 'Documenti',
            'folderHref' => $folder !== null ? '/documenti/cartella/' . $folder->id : null,
            'webLink' => $folder?->webLink,
            'files' => $folder !== null ? count($this->documents()->inFolder((int) $folder->id)) : 0,
            'folders' => array_map(static fn (DocumentFolder $f) => ['name' => (string) $f->name, 'href' => '/documenti/cartella/' . $f->id, 'count' => $counts[(int) $f->id] ?? 0], $children),
            'createAction' => $folder === null && $canCreate
                ? ($workOrderId !== null ? '/documenti/progetto/' . $workOrderId . '/cartelle' : '/documenti/cliente/' . $customerId . '/cartella')
                : null,
            'createLabel' => $workOrderId !== null ? 'Crea le cartelle sul Drive' : 'Crea la cartella sul Drive',
        ];
    }

    /** @return Document[] */
    public function search(string $query, ?int $customerId = null, int $limit = 20): array
    {
        $scope = $this->scope();
        $results = $this->documents()->search($query, $customerId, $limit * 2);
        if ($scope !== null) {
            $results = array_values(array_filter($results, static fn (Document $d) => $d->customerId === null || isset($scope[(int) $d->customerId])));
        }

        return array_slice($results, 0, $limit);
    }

    /** Dove sta un file, per mostrarlo nei risultati: "Della Rocca / Brand identity / 01 Preventivo". */
    public function pathOf(Document $document): string
    {
        $parts = [];
        $cursor = $document->folderId !== null ? $this->folders()->find((int) $document->folderId) : null;
        $guard = 0;
        while ($cursor instanceof DocumentFolder && $cursor->parentId !== null && $guard++ < 20) {
            array_unshift($parts, (string) $cursor->name);
            $cursor = $this->folders()->find((int) $cursor->parentId);
        }

        return implode(' / ', $parts);
    }

    /** @return ?array<int,bool> i clienti visibili a chi e' collegato (null = tutti) */
    private function scope(): ?array
    {
        if (!class_exists(\App\Customers\CustomerScope::class)) {
            return null;
        }
        $scope = $this->container->get(\App\Customers\CustomerScope::class);
        if (!$scope->isLimited()) {
            return null;
        }

        return array_fill_keys(array_map('intval', $scope->allowedIds() ?? []), true);
    }

    private function safeName(string $name): string
    {
        $name = trim(preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $name) ?? '');

        return mb_substr($name !== '' ? $name : 'Senza nome', 0, 200);
    }

    public static function size(int $bytes): string
    {
        return match (true) {
            $bytes >= 1073741824 => number_format($bytes / 1073741824, 1, ',', '.') . ' GB',
            $bytes >= 1048576 => number_format($bytes / 1048576, 1, ',', '.') . ' MB',
            $bytes >= 1024 => (int) round($bytes / 1024) . ' KB',
            default => $bytes . ' B',
        };
    }

    public static function kind(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'immagine',
            str_starts_with($mime, 'video/') => 'video',
            $mime === 'application/pdf' => 'pdf',
            str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel') => 'foglio',
            str_contains($mime, 'document') || str_contains($mime, 'word') => 'documento',
            str_contains($mime, 'presentation') => 'presentazione',
            str_contains($mime, 'zip') || str_contains($mime, 'compressed') => 'archivio',
            default => 'file',
        };
    }

    private function folders(): DocumentFolderRepository
    {
        return $this->container->get(DocumentFolderRepository::class);
    }

    private function documents(): DocumentRepository
    {
        return $this->container->get(DocumentRepository::class);
    }
}
