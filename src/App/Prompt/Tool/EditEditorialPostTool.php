<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Editorial\EditorialOverviewService;
use App\Model\AbstractModel;
use App\Model\EditorialPost;
use App\Repository\EditorialPostRepository;
use App\View\Component\PostSummaryComponent;

final class EditEditorialPostTool extends AbstractEditEntityTool
{
    private EditorialOverviewService $overview;
    private PostSummaryComponent $summary;
    private EditorialPostRepository $posts;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->overview = $container->get(EditorialOverviewService::class);
        $this->summary = $container->get(PostSummaryComponent::class);
        $this->posts = $container->get(EditorialPostRepository::class);
    }

    protected function repositoryClass(): string
    {
        return EditorialPostRepository::class;
    }

    protected function packageName(): string
    {
        return 'editorial';
    }

    protected function manifestEntityKey(): string
    {
        return 'editorial_posts';
    }

    protected function entityTag(): string
    {
        return 'editorial_post';
    }

    protected function baseUrl(): string
    {
        return '/piano-editoriale';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo contenuto' : 'Modifica contenuto';
    }

    protected function formTitleBelowDetail(): ?string
    {
        return 'Dati del contenuto';
    }

    protected function systemManagedFields(): array
    {
        return ['client_feedback', 'review_note', 'internal_approved_by', 'internal_approved_at', 'approved_at', 'approved_via', 'revision_count', 'task_id', 'writer_task_id', 'parent_post_id'];
    }

    protected function searchColumns(): array
    {
        return ['title', 'caption'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Contenuto'],
            ['key' => 'channel', 'label' => 'Canale'],
            ['key' => 'publishDate', 'label' => 'Uscita'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof EditorialPost) {
            return [];
        }
        $base = '/piano-editoriale/' . $model->id;
        $actions = $this->nextSteps($model, $base);
        if ($model->stage === 'da approvare') {
            $actions[] = ['label' => 'Link per il cliente', 'action' => "{$base}/link-cliente", 'variant' => 'ghost', 'icon' => 'link'];
        }
        if ($model->format !== 'storia' && $model->isOpen() && $this->posts->findAll(['parent_post_id' => $model->id]) === []) {
            $actions[] = ['label' => 'Crea la storia', 'action' => "{$base}/storia", 'variant' => 'ghost', 'icon' => 'plus'];
        }
        if ($model->isOpen() && !in_array($model->stage, ['approvato', 'programmato'], true)) {
            $empty = trim((string) $model->caption) === '';
            $actions[] = ['label' => $empty ? 'Scrivi il testo con l\'IA' : 'Riscrivi con l\'IA', 'action' => "{$base}/testo", 'variant' => 'ghost', 'icon' => 'wand-magic-sparkles', 'confirm' => $empty ? null : 'Sostituire il testo attuale con una proposta dell\'IA?'];
        }
        if ($model->isOpen()) {
            $actions[] = ['label' => 'Scarta', 'action' => "{$base}/stato", 'body' => ['stage' => 'scartato'], 'variant' => 'ghost', 'confirm' => 'Scartare questo contenuto? Si annullano anche le attivita\'.'];
        }

        return [$this->summary->toData(['url' => $base, 'post' => $this->overview->detail($model), 'actions' => $actions])];
    }

    /**
     * Il passo successivo naturale per lo stato, cosi' nessuno deve scegliere
     * da un elenco. Chi puo' approvare salta la revisione interna (la sua e'
     * gia' l'approvazione); gli altri la chiedono.
     */
    private function nextSteps(EditorialPost $post, string $base): array
    {
        $canApprove = $this->auth->hasPermission('editorial.approve');
        $step = static fn (string $stage, string $label, string $variant = 'primary', array $extra = []) => ['label' => $label, 'action' => "{$base}/stato", 'body' => ['stage' => $stage], 'variant' => $variant] + $extra;

        return match ($post->stage) {
            'idea' => [$step('in lavorazione', 'Inizio a prepararlo')],
            'in lavorazione', 'da modificare' => [$step('pronto', $canApprove ? 'Pronto: al cliente' : 'Pronto: alla revisione')],
            'revisione interna' => $canApprove
                ? [
                    $step('da approvare', 'Approvo: al cliente'),
                    $step('da modificare', 'Da rivedere', 'secondary', ['ask' => 'Cosa va rivisto?', 'placeholder' => 'Es. testo troppo lungo, foto piu\' luminosa']),
                ]
                : [],
            'da approvare' => [
                $step('approvato', 'Il cliente ha approvato', 'primary', ['ask' => 'Come l\'ha approvato?', 'placeholder' => 'Es. WhatsApp, a voce, email']),
                $step('da modificare', 'Vuole modifiche', 'secondary', ['ask' => 'Cosa vuole cambiare il cliente?', 'placeholder' => 'Es. foto del negozio al posto del prodotto']),
            ],
            'approvato' => [$step('programmato', 'Programmato')],
            'programmato' => [$step('pubblicato', 'Pubblicato', 'primary', ['ask' => 'Link al contenuto pubblicato (facoltativo)', 'placeholder' => 'https://www.instagram.com/p/...'])],
            default => [],
        };
    }

    public function name(): string
    {
        return 'edit_editorial_post';
    }

    public function description(): string
    {
        return "Apre un contenuto del piano editoriale (post, reel, storia...) cercato per id o tema: anteprima, stato, approvazione.";
    }

    public function requiredPermission(): ?string
    {
        return 'editorial.edit';
    }
}
