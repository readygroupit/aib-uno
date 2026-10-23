<?php

declare(strict_types=1);

namespace App\View\Component;

/** Una riga di esito sopra una schermata ("Referente aggiunto."): tono success | info | warning | error. */
final class NoticeComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'notice',
            'tone' => $config['tone'] ?? 'info',
            'text' => $config['text'] ?? '',
        ];
    }
}
