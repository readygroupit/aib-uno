<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Portal\PortalService;
use App\Prompt\Tool\EditCustomerTool;
use App\Prompt\PromptToolRegistry;
use App\Prompt\Tool\ListCustomersTool;
use App\Repository\CustomerRepository;

/**
 * Primo vero utilizzo di AbstractEntityController: solo i dati
 * specifici di 'customers' - index/new/edit e la validazione sono
 * ereditati.
 */
final class CustomersController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return CustomerRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditCustomerTool::class;
    }

    /** La lista registrata come 'list_customers': generica, o con le pratiche dove ci sono (vedi config/prompt-tools.php). */
    protected function listToolClass(): string
    {
        $registry = $this->container->get(PromptToolRegistry::class);

        return $registry->has('list_customers') ? $registry->get('list_customers')::class : ListCustomersTool::class;
    }

    protected function packageName(): string
    {
        return 'customers';
    }

    protected function listPageTitle(): string
    {
        return 'Clienti';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo cliente';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica cliente';
    }

    protected function createdMessage(): string
    {
        return 'Cliente creato.';
    }

    /** 'export' non e' fra i 4 casi standard di AbstractEntityController: stesso permesso di 'index', e' solo un altro modo di leggere la stessa lista. */
    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'export' => 'customers.view',
            'portalLink' => 'customers.edit',
            default => parent::requiredPermissionForAction($action),
        };
    }

    /**
     * Il link dell'area del cliente (contratto, pagamenti, approvazioni,
     * senza password), con un messaggio pronto. ?nuovo=1 lo sostituisce:
     * il vecchio smette di funzionare.
     */
    public function portalLinkAction(): void
    {
        $customer = $this->repository()->find((int) $this->param('id'));
        if ($customer === null) {
            $this->json(['ok' => false, 'message' => 'Cliente non trovato.'], 404);

            return;
        }
        $renew = $this->request->get('nuovo') === '1';
        $url = $this->container->get(PortalService::class)->link((int) $customer->id, $this->request->baseUrl(), $renew);

        $this->json(['ok' => true, 'share' => [
            'title' => $renew ? 'Nuovo link dell\'area cliente' : 'Area cliente',
            'intro' => ($renew ? 'Il link di prima non funziona piu\'. ' : '') . "Con questo link {$customer->displayName()} vede servizi, rinnovi e pagamenti, paga online, sceglie come pagare e approva i contenuti. Niente password: mandalo solo a lui.",
            'url' => $url,
            'email' => $customer->email,
            'subject' => 'La tua area',
            'text' => "Ciao,\nqui trovi la tua area: servizi attivi, rinnovi, pagamenti e i contenuti da approvare.\n{$url}\n\nE' personale, tienila per te. A presto!",
        ]]);
    }

    /**
     * CSV con la stessa situazione-pratica mostrata in tabella (vedi
     * ListCustomerClaimsTool::buildRow()), non solo l'anagrafica - a un
     * operatore che scarica l'elenco serve poter aprire il file e
     * vedere subito volo/stato/importo, non doverli ricostruire a mano
     * incrociando altre schermate. Delimitatore ';' e virgola come
     * separatore decimale: locale italiano di Excel, non quello US.
     */
    public function exportAction(): string
    {
        /** @var CustomerRepository $repo */
        $repo = $this->repository();
        $data = $repo->findWithClaimSummary();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Cliente', 'Tipo', 'Email', 'Volo', 'Vettore', 'Disservizio', 'Stato pratica', 'Documenti completi', 'Importo richiesto (€)', 'Importo recuperato (€)', 'Stato pagamento'], ';');

        foreach ($data as $entry) {
            $customer = $entry['customer'];
            $case = $entry['case'];
            $lead = $entry['lead'];
            $refund = $entry['refund'];

            $isCompany = $customer->companyName !== null && trim("{$customer->firstName}{$customer->lastName}") !== '';
            $name = trim("{$customer->firstName} {$customer->lastName}") ?: ($customer->companyName ?? '(senza nome)');

            fputcsv($handle, [
                $name,
                $isCompany ? 'Azienda' : 'Privato',
                $customer->email ?? '',
                $lead['flight_route'] ?? '',
                $lead['airline'] ?? '',
                $lead['disservice_type'] ?? '',
                $case['stage'] ?? '',
                $entry['docsTotal'] > 0 ? "{$entry['docsComplete']}/{$entry['docsTotal']}" : '',
                $refund !== null ? number_format((float) $refund['amount_claimed'], 2, ',', '') : '',
                $refund !== null && $refund['payment_status'] === 'pagato' ? number_format((float) $refund['amount_accepted'], 2, ',', '') : '',
                $refund['payment_status'] ?? '',
            ], ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $this->response->addHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->response->addHeader('Content-Disposition', 'attachment; filename="clienti-' . date('Y-m-d') . '.csv"');

        // BOM: senza, Excel su Windows apre gli accenti come caratteri illeggibili.
        return "\xEF\xBB\xBF" . $csv;
    }
}
