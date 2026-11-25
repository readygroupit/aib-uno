<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Help\PageGuideService;

/** "Come funziona questa pagina": la guida della pagina in cui si e' (vedi PageGuideService). */
final class HelpController extends AuthController
{
    public function pageAction(): void
    {
        $guide = $this->container->get(PageGuideService::class)->forPath((string) $this->request->get('path', '/'));
        $this->json(['ok' => true, 'guide' => array_filter($guide, static fn ($v) => $v !== null)]);
    }
}
