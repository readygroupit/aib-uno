<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Connector\ConnectorException;
use App\Controller\AuthController;
use App\Customers\CustomerScope;
use App\Messaging\CustomerMessageService;

/**
 * Messaggi ai clienti dal gestionale: per ora WhatsApp dal numero
 * aziendale, dalla finestra "link per il cliente" (accounts-ui.js). JSON.
 */
final class MessagesController extends AuthController
{
    public function whatsappAction(): void
    {
        if ($this->request->getMethod() !== 'POST') {
            $this->json(['ok' => false, 'message' => 'Metodo non consentito.'], 405);

            return;
        }
        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        $customerId = (int) ($body['customerId'] ?? 0);
        if (class_exists(CustomerScope::class) && !$this->container->get(CustomerScope::class)->canSee($customerId)) {
            $this->json(['ok' => false, 'message' => 'Cliente non trovato.'], 404);

            return;
        }
        try {
            $message = $this->container->get(CustomerMessageService::class)->sendWhatsApp($customerId, (string) ($body['text'] ?? ''));
        } catch (ConnectorException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);

            return;
        }
        $this->json(['ok' => true, 'message' => $message]);
    }
}
