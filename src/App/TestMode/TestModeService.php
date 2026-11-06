<?php

declare(strict_types=1);

namespace App\TestMode;

use App\Core\Container;
use App\Repository\SettingRepository;
use App\Repository\SimulatedActionRepository;
use App\Service\AuthService;

/**
 * Modalita' prova: per provare il gestionale senza effetti veri verso i
 * clienti o i soldi. Un interruttore unico (acceso di partenza) e poche
 * eccezioni da riaccendere una per una.
 *
 * Il controllo sta nei punti di uscita, non nelle pagine: MailService
 * (email a chi non e' un utente), CustomerMessageService e
 * WhatsAppService (link e messaggi ai clienti), PaymentService (link,
 * addebiti automatici), InvoiceService (SdI), SocialPublisher (social),
 * CalendarSyncService (Google Calendar). Ognuno chiede simulates() e, se
 * si', registra con record() cosa sarebbe successo e mostra una conferma
 * simulata.
 *
 * Impostazioni: 'test.enabled' ('1'/'0', assente = acceso), 'test.email' e
 * 'test.phone' (dove mandare email e WhatsApp ai clienti in prova; vuoti =
 * solo registro), 'test.real' (le eccezioni, separate da virgola; assente
 * = 'calendar', perche' gli inviti vanno solo ai colleghi). Senza tabella
 * settings (Uno) la modalita' prova non c'e'.
 */
final class TestModeService
{
    /** effetto => cosa vuol dire, per la pagina e il registro */
    public const EFFECTS = [
        'customer_email' => 'Email ai clienti',
        'whatsapp' => 'Messaggi WhatsApp ai clienti',
        'payments' => 'Pagamenti online (link e addebiti automatici)',
        'invoices' => 'Invio delle fatture allo SdI',
        'social' => 'Pubblicazione su Facebook e Instagram',
        'calendar' => 'Eventi e inviti su Google Calendar (solo colleghi)',
    ];
    private const DEFAULT_REAL = ['calendar'];

    private ?array $state = null;

    public function __construct(private readonly Container $container)
    {
    }

    /** @return array{available: bool, enabled: bool, email: string, phone: string, real: list<string>} */
    public function state(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }
        $saved = null;
        try {
            $settings = $this->container->get(SettingRepository::class);
            $saved = $settings->tableExists() ? $settings->valuesByPrefix('test.') : null;
        } catch (\Throwable) {
            $saved = null;
        }
        if ($saved === null) {
            return $this->state = ['available' => false, 'enabled' => false, 'email' => '', 'phone' => '', 'real' => array_keys(self::EFFECTS)];
        }
        $real = array_key_exists('test.real', $saved)
            ? array_values(array_intersect(array_filter(explode(',', (string) $saved['test.real'])), array_keys(self::EFFECTS)))
            : self::DEFAULT_REAL;

        return $this->state = [
            'available' => true,
            'enabled' => ($saved['test.enabled'] ?? '1') !== '0',
            'email' => (string) ($saved['test.email'] ?? ''),
            'phone' => (string) ($saved['test.phone'] ?? ''),
            'real' => $real,
        ];
    }

    public function isEnabled(): bool
    {
        return $this->state()['enabled'];
    }

    /** Vero se questo effetto ($effect, vedi EFFECTS) va simulato invece che fatto davvero. */
    public function simulates(string $effect): bool
    {
        $state = $this->state();

        return $state['enabled'] && !in_array($effect, $state['real'], true);
    }

    public function testEmail(): ?string
    {
        $email = $this->state()['email'];

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    public function testPhone(): ?string
    {
        $phone = $this->state()['phone'];

        return $phone !== '' ? $phone : null;
    }

    /** Registra cosa sarebbe successo davvero. */
    public function record(string $effect, string $summary, ?string $detail = null, ?string $recipient = null, ?string $entityType = null, ?int $entityId = null): void
    {
        try {
            $this->container->get(SimulatedActionRepository::class)->insert([
                'effect' => $effect,
                'summary' => mb_substr($summary, 0, 255),
                'detail' => $detail,
                'recipient' => $recipient !== null ? mb_substr($recipient, 0, 190) : null,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'created_by' => $this->container->get(AuthService::class)->currentUserId(),
            ]);
        } catch (\Throwable) {
            // Il registro non deve mai bloccare l'azione simulata.
        }
    }

    /**
     * @param array{enabled?: mixed, email?: mixed, phone?: mixed, real?: mixed} $input
     * @return ?string errore da mostrare
     */
    public function save(array $input): ?string
    {
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'L\'email per le prove non e\' valida.';
        }
        $phone = preg_replace('/[^\d+]/', '', (string) ($input['phone'] ?? ''));
        $real = array_values(array_intersect((array) ($input['real'] ?? []), array_keys(self::EFFECTS)));
        $settings = $this->container->get(SettingRepository::class);
        $settings->set('test.enabled', !empty($input['enabled']) ? '1' : '0');
        $settings->set('test.email', $email);
        $settings->set('test.phone', $phone);
        $settings->set('test.real', implode(',', $real));
        $this->state = null;

        return null;
    }

    /** Per la fascia in cima a ogni pagina (layout.phtml): null se la modalita' prova e' spenta. */
    public function banner(): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }
        $simulated = array_values(array_diff(array_keys(self::EFFECTS), $this->state()['real']));

        return [
            'text' => $simulated === []
                ? 'Modalita\' prova accesa, ma tutto e\' reale: controlla le eccezioni.'
                : 'Modalita\' prova: ' . self::listText(array_map(static fn (string $e) => self::SHORT[$e], $simulated)) . ' sono simulati, non parte niente di vero.',
            'href' => '/configurazione/prova',
        ];
    }

    /** "a, b e c" */
    private static function listText(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? (string) $last : implode(', ', $items) . ' e ' . $last;
    }

    private const SHORT = [
        'customer_email' => 'email ai clienti',
        'whatsapp' => 'WhatsApp',
        'payments' => 'pagamenti',
        'invoices' => 'fatture allo SdI',
        'social' => 'social',
        'calendar' => 'Google Calendar',
    ];
}
