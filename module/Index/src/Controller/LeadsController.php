<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Model\Lead;
use App\Package\PackageManifest;
use App\Prompt\Tool\EditLeadTool;
use App\Repository\CustomerRepository;
use App\Prompt\Tool\ListLeadsTool;
use App\Repository\LeadRepository;
use App\Validation\FieldValidator;

/**
 * Stessa forma di UsersController (GET mostra, POST rielabora sulla
 * stessa rotta, mai un redirect), estesa su due punti:
 *  - i campi validi/il loro formato si leggono dal manifest del
 *    pacchetto (packages/leads/package.php), non sono ripetuti a mano
 *    qui - un campo aggiunto/tolto la' si riflette qui senza toccare
 *    questo file;
 *  - due azioni di trattativa reali (converti in cliente, segna come
 *    perso) e una deliberatamente non ancora implementata (invia email),
 *    esposte come pulsanti extra sullo stesso form - vedi
 *    FormComponent::secondaryActions.
 */
final class LeadsController extends AuthController
{
    protected ?string $requiredPermission = 'leads.manage';

    public function indexAction(): ?string
    {
        $page = max(1, (int) $this->param('page', 1));

        $components = $this->container->get(ListLeadsTool::class)->execute(['page' => $page]);

        return $this->renderPage($components, ['title' => 'Contatti']);
    }

    public function newAction(): ?string
    {
        /** @var EditLeadTool $tool */
        $tool = $this->container->get(EditLeadTool::class);

        if ($this->request->getMethod() !== 'POST') {
            return $this->renderPage([$tool->buildForm(new Lead())], ['title' => 'Nuovo contatto']);
        }

        [$data, $error] = $this->collectAndValidate();

        if ($error !== null) {
            $components = [$tool->buildForm(Lead::fromArray($data), $error, 'error')];
        } else {
            /** @var LeadRepository $leads */
            $leads = $this->container->get(LeadRepository::class);
            $data['stage'] ??= 'nuovo';
            $id = $leads->insert($data);

            $components = [$tool->buildForm($leads->find($id), 'Contatto creato.', 'success')];
        }

        return $this->renderPage($components, ['title' => 'Nuovo contatto']);
    }

    public function editAction(): ?string
    {
        $id = (int) $this->param('id');

        /** @var EditLeadTool $tool */
        $tool = $this->container->get(EditLeadTool::class);
        /** @var LeadRepository $leads */
        $leads = $this->container->get(LeadRepository::class);

        if ($this->request->getMethod() !== 'POST') {
            return $this->renderPage($tool->execute(['id' => $id]), ['title' => 'Modifica contatto']);
        }

        $lead = $leads->find($id);
        if ($lead === null) {
            return $this->renderPage($tool->execute(['id' => $id]), ['title' => 'Modifica contatto']);
        }

        [$data, $error] = $this->collectAndValidate();

        if ($error !== null) {
            // I valori appena digitati (non quelli salvati prima),
            // altrimenti un errore su UN campo farebbe sparire anche le
            // modifiche fatte bene sugli altri - vedi la stessa scelta
            // in newAction() qui sotto.
            $components = [$tool->buildForm(Lead::fromArray(array_merge($lead->toArray(), $data)), $error, 'error')];
        } else {
            $leads->update($id, $data);
            $components = [$tool->buildForm($leads->find($id), 'Modifiche salvate.', 'success')];
        }

        return $this->renderPage($components, ['title' => 'Modifica contatto']);
    }

    /**
     * Crea davvero un cliente (App\Repository\CustomerRepository, appena
     * introdotto proprio per questo) a partire dai campi del lead in
     * comune con 'customers', e lo collega. Azione reale, non un segnaposto.
     */
    public function convertAction(): ?string
    {
        $id = (int) $this->param('id');

        /** @var EditLeadTool $tool */
        $tool = $this->container->get(EditLeadTool::class);
        /** @var LeadRepository $leads */
        $leads = $this->container->get(LeadRepository::class);
        /** @var CustomerRepository $customers */
        $customers = $this->container->get(CustomerRepository::class);

        $lead = $leads->find($id);
        if ($lead === null) {
            return $this->renderPage($tool->execute(['id' => $id]), ['title' => 'Modifica contatto']);
        }

        if ($lead->convertedCustomerId !== null) {
            $components = [$tool->buildForm($lead, 'Questo contatto e\' gia\' stato convertito.', 'error')];

            return $this->renderPage($components, ['title' => 'Modifica contatto']);
        }

        $customerId = $customers->insert([
            'first_name' => $lead->firstName,
            'last_name' => $lead->lastName,
            'company_name' => $lead->companyName,
            'email' => $lead->email,
            'phone' => $lead->phone,
        ]);

        $leads->update($id, [
            'converted_customer_id' => $customerId,
            'converted_at' => date('Y-m-d H:i:s'),
            'stage' => 'convertito',
        ]);

        $components = [$tool->buildForm($leads->find($id), 'Contatto convertito in cliente #' . $customerId . '.', 'success')];

        return $this->renderPage($components, ['title' => 'Modifica contatto']);
    }

    public function markLostAction(): ?string
    {
        $id = (int) $this->param('id');

        /** @var EditLeadTool $tool */
        $tool = $this->container->get(EditLeadTool::class);
        /** @var LeadRepository $leads */
        $leads = $this->container->get(LeadRepository::class);

        $lead = $leads->find($id);
        if ($lead === null) {
            return $this->renderPage($tool->execute(['id' => $id]), ['title' => 'Modifica contatto']);
        }

        $leads->update($id, [
            'stage' => 'perso',
            'lost_reason' => trim((string) $this->request->get('lost_reason', '')) ?: null,
        ]);

        $components = [$tool->buildForm($leads->find($id), 'Contatto segnato come perso.', 'success')];

        return $this->renderPage($components, ['title' => 'Modifica contatto']);
    }

    /**
     * Deliberatamente non implementata: bottone presente sulla scheda
     * (vedi EditLeadTool::buildForm()) cosi' si vede gia' dove finira'
     * una futura capacita' di invio email, ma risponde onestamente che
     * non c'e' ancora nulla dietro invece di far finta o fallire muta.
     */
    public function sendEmailAction(): ?string
    {
        $id = (int) $this->param('id');

        /** @var EditLeadTool $tool */
        $tool = $this->container->get(EditLeadTool::class);
        /** @var LeadRepository $leads */
        $leads = $this->container->get(LeadRepository::class);

        $lead = $leads->find($id);
        if ($lead === null) {
            return $this->renderPage($tool->execute(['id' => $id]), ['title' => 'Modifica contatto']);
        }

        $components = [$tool->buildForm($lead, 'Funzione non ancora disponibile.', 'info')];

        return $this->renderPage($components, ['title' => 'Modifica contatto']);
    }

    /**
     * @return array{0: array<string, ?string>, 1: ?string}
     */
    private function collectAndValidate(): array
    {
        $fields = (new PackageManifest('leads'))->entities()['leads']['fields'];

        $data = [];
        $error = null;
        foreach ($fields as $key => $definition) {
            // null (mai '') distingue "il campo non fa parte di QUESTO
            // form" (es. converted_customer_id, gestito solo da
            // convertAction()) da "il campo c'e' ma e' stato svuotato" -
            // altrimenti ogni salvataggio normale azzererebbe anche i
            // campi non mostrati nel form di modifica.
            $raw = $this->request->get($key, null);
            if ($raw === null) {
                continue;
            }

            $value = trim((string) $raw);
            $data[$key] = $value !== '' ? $value : null;

            if ($value !== '' && isset($definition['format'])) {
                $error ??= FieldValidator::validate($definition['format'], $value, $definition['formatOptions'] ?? []);
            }
        }

        if ($error === null && ($data['email'] ?? null) === null) {
            $error = "L'email e' obbligatoria.";
        }

        return [$data, $error];
    }
}
