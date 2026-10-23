<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\Profile;
use App\Repository\PermissionRepository;
use App\Repository\ProfilePermissionRepository;
use App\View\Component\FormComponent;

/**
 * Il form di un profilo: nome, descrizione e i permessi, una casella per
 * ogni area (Clienti, Piano editoriale, Preventivi...) con i suoi permessi
 * da accendere o spegnere.
 */
final class EditProfileTool
{
    private PermissionRepository $permissions;
    private ProfilePermissionRepository $grants;
    private FormComponent $form;

    public function __construct(Container $container)
    {
        $this->permissions = $container->get(PermissionRepository::class);
        $this->grants = $container->get(ProfilePermissionRepository::class);
        $this->form = $container->get(FormComponent::class);
    }

    /** @return array<string, array{label:string, permissions:list<array{id:int,name:string}>}> area => permessi */
    public function areas(): array
    {
        $areas = [];
        foreach ($this->permissions->findAllWithDomain() as $row) {
            $key = 'perm_' . preg_replace('/[^a-z0-9_]/', '_', (string) ($row['domainCode'] ?: 'altro'));
            $areas[$key] ??= ['label' => (string) $row['domain'], 'permissions' => []];
            $areas[$key]['permissions'][] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
        }

        return $areas;
    }

    public function buildForm(?Profile $profile, ?string $message = null, ?string $messageType = null): array
    {
        $granted = $profile !== null ? array_flip($this->grants->permissionIdsForProfile((int) $profile->id)) : [];
        $fields = [
            ['key' => 'name', 'label' => 'Nome del profilo', 'value' => $profile?->name, 'required' => true, 'width' => 'half'],
            ['key' => 'description', 'label' => 'Cosa fa chi ha questo profilo', 'value' => $profile?->description, 'width' => 'half'],
        ];
        $boxes = [['title' => 'Profilo', 'area' => 'main', 'fields' => ['name', 'description']]];
        foreach ($this->areas() as $key => $area) {
            $fields[] = [
                'key' => $key,
                'label' => 'Puo\'',
                'inputType' => 'checkboxes',
                'width' => 'full',
                'value' => implode(',', array_map(static fn (array $p) => (string) $p['id'], array_filter($area['permissions'], static fn (array $p) => isset($granted[$p['id']])))),
                'choices' => array_map(static fn (array $p) => ['value' => (string) $p['id'], 'label' => $p['name']], $area['permissions']),
            ];
            $boxes[] = ['title' => $area['label'], 'area' => 'main', 'fields' => [$key]];
        }

        return $this->form->toData([
            'entity' => 'profile',
            'title' => $profile !== null ? 'Profilo: ' . $profile->name : 'Nuovo profilo',
            'action' => $profile !== null ? '/profili/' . $profile->id : '/profili/nuovo',
            'fields' => $fields,
            'layout' => ['sections' => [['label' => null, 'boxes' => $boxes]]],
            'submitLabel' => 'Salva il profilo',
            'message' => $message ?? 'I permessi nuovi valgono dal prossimo accesso di chi ha questo profilo.',
            'messageType' => $messageType ?? 'info',
        ]);
    }
}
