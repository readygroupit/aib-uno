<?php

declare(strict_types=1);

namespace App\ClientRequests;

use App\Connector\ConnectorRegistry;
use App\Connector\GmailConnector;
use App\Core\Container;
use App\Model\Customer;
use App\Model\CustomerContact;
use App\Model\User;
use App\Repository\ClientRequestRepository;
use App\Repository\CustomerContactRepository;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;

/**
 * Le email dei clienti (Gmail) diventano richieste "nuove", da smistare
 * come quelle segnate a mano: cliente riconosciuto dall'indirizzo del
 * cliente o di un referente, o dal dominio (sito o email aziendale, non
 * gmail.com e simili). Le altre email (colleghi, fornitori, newsletter,
 * risposte automatiche) si saltano. Una risposta in una conversazione gia'
 * diventata richiesta non ne crea un'altra.
 *
 * Non assegna e non crea attivita': chi smista legge e decide, come per le
 * richieste al telefono.
 */
final class EmailRequestImporter
{
    /** Domini di posta di tutti: non dicono di quale cliente si tratta. */
    private const PUBLIC_DOMAINS = [
        'gmail.com', 'googlemail.com', 'libero.it', 'virgilio.it', 'alice.it', 'tin.it', 'tim.it', 'tiscali.it',
        'hotmail.com', 'hotmail.it', 'outlook.com', 'outlook.it', 'live.com', 'live.it', 'msn.com',
        'yahoo.com', 'yahoo.it', 'icloud.com', 'me.com', 'mac.com', 'fastwebnet.it', 'email.it', 'inwind.it',
        'aruba.it', 'pec.it', 'legalmail.it', 'postecert.it', 'arubapec.it', 'protonmail.com', 'proton.me',
    ];
    private const MAX_DETAILS = 4000;

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function isReady(): bool
    {
        return $this->gmail()?->isConnected() ?? false;
    }

    /** @return array{created:int, replies:int, skipped:int} */
    public function import(int $limit = 50): array
    {
        $gmail = $this->gmail();
        if ($gmail === null || !$gmail->isConnected()) {
            return ['created' => 0, 'replies' => 0, 'skipped' => 0];
        }
        $messages = $gmail->newMessages($limit);
        [$outcome, $threads] = $this->importMessages($messages, $gmail->knownThreads(), $gmail->mailbox());
        $gmail->markRead($messages, $threads);

        return $outcome;
    }

    /**
     * Il lavoro vero, separato dalla lettura di Gmail (si prova con email finte).
     *
     * @param list<array{id:string, threadId:string, fromEmail:string, fromName:string, subject:string, date:string, text:string, link:string, automatic:bool}> $messages
     * @param array<string,int> $knownThreads conversazione => richiesta gia' creata
     * @return array{0: array{created:int, replies:int, skipped:int}, 1: array<string,int>} esito e conversazioni nuove
     */
    public function importMessages(array $messages, array $knownThreads, ?string $mailbox): array
    {
        $outcome = ['created' => 0, 'replies' => 0, 'skipped' => 0];
        $threads = [];
        if ($messages === []) {
            return [$outcome, $threads];
        }
        [$byEmail, $byDomain] = $this->customerMatchers();
        // Il dominio dell'agenzia non dice di quale cliente si tratta.
        unset($byDomain[self::domainOf((string) $mailbox) ?? '']);
        $staff = $this->staffEmails($mailbox);
        $requests = $this->container->get(ClientRequestRepository::class);

        foreach ($messages as $message) {
            $thread = $message['threadId'];
            if (isset($knownThreads[$thread]) || isset($threads[$thread])) {
                $outcome['replies']++;
                continue;
            }
            $from = $message['fromEmail'];
            $customerId = $message['automatic'] || isset($staff[$from]) ? null : self::matchCustomer($from, $byEmail, $byDomain);
            if ($customerId === null) {
                $outcome['skipped']++;
                continue;
            }
            $subject = $message['subject'] !== '' ? $message['subject'] : self::firstLine($message['text']);
            $sender = $message['fromName'] !== '' ? "{$message['fromName']} <{$from}>" : $from;
            $text = mb_strlen($message['text']) > self::MAX_DETAILS ? mb_substr($message['text'], 0, self::MAX_DETAILS) . '...' : $message['text'];
            $id = $requests->insert([
                'customer_id' => $customerId,
                'summary' => mb_substr($subject !== '' ? $subject : 'Email da ' . $from, 0, 255),
                'details' => "Email da {$sender}" . ($message['subject'] !== '' ? "\nOggetto: {$message['subject']}" : '') . "\n\n{$text}\n\nApri in Gmail: {$message['link']}",
                'channel' => 'email',
                'received_at' => $message['date'],
                'stage' => 'nuova',
            ]);
            $threads[$thread] = $id;
            $outcome['created']++;
        }

        return [$outcome, $threads];
    }

    /** Prima l'indirizzo esatto (cliente o referente), poi il dominio aziendale se e' di un solo cliente. */
    public static function matchCustomer(string $email, array $byEmail, array $byDomain): ?int
    {
        $email = strtolower(trim($email));
        if (isset($byEmail[$email])) {
            return $byEmail[$email];
        }
        $domain = self::domainOf($email);
        $customers = $domain !== null ? array_unique($byDomain[$domain] ?? []) : [];

        return count($customers) === 1 ? (int) reset($customers) : null;
    }

    /** @return array{0: array<string,int>, 1: array<string,list<int>>} indirizzo => cliente, dominio => clienti */
    private function customerMatchers(): array
    {
        $byEmail = [];
        $byDomain = [];
        $add = static function (?string $email, int $customerId) use (&$byEmail, &$byDomain): void {
            $email = strtolower(trim((string) $email));
            if ($email === '' || !str_contains($email, '@')) {
                return;
            }
            $byEmail[$email] ??= $customerId;
            $domain = self::domainOf($email);
            if ($domain !== null) {
                $byDomain[$domain][] = $customerId;
            }
        };
        foreach ($this->container->get(CustomerRepository::class)->findAll() as $customer) {
            /** @var Customer $customer */
            $add($customer->email, (int) $customer->id);
            $host = strtolower((string) parse_url(str_contains((string) $customer->website, '://') ? (string) $customer->website : 'http://' . $customer->website, PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host);
            if ($host !== '' && $host !== null && str_contains($host, '.') && !in_array($host, self::PUBLIC_DOMAINS, true)) {
                $byDomain[$host][] = (int) $customer->id;
            }
        }
        foreach ($this->container->get(CustomerContactRepository::class)->findAll() as $contact) {
            /** @var CustomerContact $contact */
            if ($contact->customerId !== null) {
                $add($contact->email, (int) $contact->customerId);
            }
        }

        return [$byEmail, $byDomain];
    }

    /** @return array<string,true> colleghi e la casella stessa: non sono clienti */
    private function staffEmails(?string $mailbox): array
    {
        $result = [];
        foreach ($this->container->get(UserRepository::class)->findAll() as $user) {
            /** @var User $user */
            if ($user->email) {
                $result[strtolower($user->email)] = true;
            }
        }
        if ($mailbox !== null) {
            $result[strtolower($mailbox)] = true;
        }

        return $result;
    }

    private static function domainOf(string $email): ?string
    {
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        return $domain !== '' && !in_array($domain, self::PUBLIC_DOMAINS, true) ? $domain : null;
    }

    private static function firstLine(string $text): string
    {
        foreach (explode("\n", $text) as $line) {
            if (trim($line) !== '') {
                return mb_substr(trim($line), 0, 120);
            }
        }

        return '';
    }

    private function gmail(): ?GmailConnector
    {
        $registry = $this->container->get(ConnectorRegistry::class);
        $gmail = $registry->has('gmail') ? $registry->get('gmail') : null;

        return $gmail instanceof GmailConnector ? $gmail : null;
    }
}
