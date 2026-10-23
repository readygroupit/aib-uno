<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\Profile;
use App\Prompt\PromptToolInterface;
use App\Repository\ProfilePermissionRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\View\Component\SimpleBoardComponent;

/**
 * I profili (ruoli): chi li ha e quanti permessi danno. Ogni utente ha un
 * profilo; i permessi si danno al profilo, non alla persona.
 */
final class ShowProfilesTool implements PromptToolInterface
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function name(): string
    {
        return 'show_profiles';
    }

    public function description(): string
    {
        return "Profili e permessi: i ruoli (direzione, social, grafica...), chi li ha e cosa possono fare. Usalo per 'profili', 'ruoli', 'permessi dei ruoli'.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Profili e permessi';
    }

    public function menuSection(): ?string
    {
        return 'sistema';
    }

    public function menuCount(): ?string
    {
        return (string) $this->container->get(ProfileRepository::class)->count();
    }

    public function requiredPermission(): ?string
    {
        return 'users.manage';
    }

    public function triggers(): array
    {
        return ['profili', 'ruoli', 'permessi dei profili'];
    }

    public function execute(array $input): array
    {
        $users = $this->container->get(UserRepository::class)->countPerProfile();
        $grants = $this->container->get(ProfilePermissionRepository::class);
        $rows = array_map(static fn (Profile $p) => [
            'href' => '/profili/' . $p->id,
            'title' => (string) $p->name,
            'meta' => (string) $p->description,
            'amount' => ($users[(int) $p->id] ?? 0) === 1 ? '1 persona' : ($users[(int) $p->id] ?? 0) . ' persone',
            'badge' => ['label' => count($grants->permissionIdsForProfile((int) $p->id)) . ' permessi', 'tone' => 'neutral'],
            'actions' => [],
        ], $this->container->get(ProfileRepository::class)->findAll([], 'id'));

        return [$this->container->get(SimpleBoardComponent::class)->toData([
            'title' => 'Profili e permessi',
            'url' => '/profili',
            'createHref' => '/profili/nuovo',
            'createLabel' => 'Nuovo profilo',
            'sections' => [['title' => 'Profili', 'tone' => 'neutral', 'empty' => 'Nessun profilo.', 'rows' => $rows]],
        ])];
    }
}
