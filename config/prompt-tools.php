<?php

declare(strict_types=1);

use App\Prompt\PromptToolRegistry;
use App\Prompt\Tool\EditAppointmentTool;
use App\Prompt\Tool\EditBlogPostTool;
use App\Prompt\Tool\EditCampaignTool;
use App\Prompt\Tool\EditCaseTool;
use App\Prompt\Tool\EditCommunicationTool;
use App\Prompt\Tool\EditCustomerTool;
use App\Prompt\Tool\EditDocumentRequestTool;
use App\Prompt\Tool\EditInterventionTool;
use App\Prompt\Tool\EditInvoiceTool;
use App\Prompt\Tool\EditLeadTool;
use App\Prompt\Tool\EditPermissionTool;
use App\Prompt\Tool\EditProductTool;
use App\Prompt\Tool\EditRefundTool;
use App\Prompt\Tool\EditServiceTool;
use App\Prompt\Tool\EditTaskTool;
use App\Prompt\Tool\EditUserTool;
use App\Prompt\Tool\InstallPackageTool;
use App\Prompt\Tool\ListAppointmentsTool;
use App\Prompt\Tool\ListBlogPostsTool;
use App\Prompt\Tool\ListCampaignsTool;
use App\Prompt\Tool\ListCasesTool;
use App\Prompt\Tool\ListCommunicationsTool;
use App\Prompt\Tool\ListCustomersTool;
use App\Prompt\Tool\ListDocumentRequestsTool;
use App\Prompt\Tool\ListInterventionsTool;
use App\Prompt\Tool\ListInvoicesTool;
use App\Prompt\Tool\ListLeadDuplicatesTool;
use App\Prompt\Tool\ListLeadsTool;
use App\Prompt\Tool\ListMessageTemplatesTool;
use App\Prompt\Tool\EditMessageTemplateTool;
use App\Prompt\Tool\ListPackagesTool;
use App\Prompt\Tool\ListPermissionsTool;
use App\Prompt\Tool\ListProductsTool;
use App\Prompt\Tool\ListRefundsTool;
use App\Prompt\Tool\ListServicesTool;
use App\Prompt\Tool\ListTasksTool;
use App\Prompt\Tool\ListUsersTool;
use App\Prompt\Tool\ShowHomeDashboardTool;
use App\Prompt\Tool\ShowMenuTool;
use App\Prompt\Tool\ShowAgentsTool;
use App\Prompt\Tool\ShowReportTool;
use App\Prompt\Tool\ShowSetupTool;
use App\Prompt\Tool\ShowWizardTool;

return static function (PromptToolRegistry $registry): void {
    // Prima usato solo da IndexController per la home vera e propria -
    // registrato anche qui perche' il pulsante "casa" della toolbar del
    // prompt (vedi hero.js) lo raggiunge tramite window.unoSubmitPrompt
    // ('home'), che passa sempre da matchLocalTool() su questo registro.
    $registry->register(ShowHomeDashboardTool::class);
    $registry->register(ShowMenuTool::class);
    $registry->register(ShowWizardTool::class);
    $registry->register(ListUsersTool::class);
    $registry->register(ListPermissionsTool::class);
    $registry->register(ListLeadsTool::class);
    $registry->register(ListLeadDuplicatesTool::class);
    $registry->register(ListCustomersTool::class);
    $registry->register(ListTasksTool::class);
    $registry->register(ListAppointmentsTool::class);
    $registry->register(ListProductsTool::class);
    $registry->register(ListServicesTool::class);
    $registry->register(ListInterventionsTool::class);
    $registry->register(ListBlogPostsTool::class);
    $registry->register(ListCampaignsTool::class);
    $registry->register(ListInvoicesTool::class);
    $registry->register(ListCasesTool::class);
    $registry->register(ListDocumentRequestsTool::class);
    $registry->register(ListCommunicationsTool::class);
    $registry->register(ListRefundsTool::class);
    $registry->register(EditUserTool::class);
    $registry->register(EditPermissionTool::class);
    $registry->register(EditLeadTool::class);
    $registry->register(EditCustomerTool::class);
    $registry->register(EditTaskTool::class);
    $registry->register(EditAppointmentTool::class);
    $registry->register(EditProductTool::class);
    $registry->register(EditServiceTool::class);
    $registry->register(EditInterventionTool::class);
    $registry->register(EditBlogPostTool::class);
    $registry->register(EditCampaignTool::class);
    $registry->register(EditInvoiceTool::class);
    $registry->register(EditCaseTool::class);
    $registry->register(EditDocumentRequestTool::class);
    $registry->register(EditCommunicationTool::class);
    $registry->register(EditRefundTool::class);
    $registry->register(ListMessageTemplatesTool::class);
    $registry->register(EditMessageTemplateTool::class);
    $registry->register(ListPackagesTool::class);
    $registry->register(InstallPackageTool::class);
    $registry->register(ShowReportTool::class);
    $registry->register(ShowSetupTool::class);
    $registry->register(ShowAgentsTool::class);
};
