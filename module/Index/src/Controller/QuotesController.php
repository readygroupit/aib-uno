<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Model\Quote;
use App\Portal\PortalService;
use App\Prompt\Tool\EditQuoteTool;
use App\Prompt\Tool\ShowQuotesTool;
use App\Quotes\QuoteOverviewService;
use App\Quotes\QuoteService;
use App\Repository\CompanyProfileRepository;
use App\Repository\CustomerRepository;
use App\Repository\QuoteRepository;
use App\Service\AuthService;
use App\Package\PackageCatalogService;

/**
 * Preventivi. Il passo successivo (approvazione, invio, accettazione,
 * rifiuto) e' un'azione sola (passoAction); la stampa e' una pagina a se'
 * da salvare in PDF dal browser.
 */
final class QuotesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return QuoteRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditQuoteTool::class;
    }

    protected function listToolClass(): string
    {
        return ShowQuotesTool::class;
    }

    protected function packageName(): string
    {
        return 'quotes';
    }

    protected function listPageTitle(): string
    {
        return 'Preventivi';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo preventivo';
    }

    protected function editPageTitle(): string
    {
        return 'Preventivo';
    }

    protected function defaultsOnCreate(): array
    {
        return [
            'stage' => 'bozza',
            'quote_date' => date('Y-m-d'),
            'valid_until' => date('Y-m-d', strtotime('+30 days')),
            'owner_user_id' => (string) $this->container->get(AuthService::class)->currentUserId(),
            'included_revisions' => '2',
        ];
    }

    protected function createdMessage(): string
    {
        return 'Preventivo creato: aggiungi le righe dal listino.';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'step', 'clientLink' => 'quotes.edit',
            'print' => 'quotes.view',
            default => parent::requiredPermissionForAction($action),
        };
    }

    protected function afterCreate(int $id): void
    {
        $service = $this->container->get(QuoteService::class);
        $service->initialize($id);
        $service->recalc($id);
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $this->container->get(QuoteService::class)->recalc($id);

        return null;
    }

    public function stepAction(): void
    {
        $auth = $this->container->get(AuthService::class);
        try {
            $message = $this->container->get(QuoteService::class)->advance(
                (int) $this->param('id'),
                (string) $this->request->get('passo', ''),
                (int) $auth->currentUserId(),
                $auth->hasPermission('quotes.approve'),
                (string) $this->request->get('text', '')
            );
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);

            return;
        }
        $this->json(['ok' => true, 'message' => $message]);
    }

    /** Il link dell'area del cliente, dove vede il preventivo e lo accetta. */
    public function clientLinkAction(): void
    {
        $quote = $this->repository()->find((int) $this->param('id'));
        $customer = $quote instanceof Quote ? $this->container->get(CustomerRepository::class)->find((int) $quote->customerId) : null;
        if ($customer === null) {
            $this->json(['ok' => false, 'message' => 'Cliente non trovato.'], 404);

            return;
        }
        $url = $this->container->get(PortalService::class)->link((int) $customer->id, $this->request->baseUrl());
        $this->json(['ok' => true, 'share' => [
            'title' => 'Preventivo per il cliente',
            'intro' => "Nella sua area {$customer->displayName()} vede il preventivo {$quote->quoteNumber} e lo accetta con un clic. Prima segnalo come inviato.",
            'url' => $url,
            'email' => $customer->email,
            'subject' => 'La nostra proposta: ' . $quote->title,
            'text' => "Buongiorno,\nqui trova la nostra proposta \u{ab}{$quote->title}\u{bb}: puo' leggerla e accettarla direttamente da questo link.\n{$url}\n\nRestiamo a disposizione per qualsiasi domanda.",
        ]]);
    }

    /** Pagina da stampare o salvare in PDF dal browser. */
    public function printAction(): string
    {
        $quote = $this->repository()->find((int) $this->param('id'));
        if (!$quote instanceof Quote) {
            $this->response->setStatusCode(404);

            return '';
        }
        $detail = $this->container->get(QuoteOverviewService::class)->detail($quote, false);
        $company = in_array('invoices', PackageCatalogService::installedNames(), true) ? $this->container->get(CompanyProfileRepository::class)->current() : null;

        return $this->render('quotes/print', [
            'title' => 'Preventivo ' . $quote->quoteNumber,
            'quote' => $detail,
            'company' => $company,
            'customer' => $this->container->get(CustomerRepository::class)->find((int) $quote->customerId),
        ], false);
    }
}
