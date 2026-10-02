<?php

declare(strict_types=1);

namespace App\Connector;

/**
 * Un connettore verso un sistema esterno (Jotform, Google Sheets, ecc.) -
 * stesso principio di registrazione esplicita di PromptToolRegistry/
 * JobHandlerRegistry (vedi ConnectorRegistry), non auto-discovery. Questa
 * interfaccia e' lo strato comune minimo; l'autenticazione vera e propria
 * (dove prendere la chiave/il token, come rinnovarlo) vive nelle due
 * classi base AbstractApiKeyConnector/AbstractOAuthConnector, non qui -
 * un connettore concreto estende una di quelle due, non implementa
 * questa interfaccia direttamente.
 *
 * Oltre al test delle credenziali gia' salvate, l'interfaccia descrive
 * come collegarlo da UI: setupSpec() e' la procedura guidata (passi + campi
 * da compilare), connect() verifica quello che l'operatore ha inserito e
 * salva SOLO se funziona - mai credenziali sbagliate a database.
 */
interface ConnectorInterface
{
    /** Identificativo stabile, stesso valore usato in connector_credentials.connector_code. */
    public function code(): string;

    public function label(): string;

    /**
     * Vero se le credenziali salvate ora sembrano valide e sufficienti
     * per usare il connettore - una chiamata leggera al servizio esterno
     * quando possibile, o almeno un controllo di presenza/coerenza dei
     * dati salvati.
     */
    public function test(): bool;

    /**
     * @return array{
     *     intro: string,
     *     steps: list<string>,
     *     fields: list<array{key: string, label: string, type: string, help?: string, placeholder?: string, options?: list<array{value: string, label: string}>}>,
     *     note: string
     * }
     */
    public function setupSpec(): array;

    /**
     * Verifica $input contro il servizio vero e, solo se funziona, lo
     * salva. Restituisce una frase di riepilogo ("3 moduli trovati").
     *
     * @param array<string, string> $input
     * @throws ConnectorException con un messaggio leggibile dall'operatore
     */
    public function connect(array $input): string;

    public function disconnect(): void;

    /** Vero se esiste una credenziale salvata che ha superato connect(). */
    public function isConnected(): bool;

    /** Riepilogo dell'ultima verifica riuscita (es. "3 moduli trovati") o null. */
    public function summary(): ?string;
}
