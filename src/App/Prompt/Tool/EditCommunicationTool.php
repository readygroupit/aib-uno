<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\Communication;
use App\Model\CommunicationContent;
use App\Package\PackageManifest;
use App\Prompt\PromptToolInterface;
use App\Repository\CommunicationContentRepository;
use App\Repository\CommunicationRepository;
use App\Service\AuthService;
use App\View\Component\DataTableComponent;
use App\View\Component\FormComponent;

/**
 * Bespoke apposta (non su AbstractEditEntityTool): 'communications' e'
 * l'unico pacchetto finora con due tabelle per una sola entita' percepita
 * dall'operatore (metadati + contenuto, vedi il commento nel manifest sul
 * perche') - la base generica assume un solo repository/una sola riga di
 * manifest per form. Qui si costruisce un solo form con i campi di
 * ENTRAMBE le tabelle (stessa forma finale prodotta da FormComponent,
 * che non sa/non le importa da dove viene ogni campo).
 */
final class EditCommunicationTool implements PromptToolInterface
{
    private CommunicationRepository $communications;
    private CommunicationContentRepository $contents;
    private FormComponent $form;
    private DataTableComponent $dataTable;
    private AuthService $auth;

    public function __construct(Container $container)
    {
        $this->communications = $container->get(CommunicationRepository::class);
        $this->contents = $container->get(CommunicationContentRepository::class);
        $this->form = $container->get(FormComponent::class);
        $this->dataTable = $container->get(DataTableComponent::class);
        $this->auth = $container->get(AuthService::class);
    }

    public function name(): string
    {
        return 'edit_communication';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di una comunicazione, cercata per id. "
            . "Usalo quando l'utente chiede di aprire o modificare una comunicazione specifica.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => "Id della comunicazione"],
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
        return 'communications.edit';
    }

    public function triggers(): array
    {
        return [];
    }

    public function execute(array $input): array
    {
        $id = isset($input['id']) ? (int) $input['id'] : null;

        if ($id === null) {
            return [$this->emptyResult()];
        }

        $communication = $this->communications->find($id);
        if ($communication === null) {
            return [$this->emptyResult()];
        }

        $content = $this->contents->findByCommunicationId($id) ?? new CommunicationContent();

        return [$this->buildForm($communication, $content)];
    }

    /**
     * @param array<string,string> $fieldErrors fieldKey => messaggio (chiavi di entrambe le tabelle, mai in collisione: nomi campo diversi)
     */
    public function buildForm(
        Communication $communication,
        CommunicationContent $content,
        ?string $message = null,
        ?string $messageType = null,
        array $fieldErrors = []
    ): array {
        $isNew = $communication->id === null;

        $fields = array_merge(
            $this->fieldsFor('communications', $communication, $fieldErrors),
            $this->fieldsFor('communication_contents', $content, $fieldErrors, excludeKeys: ['communication_id'])
        );

        return $this->form->toData([
            'entity' => 'communication',
            'title' => $isNew ? 'Nuova comunicazione' : 'Modifica comunicazione',
            'action' => $isNew ? '/comunicazioni/nuovo' : '/comunicazioni/' . $communication->id,
            'submitLabel' => $isNew ? 'Registra' : 'Salva',
            'fields' => $fields,
            'layout' => $this->layout(),
            'secondaryActions' => $isNew ? [] : $this->secondaryActions($communication),
            'message' => $message,
            'messageType' => $messageType,
        ]);
    }

    /** @return list<array{label:string, action:string, confirm?:string, variant?:string}> */
    private function secondaryActions(Communication $communication): array
    {
        if (!$this->auth->hasPermission('communications.delete')) {
            return [];
        }

        return [[
            'label' => 'Elimina',
            'action' => '/comunicazioni/' . $communication->id . '/elimina',
            'confirm' => 'Eliminare definitivamente questa comunicazione?',
            'variant' => 'delete',
        ]];
    }

    /**
     * Stessa logica di App\Prompt\Tool\AbstractEditEntityTool::fieldsFromManifest()/
     * inferWidth(), qui duplicata in forma ridotta (niente autocomplete,
     * nessun campo lo usa) perche' e' l'unico chiamante bespoke - non vale
     * la pena generalizzare la base per un solo consumatore in piu'.
     *
     * @param array<string,string> $fieldErrors
     * @param string[] $excludeKeys campi del manifest esclusi dal form (es. la FK impostata dal controller, mai dall'operatore)
     * @return list<array{key:string,label:string,value:mixed,type:string,required:bool,width:string,error:?string}>
     */
    private function fieldsFor(string $entityKey, AbstractModel $model, array $fieldErrors, array $excludeKeys = []): array
    {
        $definitions = (new PackageManifest('communications'))->entities()[$entityKey]['fields'];

        $fields = [];
        foreach ($definitions as $key => $definition) {
            if (in_array($key, $excludeKeys, true)) {
                continue;
            }

            $format = $definition['format'] ?? null;
            $property = AbstractModel::propertyFromColumn($key);

            $fields[] = [
                'key' => $key,
                'label' => $definition['label'],
                'value' => $model->$property ?? null,
                'type' => $format === 'email' ? 'email' : ($format === 'integer' ? 'number' : 'text'),
                'required' => ($definition['base'] ?? false) && !($definition['nullable'] ?? false),
                'width' => $this->inferWidth($definition),
                'error' => $fieldErrors[$key] ?? null,
            ];
        }

        return $fields;
    }

    private function inferWidth(array $definition): string
    {
        if (isset($definition['width'])) {
            return $definition['width'];
        }

        $format = $definition['format'] ?? null;
        $byFormat = ['email' => 'half', 'datetime' => 'half', 'integer' => 'quarter'];
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

    /** Sezioni/box fissi (non da manifest: due entity diverse in un solo form, PackageManifest::layout() legge una sola entity key alla volta). */
    private function layout(): array
    {
        return [
            'sections' => [
                [
                    'label' => null,
                    'boxes' => [
                        [
                            'title' => 'Messaggio',
                            'area' => 'main',
                            'fields' => ['subject', 'body'],
                        ],
                        [
                            'title' => 'Dettagli invio',
                            'area' => 'main',
                            'fields' => ['channel', 'direction', 'delivery_status', 'template_code', 'sent_at', 'read_at'],
                        ],
                        [
                            'title' => 'Collegamento',
                            'area' => 'sidebar',
                            'fields' => ['entity_type', 'entity_id'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function emptyResult(): array
    {
        return $this->dataTable->toData([
            'title' => 'Modifica comunicazione',
            'columns' => [],
            'rows' => [],
            'emptyMessage' => 'Comunicazione non trovata.',
        ]);
    }
}
