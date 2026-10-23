<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Connector\ConnectorException;
use App\Connector\ConnectorRegistry;
use App\Connector\StripeConnector;
use App\Controller\AbstractController;
use App\Payments\PaymentService;

/**
 * Le conferme dei fornitori di pagamento, pubbliche:
 * - webhook di Stripe (senza CSRF: lo protegge la firma Stripe-Signature);
 * - ritorno da PayPal, dove si incassa l'ordine approvato dal cliente.
 */
final class PaymentsController extends AbstractController
{
    public function stripeWebhookAction(): void
    {
        $registry = $this->container->get(ConnectorRegistry::class);
        $stripe = $registry->has('stripe') ? $registry->get('stripe') : null;
        $event = $stripe instanceof StripeConnector ? $stripe->verifyWebhook($this->request->rawBody(), (string) $this->request->header('Stripe-Signature')) : null;
        if ($event === null) {
            $this->json(['ok' => false], 400);

            return;
        }
        try {
            $outcome = $this->container->get(PaymentService::class)->handleStripeEvent($event);
        } catch (ConnectorException $e) {
            // 500: Stripe riprova da solo piu' tardi.
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);

            return;
        }
        $this->json(['ok' => true, 'outcome' => $outcome]);
    }

    public function paypalReturnAction(): void
    {
        $back = (string) $this->request->get('ritorno', '/scadenzario');
        // Solo un percorso di questo sito: mai un redirect verso fuori.
        if (!str_starts_with($back, '/') || str_starts_with($back, '//')) {
            $back = '/scadenzario';
        }
        $separator = str_contains($back, '?') ? '&' : '?';
        $orderId = (string) $this->request->get('token', '');
        try {
            $ok = $orderId !== '' && $this->container->get(PaymentService::class)->capturePayPal($orderId);
        } catch (ConnectorException) {
            $ok = false;
        }
        $this->redirect($back . $separator . 'pagamento=' . ($ok ? 'ok' : 'errore'));
    }
}
