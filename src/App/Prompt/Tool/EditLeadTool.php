<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\Lead;
use App\Package\PackageManifest;
use App\Prompt\PromptToolInterface;
use App\Repository\LeadRepository;
use App\View\Component\DataTableComponent;
use App\View\Component\FormComponent;

/**
 * Stessa forma di EditUserTool (ricerca id/nome libero, 0/1/N risultati).
 * A differenza di li', i campi del form non sono elencati a mano qui:
 * si leggono dal manifest (packages/leads/package.php), stessa fonte gia'
 * usata da LeadsController per la validazione - un campo aggiunto/tolto
 * la' si riflette automaticamente qui, larghezza del campo compresa (vedi
 * inferWidth()). La struttura a sezioni/box (tab, sidebar) e' anch'essa
 * nel manifest ('layout'), passata cosi' com'e' a FormComponent che la
 * incrocia con i campi davvero disponibili.
 *
 * Usato sia per aprire un contatto esistente sia (Lead con id nullo) per
 * crearne uno nuovo - buildForm() decide l'azione del form in base a
 * questo.
 *
 * Alcuni campi (date/orari) restano input di testo semplice, non widget
 * calendario nativi: il valore in DB e quello atteso da <input
 * type="date/datetime-local"> non coincidono esattamente (spazio vs 'T',
 * secondi), servirebbe una conversione che qui non c'e' ancora - la
 * validazione del formato resta comunque server-side (vedi FieldValidator).
 */
final class EditLeadTool implements PromptToolInterface
{
    /** Gestiti solo da LeadsController::convertAction(), mai a mano dall'operatore. */
    private const SYSTEM_MANAGED_FIELDS = ['converted_customer_id', 'converted_at'];

    private LeadRepository $leads;
    private FormComponent $form;
    private DataTableComponent $dataTable;

    public function __construct(Container $container)
    {
        $this->leads = $container->get(LeadRepository::class);
        $this->form = $container->get(FormComponent::class);
        $this->dataTable = $container->get(DataTableComponent::class);
    }

    public function name(): string
    {
        return 'edit_lead';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un contatto, cercato per id o per nome/cognome/azienda/email/telefono. "
            . "Usalo quando l'utente chiede di aprire, vedere il dettaglio o modificare uno specifico contatto "
            . "(es. 'modifica il contatto Rossi', 'apri il lead con email x@y.it').";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => "Id del contatto, se gia' noto"],
                'query' => [
                    'type' => 'string',
                    'description' => "Nome, cognome, azienda, email o telefono (anche parziale) da cercare, se l'id non e' noto",
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
        return 'leads.manage';
    }

    public function triggers(): array
    {
        return [];
    }

    public function execute(array $input): array
    {
        $id = isset($input['id']) ? (int) $input['id'] : null;
        $query = trim((string) ($input['query'] ?? ''));

        if ($id === null && $query !== '') {
            $matches = $this->leads->searchByName($query);

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

        $lead = $this->leads->find($id);
        if ($lead === null) {
            return [$this->emptyResult((string) $id)];
        }

        return [$this->buildForm($lead)];
    }

    public function buildForm(Lead $lead, ?string $message = null, ?string $messageType = null): array
    {
        $isNew = $lead->id === null;
        $manifest = new PackageManifest('leads');

        return $this->form->toData([
            'entity' => 'lead',
            'title' => $isNew ? 'Nuovo contatto' : 'Modifica contatto',
            'action' => $isNew ? '/contatti/nuovo' : '/contatti/' . $lead->id,
            'submitLabel' => $isNew ? 'Crea contatto' : 'Salva',
            'fields' => $this->fieldsFromManifest($manifest, $lead),
            'layout' => $manifest->layout('leads'),
            // Solo su un contatto gia' esistente: "converti"/"segna
            // perso" non hanno senso prima di aver salvato un id, "invia
            // email" e' un segnaposto onesto (vedi LeadsController::
            // sendEmailAction) ma non ha comunque senso su un id che
            // ancora non c'e'.
            'secondaryActions' => $isNew ? [] : [
                ['label' => 'Converti in cliente', 'action' => '/contatti/' . $lead->id . '/converti'],
                ['label' => 'Segna come perso', 'action' => '/contatti/' . $lead->id . '/perso'],
                ['label' => 'Invia email', 'action' => '/contatti/' . $lead->id . '/invia-email'],
            ],
            'message' => $message,
            'messageType' => $messageType,
        ]);
    }

    /**
     * @return list<array{key: string, label: string, value: mixed, type: string, required: bool, width: string}>
     */
    private function fieldsFromManifest(PackageManifest $manifest, Lead $lead): array
    {
        $definitions = $manifest->entities()['leads']['fields'];
        $fields = [];

        foreach ($definitions as $key => $definition) {
            if (in_array($key, self::SYSTEM_MANAGED_FIELDS, true)) {
                continue;
            }

            $format = $definition['format'] ?? null;

            $fields[] = [
                'key' => $key,
                'label' => $definition['label'],
                'value' => $this->fieldValue($lead, $key),
                'type' => $format === 'email' ? 'email' : ($format === 'integer' ? 'number' : 'text'),
                'required' => (bool) ($definition['base'] ?? false),
                'width' => $this->inferWidth($definition),
            ];
        }

        return $fields;
    }

    /** Nome del campo (snake_case) -> proprieta' del model (camelCase), stessa trasformazione di AbstractModel::fromArray(). */
    private function fieldValue(Lead $lead, string $key): mixed
    {
        $property = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));

        return $lead->$property ?? null;
    }

    /**
     * Larghezza suggerita dedotta dal formato/tipo SQL del campo, non
     * scelta a mano campo per campo - cosi' un CAP (VARCHAR(10)) resta
     * stretto e delle note (TEXT) prendono tutta la riga senza doverlo
     * dichiarare ogni volta nel manifest. Un campo puo' comunque avere
     * 'width' esplicito nel manifest per un caso fuori norma (non
     * ancora servito, non implementato finche' non serve davvero).
     */
    private function inferWidth(array $definition): string
    {
        if (isset($definition['width'])) {
            return $definition['width'];
        }

        $format = $definition['format'] ?? null;
        $byFormat = [
            'email' => 'half',
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

    private function candidatesTable(array $leads, string $query): array
    {
        $rows = array_map(static fn (Lead $l) => $l->toDisplayArray(), $leads);

        return $this->dataTable->toData([
            'entity' => 'lead',
            'title' => "Piu' contatti corrispondono a \u{ab}{$query}\u{bb}",
            'columns' => [
                ['key' => 'firstName', 'label' => 'Nome'],
                ['key' => 'lastName', 'label' => 'Cognome'],
                ['key' => 'companyName', 'label' => 'Azienda'],
                ['key' => 'email', 'label' => 'Email'],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => 'Apri', 'href' => '/contatti/{id}', 'permission' => 'leads.manage'],
            ],
        ]);
    }

    private function emptyResult(string $query): array
    {
        return $this->dataTable->toData([
            'title' => 'Modifica contatto',
            'columns' => [],
            'rows' => [],
            'emptyMessage' => $query !== ''
                ? "Nessun contatto trovato per \u{ab}{$query}\u{bb}."
                : "Specifica un id o un nome da modificare.",
        ]);
    }
}
