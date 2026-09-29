<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditCustomerTool;
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

    protected function listToolClass(): string
    {
        return ListCustomersTool::class;
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
        return $action === 'export' ? 'customers.view' : parent::requiredPermissionForAction($action);
    }

    /**
     * CSV con la stessa situazione-pratica mostrata in tabella (vedi
     * ListCustomersTool::buildRow()), non solo l'anagrafica - a un
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
