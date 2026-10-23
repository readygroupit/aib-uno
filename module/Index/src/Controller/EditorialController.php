<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Editorial\EditorialService;
use App\Model\AbstractModel;
use App\Model\EditorialPost;
use App\Portal\PortalService;
use App\Prompt\Tool\EditEditorialPostTool;
use App\Prompt\Tool\ShowEditorialTool;
use App\Repository\CustomerRepository;
use App\Repository\EditorialPostRepository;
use App\Service\AuthService;

/**
 * Piano editoriale. Ogni salvataggio riallinea l'attivita' di
 * preparazione di chi prepara il contenuto (EditorialService::syncTask);
 * il passo successivo (stato), i buchi da contratto, il testo proposto
 * dall'IA e il link per il cliente sono azioni a un clic.
 */
final class EditorialController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return EditorialPostRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditEditorialPostTool::class;
    }

    protected function listToolClass(): string
    {
        return ShowEditorialTool::class;
    }

    protected function packageName(): string
    {
        return 'editorial';
    }

    protected function manifestEntityKey(): string
    {
        return 'editorial_posts';
    }

    protected function listPageTitle(): string
    {
        return 'Piano editoriale';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo contenuto';
    }

    protected function editPageTitle(): string
    {
        return 'Contenuto';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'idea', 'channel' => 'Instagram', 'format' => 'post', 'publish_date' => date('Y-m-d', strtotime('+3 days')), 'publish_time' => '12:30'];
    }

    protected function createdMessage(): string
    {
        return 'Contenuto aggiunto al piano.';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'stage', 'caption', 'clientLink' => 'editorial.edit',
            'fill', 'story' => 'editorial.create',
            'approveCustomer' => 'editorial.approve',
            default => parent::requiredPermissionForAction($action),
        };
    }

    /** ?settimana=AAAA-MM-GG per un altro periodo. */
    public function indexAction(): ?string
    {
        $components = $this->container->get(ShowEditorialTool::class)->execute(['week' => (string) $this->request->get('settimana', '')]);

        return $this->renderPage($components, ['title' => $this->listPageTitle()]);
    }

    protected function afterCreate(int $id): void
    {
        $service = $this->container->get(EditorialService::class);
        $service->fillPeople($id);
        $service->syncTask($id);
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $this->container->get(EditorialService::class)->syncTask($id);

        return null;
    }

    protected function afterDelete(int $id): void
    {
        $this->container->get(EditorialService::class)->syncTask($id);
    }

    /** Il passo successivo (vedi EditorialService::advance()); 'text' e' la nota o, per "approvato", come l'ha approvato il cliente. */
    public function stageAction(): void
    {
        $auth = $this->container->get(AuthService::class);
        $stage = (string) $this->request->get('stage', '');
        $text = (string) $this->request->get('text', '');
        try {
            $message = $this->container->get(EditorialService::class)->advance(
                (int) $this->param('id'),
                $stage,
                (int) $auth->currentUserId(),
                $auth->hasPermission('editorial.approve'),
                $stage === 'approvato' ? '' : $text,
                $stage === 'approvato' ? (trim($text) ?: null) : null
            );
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);

            return;
        }
        $this->json(['ok' => true, 'message' => $message]);
    }

    /** Approvazione interna di tutto cio' che un cliente ha in revisione (il piano del mese). */
    public function approveCustomerAction(): void
    {
        $auth = $this->container->get(AuthService::class);
        $count = $this->container->get(EditorialService::class)->approveAllInternally((int) $this->request->get('cliente'), (int) $auth->currentUserId());
        $this->json(['ok' => true, 'message' => $count > 0 ? ($count === 1 ? 'Approvato 1 contenuto' : "Approvati {$count} contenuti") . ': ora li vede il cliente.' : 'Niente da approvare.']);
    }

    /** Dal post nasce la storia: si apre subito. */
    public function storyAction(): void
    {
        try {
            $id = $this->container->get(EditorialService::class)->deriveStory((int) $this->param('id'));
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);

            return;
        }
        $this->json(['ok' => true, 'message' => 'Storia creata dal post.', 'open' => '/piano-editoriale/' . $id]);
    }

    /** Crea le bozze che mancano al contratto di un cliente su un canale. */
    public function fillAction(): void
    {
        $withAi = $this->request->get('ia') === '1';
        $created = $this->container->get(EditorialService::class)->fillGaps((int) $this->request->get('cliente'), (string) $this->request->get('canale', ''), date('Y-m-d'), $withAi);
        $this->json(['ok' => true, 'message' => $created > 0
            ? ($created === 1 ? 'Creata 1 bozza' : "Create {$created} bozze") . ' nei prossimi giorni lavorativi' . ($withAi ? ', con i temi proposti dall\'IA dalla strategia: rivedili.' : ': dai a ognuna il suo tema.')
            : 'Non manca nulla.']);
    }

    public function captionAction(): void
    {
        $post = $this->repository()->find((int) $this->param('id'));
        if (!$post instanceof EditorialPost) {
            $this->json(['ok' => false, 'message' => 'Contenuto non trovato.'], 404);

            return;
        }
        try {
            $caption = $this->container->get(EditorialService::class)->draftCaption($post);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'message' => 'L\'IA non e\' disponibile ora: scrivi il testo a mano o riprova.']);

            return;
        }
        $this->repository()->update((int) $post->id, ['caption' => $caption]);
        $this->json(['ok' => true, 'message' => 'Testo proposto dall\'IA: rileggilo prima di mandarlo al cliente.']);
    }

    /** Il link dell'area del cliente, con un messaggio pronto: "c'e' un contenuto da approvare". */
    public function clientLinkAction(): void
    {
        $post = $this->repository()->find((int) $this->param('id'));
        $customer = $post instanceof EditorialPost ? $this->container->get(CustomerRepository::class)->find((int) $post->customerId) : null;
        if ($customer === null) {
            $this->json(['ok' => false, 'message' => 'Cliente non trovato.'], 404);

            return;
        }
        $url = $this->container->get(PortalService::class)->link((int) $customer->id, $this->request->baseUrl());
        $waiting = count($this->container->get(EditorialPostRepository::class)->findForCustomerInStage((int) $customer->id, 'da approvare'));
        $what = $waiting > 1 ? "{$waiting} contenuti da approvare" : "un contenuto da approvare (\u{ab}{$post->title}\u{bb})";

        $this->json(['ok' => true, 'share' => [
            'title' => 'Link per il cliente',
            'intro' => "Nell'area di {$customer->displayName()} c'e' {$what}: il cliente approva o chiede modifiche, senza password.",
            'url' => $url,
            'email' => $customer->email,
            'subject' => 'Contenuti da approvare',
            'text' => "Ciao,\nnella tua area c'e' {$what}. Puoi approvare o chiederci modifiche da qui:\n{$url}\n\nGrazie!",
        ]]);
    }
}
