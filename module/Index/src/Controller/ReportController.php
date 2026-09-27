<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Prompt\Tool\ShowReportTool;

final class ReportController extends AuthController
{
    protected ?string $requiredPermission = 'reports.view';

    public function indexAction(): ?string
    {
        $components = $this->container->get(ShowReportTool::class)->execute([]);

        return $this->renderPage($components, ['title' => 'Report']);
    }
}
