<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Repository\UserRepository;
use App\Repository\WorkScheduleRepository;

final class ListWorkSchedulesTool extends AbstractListEntityTool
{
    private const DAYS = ['1' => 'lun', '2' => 'mar', '3' => 'mer', '4' => 'gio', '5' => 'ven', '6' => 'sab', '7' => 'dom'];

    private UserRepository $users;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->users = $container->get(UserRepository::class);
    }

    protected function repositoryClass(): string
    {
        return WorkScheduleRepository::class;
    }

    protected function entityTag(): string
    {
        return 'work_schedule';
    }

    protected function baseUrl(): string
    {
        return '/orari';
    }

    protected function title(): string
    {
        return 'Orari di lavoro';
    }

    protected function statLabel(): string
    {
        return 'Orari impostati';
    }

    protected function statComparison(): string
    {
        return "chi non ce l'ha lavora 9-18, pausa 13-14, lun-ven, 85% pianificabile";
    }

    protected function createLabel(): ?string
    {
        return 'Nuovo orario';
    }

    protected function createPermission(): ?string
    {
        return 'planner.team';
    }

    protected function decorateRows(array $rows): array
    {
        $names = [];
        foreach ($this->users->findAll() as $user) {
            $names[(int) $user->id] = trim("{$user->firstName} {$user->lastName}") ?: (string) $user->username;
        }

        return array_map(static function (array $row) use ($names) {
            $row['person'] = $names[(int) $row['userId']] ?? '';
            $row['hours'] = "{$row['startTime']}-{$row['endTime']}" . ($row['breakStart'] ? " (pausa {$row['breakStart']}-{$row['breakEnd']})" : '');
            $row['days'] = implode(' ', array_map(static fn ($d) => self::DAYS[trim($d)] ?? '', explode(',', (string) $row['workdays'])));
            $row['focus'] = ($row['focusPercent'] ?? 85) . '%';

            return $row;
        }, $rows);
    }

    protected function columns(): array
    {
        return [
            ['key' => 'person', 'label' => 'Persona'],
            ['key' => 'hours', 'label' => 'Orario'],
            ['key' => 'days', 'label' => 'Giorni'],
            ['key' => 'focus', 'label' => 'Pianificabile'],
        ];
    }

    protected function actions(): array
    {
        return [['label' => 'Modifica', 'href' => '/orari/{id}', 'permission' => 'planner.team']];
    }

    public function name(): string
    {
        return 'list_work_schedules';
    }

    public function description(): string
    {
        return "Orari di lavoro di ognuno (inizio, fine, pausa, giorni, quanta giornata pianificare): li usa l'organizer.";
    }

    public function menuLabel(): ?string
    {
        return 'Orari di lavoro';
    }

    public function menuSection(): ?string
    {
        return 'sistema';
    }

    public function requiredPermission(): ?string
    {
        return 'planner.team';
    }

    public function triggers(): array
    {
        return ['orari', 'orari di lavoro'];
    }
}
