<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\User;
use App\Prompt\PromptToolInterface;
use App\Repository\UserRepository;
use App\View\Component\DataTableComponent;
use App\View\Component\FormComponent;

/**
 * Apre la scheda di un utente per modificarla - sia da /utenti/:id (id
 * noto, arriva dal click "Modifica" della lista) sia dal prompt con un
 * nome libero ("modifica admin", "apri utente Tommaso"): in quel caso
 * l'id non c'e', si cerca per nome/username/email e:
 *  - 0 risultati: lo si dice (tabella vuota col messaggio giusto, stesso
 *    componente delle liste normali, niente di nuovo da inventare);
 *  - 1 risultato: si apre direttamente il form, come se l'id fosse noto;
 *  - piu' risultati: si mostra una mini tabella con un link "Apri" per
 *    riga (stessa DataTableComponent/azione gia' usata da ListUsersTool),
 *    cosi' la scelta resta un click deterministico, non un altro giro di
 *    interpretazione del linguaggio.
 *
 * Nessuna 'triggers()' fissa: a differenza di "lista utenti" il nome qui
 * e' sempre dinamico (chi si sta modificando cambia ogni volta), quindi
 * non puo' esistere una frase scorciatoia statica - passa sempre da
 * Claude, che estrae 'query' dal messaggio libero.
 */
final class EditUserTool implements PromptToolInterface
{
    private UserRepository $users;
    private FormComponent $form;
    private DataTableComponent $dataTable;

    public function __construct(Container $container)
    {
        $this->users = $container->get(UserRepository::class);
        $this->form = $container->get(FormComponent::class);
        $this->dataTable = $container->get(DataTableComponent::class);
    }

    public function name(): string
    {
        return 'edit_user';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un utente del sistema, cercato per id o per nome/username/email. "
            . "Usalo quando l'utente chiede di aprire, vedere il dettaglio o modificare uno specifico utente "
            . "(es. 'modifica admin', 'apri utente Tommaso', 'voglio modificare l'utente con username trossi').";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => "Id dell'utente, se gia' noto"],
                'query' => [
                    'type' => 'string',
                    'description' => "Nome, username o email (anche parziale) da cercare, se l'id non e' noto",
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
        return 'users.manage';
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
            $matches = $this->users->searchByName($query);

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

        $user = $this->users->find($id);
        if ($user === null) {
            return [$this->emptyResult((string) $id)];
        }

        return [$this->buildForm($user)];
    }

    /**
     * $action e' sovrascrivibile perche' questo stesso form serve sia
     * alla scheda admin (/utenti/:id, dietro users.manage) sia alla
     * pagina "Il mio profilo" (/profilo, il proprio account senza
     * permessi speciali) - i campi esposti sono gli stessi (niente
     * ruolo/permessi qui, quella e' tutta un'altra schermata), cambia
     * solo dove va a finire il POST.
     */
    public function buildForm(User $user, ?string $message = null, ?string $messageType = null, ?string $action = null): array
    {
        return $this->form->toData([
            'entity' => 'user',
            'title' => 'Modifica utente',
            'action' => $action ?? ('/utenti/' . $user->id),
            'fields' => [
                ['key' => 'username', 'label' => 'Username', 'value' => $user->username, 'required' => true],
                ['key' => 'first_name', 'label' => 'Nome', 'value' => $user->firstName],
                ['key' => 'last_name', 'label' => 'Cognome', 'value' => $user->lastName],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $user->email, 'required' => true],
            ],
            'message' => $message,
            'messageType' => $messageType,
        ]);
    }

    private function candidatesTable(array $users, string $query): array
    {
        $rows = array_map(static fn (User $u) => $u->toDisplayArray(), $users);

        return $this->dataTable->toData([
            'entity' => 'user',
            'title' => "Piu' utenti corrispondono a \u{ab}{$query}\u{bb}",
            'columns' => [
                ['key' => 'username', 'label' => 'Username'],
                ['key' => 'firstName', 'label' => 'Nome'],
                ['key' => 'lastName', 'label' => 'Cognome'],
                ['key' => 'email', 'label' => 'Email'],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => 'Apri', 'href' => '/utenti/{id}', 'permission' => 'users.manage'],
            ],
        ]);
    }

    private function emptyResult(string $query): array
    {
        return $this->dataTable->toData([
            'title' => 'Modifica utente',
            'columns' => [],
            'rows' => [],
            'emptyMessage' => $query !== ''
                ? "Nessun utente trovato per \u{ab}{$query}\u{bb}."
                : "Specifica un id o un nome utente da modificare.",
        ]);
    }
}
