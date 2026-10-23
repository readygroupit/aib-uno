<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Un fornitore di pagamenti online (Stripe, PayPal...). Il gestionale
 * chiede solo: un link per pagare un importo, e (se il fornitore lo
 * permette) salvare un metodo per gli addebiti automatici e addebitare.
 */
interface PaymentGatewayInterface
{
    public function code(): string;

    public function label(): string;

    public function isConnected(): bool;

    /**
     * Link alla pagina di pagamento del fornitore.
     *
     * @param array{reference:string, description:string, amountCents:int, email:?string, customerName:string, customerRef:?string, successUrl:string, cancelUrl:string, methods:list<string>} $request
     * @return array{url:string, id:string, customerRef:?string}
     */
    public function checkout(array $request): array;

    public function supportsAutoPay(): bool;
}
