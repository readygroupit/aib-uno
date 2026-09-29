<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\User;
use App\Prompt\PromptToolInterface;
use App\Repository\CaseFileRepository;
use App\Repository\CommunicationRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\LeadRepository;
use App\Repository\PermissionRepository;
use App\Repository\ProfileRepository;
use App\Repository\RefundRepository;
use App\Repository\TaskRepository;
use App\Service\AuthService;
use App\View\Component\ActivityFeedComponent;
use App\View\Component\FormComponent;
use App\View\Component\PermissionSummaryComponent;
use App\View\Component\ProfileHeaderComponent;
use App\View\Component\ProfileLayoutComponent;
use App\View\Component\StatBoxComponent;

/**
 * "Il mio profilo" - non piu' un riuso nudo del form utente (vedi la
 * nota storica in EditUserTool::buildForm()), una pagina a se': chi sei
 * (intestazione), i tuoi dati (form), sicurezza (cambio password), cosa
 * puoi fare davvero (permessi effettivi, sola lettura) e cosa hai fatto
 * davvero (attivita' recente derivata da created_at/created_by, nessuna
 * tabella di audit dedicata serve per questo). Tutto quello che compare
 * e' dato reale di QUESTO utente, mai inventato.
 */
final class ShowProfileTool implements PromptToolInterface
{
    private const CATEGORY_LABELS = ['view' => 'Lettura', 'create' => 'Creazione', 'edit' => 'Modifica', 'delete' => 'Eliminazione'];

    public function __construct(private readonly Container $container)
    {
    }

    public function name(): string
    {
        return 'show_profile';
    }

    public function description(): string
    {
        return "Mostra la pagina del profilo dell'utente collegato: dati personali, sicurezza, permessi e attivita' recente.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
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
        return null;
    }

    public function triggers(): array
    {
        return [];
    }

    public function execute(array $input): array
    {
        return $this->build();
    }

    /**
     * $info/$password: ['message' => ?string, 'messageType' => ?string,
     * 'fieldErrors' => array<string,string>] - due form indipendenti
     * sulla stessa pagina (vedi ProfileController), ognuno con il proprio
     * feedback dopo il submit, mai quello dell'altro.
     */
    public function build(array $info = [], array $password = []): array
    {
        $auth = $this->container->get(AuthService::class);
        $userId = $auth->currentUserId();
        if ($userId === null) {
            return [];
        }

        /** @var \App\Repository\UserRepository $users */
        $users = $this->container->get(\App\Repository\UserRepository::class);
        $user = $users->find($userId);
        if ($user === null) {
            return [];
        }

        /** @var ProfileRepository $profiles */
        $profiles = $this->container->get(ProfileRepository::class);
        $profile = $user->profileId !== null ? $profiles->find($user->profileId) : null;

        $components = [
            $this->buildHeader($user, $profile?->name ?? ''),
            $this->container->get(ProfileLayoutComponent::class)->toData([
                'main' => [
                    $this->buildInfoForm(
                        $user,
                        $info['message'] ?? null,
                        $info['messageType'] ?? null,
                        $info['fieldErrors'] ?? []
                    ),
                    $this->buildPasswordForm($password['message'] ?? null, $password['messageType'] ?? null),
                ],
                'sidebar' => [
                    $this->buildPermissionSummary($userId, $user->profileId),
                    ...$this->buildPersonalStats($userId),
                ],
            ]),
            $this->buildActivityFeed($userId),
        ];

        return $components;
    }

    private function buildHeader(User $user, string $roleLabel): array
    {
        $initials = mb_strtoupper(mb_substr((string) $user->firstName, 0, 1) . mb_substr((string) $user->lastName, 0, 1));
        if (trim($initials) === '') {
            $initials = mb_strtoupper(mb_substr((string) $user->username, 0, 2));
        }

        return $this->container->get(ProfileHeaderComponent::class)->toData([
            'initials' => $initials,
            'name' => trim("{$user->firstName} {$user->lastName}") ?: $user->username,
            'roleLabel' => $roleLabel,
            'email' => (string) $user->email,
            'memberSince' => $user->createdAt !== null ? date('d/m/Y', strtotime($user->createdAt)) : null,
            'lastLogin' => $user->lastLoginAt !== null ? date('d/m/Y H:i', strtotime($user->lastLoginAt)) : 'mai prima d\'ora',
        ]);
    }

    /** @param array<string,string> $fieldErrors */
    private function buildInfoForm(User $user, ?string $message, ?string $messageType, array $fieldErrors): array
    {
        /** @var FormComponent $form */
        $form = $this->container->get(FormComponent::class);

        return $form->toData([
            'entity' => 'user',
            'title' => 'Informazioni personali',
            'action' => '/profilo',
            'submitLabel' => 'Salva',
            'fields' => [
                ['key' => 'username', 'label' => 'Username', 'value' => $user->username, 'required' => true, 'width' => 'half', 'error' => $fieldErrors['username'] ?? null],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $user->email, 'required' => true, 'width' => 'half', 'error' => $fieldErrors['email'] ?? null],
                ['key' => 'first_name', 'label' => 'Nome', 'value' => $user->firstName, 'width' => 'half'],
                ['key' => 'last_name', 'label' => 'Cognome', 'value' => $user->lastName, 'width' => 'half'],
            ],
            'message' => $message,
            'messageType' => $messageType,
        ]);
    }

    private function buildPasswordForm(?string $message, ?string $messageType): array
    {
        /** @var FormComponent $form */
        $form = $this->container->get(FormComponent::class);

        return $form->toData([
            'entity' => 'user-password',
            'title' => 'Sicurezza',
            'action' => '/profilo/password',
            'submitLabel' => 'Cambia password',
            'fields' => [
                ['key' => 'current_password', 'label' => 'Password attuale', 'type' => 'password', 'required' => true, 'width' => 'full'],
                ['key' => 'new_password', 'label' => 'Nuova password', 'type' => 'password', 'required' => true, 'width' => 'half'],
                ['key' => 'new_password_confirm', 'label' => 'Conferma nuova password', 'type' => 'password', 'required' => true, 'width' => 'half'],
            ],
            'message' => $message,
            'messageType' => $messageType,
        ]);
    }

    private function buildPermissionSummary(int $userId, ?int $profileId): array
    {
        /** @var PermissionRepository $permissions */
        $permissions = $this->container->get(PermissionRepository::class);
        $auth = $this->container->get(AuthService::class);

        $groups = [];
        foreach ($permissions->findAllWithDomain() as $row) {
            if (!$auth->hasPermission($row['code'])) {
                continue;
            }
            $groups[$row['domain']][] = ['name' => $row['name'], 'category' => $row['category'] ?? 'view'];
        }

        $formatted = [];
        foreach ($groups as $domain => $perms) {
            $formatted[] = ['domain' => $domain, 'permissions' => $perms];
        }

        return $this->container->get(PermissionSummaryComponent::class)->toData([
            'title' => 'Accesso',
            'subtitle' => 'Cosa puoi fare',
            'groups' => $formatted,
        ]);
    }

    /** @return list<array> 3 StatBoxComponent */
    private function buildPersonalStats(int $userId): array
    {
        /** @var StatBoxComponent $statBox */
        $statBox = $this->container->get(StatBoxComponent::class);
        $leads = $this->container->get(LeadRepository::class);
        $cases = $this->container->get(CaseFileRepository::class);
        $tasks = $this->container->get(TaskRepository::class);

        $openTasks = count(array_filter(
            $tasks->findAll(['assigned_user_id' => $userId]),
            static fn ($t) => $t->completedAt === null
        ));

        return [
            $statBox->toData(['label' => 'Contatti assegnati', 'value' => (string) $leads->count(['assigned_user_id' => $userId]), 'href' => '/contatti', 'dotColor' => 'moss', 'hero' => true]),
            $statBox->toData(['label' => 'Pratiche assegnate', 'value' => (string) $cases->count(['assigned_user_id' => $userId]), 'href' => '/pratiche', 'dotColor' => 'lilac']),
            $statBox->toData(['label' => 'Attivita\' da completare', 'value' => (string) $openTasks, 'href' => '/attivita', 'dotColor' => 'brass']),
        ];
    }

    private function buildActivityFeed(int $userId): array
    {
        $items = [];

        $leads = $this->container->get(LeadRepository::class)->findAll(['created_by' => $userId], 'created_at DESC');
        foreach (array_slice($leads, 0, 4) as $lead) {
            $items[] = [
                'label' => 'Contatto creato: ' . trim("{$lead->firstName} {$lead->lastName}"),
                'detail' => $lead->disserviceType ?? null,
                'timestamp' => $lead->createdAt,
                'dotColor' => 'moss',
                'href' => '/contatti/' . $lead->id,
            ];
        }

        $cases = $this->container->get(CaseFileRepository::class)->findAll(['created_by' => $userId], 'created_at DESC');
        foreach (array_slice($cases, 0, 4) as $case) {
            $items[] = [
                'label' => 'Pratica creata: ' . $case->title,
                'detail' => $case->stage,
                'timestamp' => $case->createdAt,
                'dotColor' => 'lilac',
                'href' => '/pratiche/' . $case->id,
            ];
        }

        $comms = $this->container->get(CommunicationRepository::class)->findAll(['created_by' => $userId], 'created_at DESC');
        foreach (array_slice($comms, 0, 3) as $comm) {
            $items[] = [
                'label' => 'Comunicazione registrata (' . $comm->channel . ')',
                'detail' => $comm->direction === 'out' ? 'inviata' : 'ricevuta',
                'timestamp' => $comm->createdAt,
                'dotColor' => 'teal',
                'href' => '/comunicazioni/' . $comm->id,
            ];
        }

        $refunds = $this->container->get(RefundRepository::class)->findAll(['created_by' => $userId], 'created_at DESC');
        foreach (array_slice($refunds, 0, 3) as $refund) {
            $items[] = [
                'label' => 'Rimborso registrato',
                'detail' => $refund->paymentStatus,
                'timestamp' => $refund->createdAt,
                'dotColor' => 'brass',
                'href' => '/rimborsi/' . $refund->id,
            ];
        }

        $docs = $this->container->get(DocumentRequestRepository::class)->findAll(['created_by' => $userId], 'created_at DESC');
        foreach (array_slice($docs, 0, 3) as $doc) {
            $items[] = [
                'label' => 'Documento richiesto: ' . $doc->documentType,
                'detail' => $doc->completenessStatus,
                'timestamp' => $doc->createdAt,
                'dotColor' => 'petrol',
                'href' => '/documenti-richiesti/' . $doc->id,
            ];
        }

        usort($items, static fn ($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));
        $items = array_slice($items, 0, 8);

        foreach ($items as &$item) {
            $item['timestamp'] = $this->relativeTime($item['timestamp']);
        }
        unset($item);

        return $this->container->get(ActivityFeedComponent::class)->toData([
            'title' => 'Cronologia',
            'subtitle' => 'Attivita\' recente',
            'items' => $items,
            'emptyMessage' => 'Non hai ancora registrato nulla.',
        ]);
    }

    private function relativeTime(?string $timestamp): string
    {
        if ($timestamp === null) {
            return '';
        }

        $diffMinutes = (int) round((time() - strtotime($timestamp)) / 60);
        if ($diffMinutes < 60) {
            return $diffMinutes <= 1 ? 'ora' : "{$diffMinutes} min fa";
        }
        $diffHours = (int) round($diffMinutes / 60);
        if ($diffHours < 24) {
            return "{$diffHours} h fa";
        }
        $diffDays = (int) round($diffHours / 24);

        return $diffDays === 1 ? 'ieri' : "{$diffDays} giorni fa";
    }
}
