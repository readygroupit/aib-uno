<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Intestazione de "Il mio profilo": chi sei, da quando, quando hai
 * acceduto l'ultima volta - vedi ShowProfileTool. Stesso linguaggio
 * visivo dell'avatar a iniziali gia' usato nel cruscotto home
 * (DashboardHeaderComponent) e nella coda di approvazione, non un nuovo
 * stile inventato apposta.
 */
final class ProfileHeaderComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'profile-header',
            'initials' => $config['initials'] ?? '',
            'name' => $config['name'] ?? '',
            'roleLabel' => $config['roleLabel'] ?? '',
            'email' => $config['email'] ?? '',
            'memberSince' => $config['memberSince'] ?? null,
            'lastLogin' => $config['lastLogin'] ?? null,
        ];
    }
}
