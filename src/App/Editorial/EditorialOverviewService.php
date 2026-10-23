<?php

declare(strict_types=1);

namespace App\Editorial;

use App\Core\Container;
use App\Model\EditorialPost;
use App\Repository\CustomerRepository;
use App\Repository\EditorialPostRepository;
use App\Repository\EditorialQuotaRepository;
use App\Repository\EditorialStrategyRepository;
use App\Repository\UserRepository;
use App\Support\ItalianFormat as F;

/**
 * Il piano editoriale da mostrare: la settimana, una riga per cliente,
 * i contenuti nei giorni; i buchi rispetto al contratto; cosa aspetta
 * l'approvazione del cliente; la scheda di un contenuto.
 */
final class EditorialOverviewService
{
    public const STAGE_TONES = [
        'idea' => 'neutral',
        'in lavorazione' => 'info',
        'revisione interna' => 'accent',
        'da approvare' => 'warning',
        'da modificare' => 'danger',
        'approvato' => 'success',
        'programmato' => 'strong',
        'pubblicato' => 'success',
        'scartato' => 'neutral',
    ];

    private Container $container;
    private EditorialPostRepository $posts;
    private EditorialQuotaRepository $quotas;
    private CustomerRepository $customers;
    private UserRepository $users;
    private string $today;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->posts = $container->get(EditorialPostRepository::class);
        $this->quotas = $container->get(EditorialQuotaRepository::class);
        $this->customers = $container->get(CustomerRepository::class);
        $this->users = $container->get(UserRepository::class);
        $this->today = date('Y-m-d');
    }

    /** Una settimana da lunedi': in due le etichette dei contenuti diventano illeggibili. */
    public function board(string $monday, int $length = 7): array
    {
        $to = date('Y-m-d', strtotime("{$monday} +" . ($length - 1) . ' days'));
        $posts = $this->posts->findBetween($monday, $to);
        $names = $this->customerNames();
        $people = $this->userInitials();

        $days = [];
        for ($i = 0; $i < $length; $i++) {
            $date = date('Y-m-d', strtotime("{$monday} +{$i} days"));
            $days[] = ['date' => $date, 'label' => ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'][$i % 7] . ' ' . (int) date('j', strtotime($date)), 'isToday' => $date === $this->today, 'isWeekend' => $i % 7 >= 5];
        }

        // Una riga per ogni cliente con contratto o con contenuti nel periodo.
        $rows = [];
        foreach ($this->quotas->findAll([], 'customer_id') as $quota) {
            $rows[(int) $quota->customerId] ??= ['customerId' => (int) $quota->customerId, 'name' => $names[(int) $quota->customerId] ?? '', 'quota' => [], 'posts' => []];
            $rows[(int) $quota->customerId]['quota'][] = $quota->channel . ' ' . $quota->perWeek . '/sett.';
        }
        foreach ($posts as $post) {
            $rows[(int) $post->customerId] ??= ['customerId' => (int) $post->customerId, 'name' => $names[(int) $post->customerId] ?? '', 'quota' => [], 'posts' => []];
            $rows[(int) $post->customerId]['posts'][(string) $post->publishDate][] = $this->chip($post, $people);
        }
        uasort($rows, static fn (array $a, array $b) => strcmp($a['name'], $b['name']));
        $strategies = $this->container->get(EditorialStrategyRepository::class)->idsByCustomer();
        $review = $this->reviewQueue();
        $revisions = $this->revisionsLastMonth();
        foreach ($rows as &$row) {
            $id = $row['customerId'];
            $row['href'] = '/clienti/' . $id;
            $row['newHref'] = '/piano-editoriale/nuovo?customer_id=' . $id;
            $row['strategyHref'] = isset($strategies[$id]) ? '/piano-editoriale/strategie/' . $strategies[$id] : '/piano-editoriale/strategie/nuovo?customer_id=' . $id;
            $row['hasStrategy'] = isset($strategies[$id]);
            $row['revisions'] = $revisions[$id] ?? 0;
            $row['toReview'] = $review[$id]['count'] ?? 0;
            $row['cells'] = array_map(static fn (array $d) => ['date' => $d['date'], 'posts' => $row['posts'][$d['date']] ?? []], $days);
            unset($row['posts']);
        }
        unset($row);

        // I numeri di testa guardano a oggi, non alla settimana sfogliata.
        $around = $this->posts->findBetween(date('Y-m-d', strtotime($this->today . ' -14 days')), date('Y-m-d', strtotime($this->today . ' +28 days')));
        $open = array_filter($around, static fn (EditorialPost $p) => $p->isOpen());
        $weekEnd = date('Y-m-d', strtotime($this->today . ' +6 days'));

        return [
            'days' => $days,
            'rows' => array_values($rows),
            'gaps' => $this->gaps(),
            'review' => array_values($review),
            'totals' => [
                'thisWeek' => count(array_filter($around, fn (EditorialPost $p) => $p->stage !== 'scartato' && $p->publishDate >= $this->today && $p->publishDate <= $weekEnd)),
                'toApprove' => count(array_filter($open, static fn (EditorialPost $p) => $p->stage === 'da approvare')),
                'toReview' => count(array_filter($open, static fn (EditorialPost $p) => $p->stage === 'revisione interna')),
                'toChange' => count(array_filter($open, static fn (EditorialPost $p) => $p->stage === 'da modificare')),
                'toPrepare' => count(array_filter($open, fn (EditorialPost $p) => in_array($p->stage, ['idea', 'in lavorazione'], true) && $p->publishDate <= date('Y-m-d', strtotime($this->today . ' +7 days')))),
            ],
        ];
    }

    /**
     * Contratto contro piano, nelle prossime due settimane.
     *
     * @return list<array{customer:string, channel:string, missing:int, text:string, action:string}>
     */
    public function gaps(): array
    {
        $from = $this->today;
        $to = date('Y-m-d', strtotime("{$from} +13 days"));
        $counts = $this->posts->countPerCustomerChannel($from, $to);
        $names = $this->customerNames();
        $strategies = $this->container->get(EditorialStrategyRepository::class)->idsByCustomer();
        $gaps = [];
        foreach ($this->quotas->findAll() as $quota) {
            $needed = 2 * (int) $quota->perWeek;
            $have = $counts["{$quota->customerId}|{$quota->channel}"] ?? 0;
            if ($have >= $needed) {
                continue;
            }
            $missing = $needed - $have;
            $name = $names[(int) $quota->customerId] ?? '';
            $gaps[] = [
                'customer' => $name,
                'channel' => (string) $quota->channel,
                'missing' => $missing,
                'text' => "{$name}, {$quota->channel}: " . F::plural($have, 'contenuto', 'contenuti') . " in programma su {$needed} da contratto nelle prossime 2 settimane.",
                'action' => '/piano-editoriale/riempi?cliente=' . $quota->customerId . '&canale=' . rawurlencode((string) $quota->channel),
                'label' => 'Crea ' . F::plural($missing, 'bozza', 'bozze'),
                // Con la strategia del cliente l'IA propone anche i temi.
                'aiAction' => isset($strategies[(int) $quota->customerId]) ? '/piano-editoriale/riempi?cliente=' . $quota->customerId . '&canale=' . rawurlencode((string) $quota->channel) . '&ia=1' : null,
            ];
        }

        return $gaps;
    }

    /** Scheda di un contenuto: anteprima, stato, chi, cliente. */
    public function detail(EditorialPost $post): array
    {
        $customer = $this->customers->find((int) $post->customerId);
        $user = $post->assignedUserId !== null ? $this->users->find((int) $post->assignedUserId) : null;
        $writer = $post->writerUserId !== null ? $this->users->find((int) $post->writerUserId) : null;
        $approver = $post->internalApprovedBy !== null ? $this->users->find((int) $post->internalApprovedBy) : null;
        $parent = $post->parentPostId !== null ? $this->posts->find((int) $post->parentPostId) : null;
        $children = $this->posts->findAll(['parent_post_id' => $post->id], 'id');

        return [
            'title' => (string) $post->title,
            'caption' => $post->caption,
            'channel' => (string) $post->channel,
            'format' => (string) $post->format,
            'stage' => (string) $post->stage,
            'stageTone' => self::STAGE_TONES[$post->stage] ?? 'neutral',
            'when' => F::longDate($post->publishDate) . ($post->publishTime ? ' alle ' . $post->publishTime : '') . ' (' . F::relativeDays((string) $post->publishDate, $this->today) . ')',
            'customer' => $customer?->displayName(),
            'customerHref' => $customer !== null ? '/clienti/' . $customer->id : null,
            'handle' => $customer !== null ? '@' . preg_replace('/[^a-z0-9]/', '', strtolower($customer->displayName())) : '@cliente',
            'who' => $user !== null ? trim("{$user->firstName} {$user->lastName}") : null,
            'writer' => $writer !== null && $writer->id !== $user?->id ? trim("{$writer->firstName} {$writer->lastName}") : null,
            'internalApproval' => $post->internalApprovedAt !== null
                ? 'Approvato internamente' . ($approver !== null ? ' da ' . trim("{$approver->firstName} {$approver->lastName}") : '') . ' il ' . F::longDate(substr($post->internalApprovedAt, 0, 10))
                : null,
            'clientApproval' => $post->approvedAt !== null ? 'Approvato dal cliente il ' . F::longDate(substr($post->approvedAt, 0, 10)) . ($post->approvedVia ? " ({$post->approvedVia})" : '') : null,
            'revisions' => (int) $post->revisionCount,
            'reviewNote' => $post->reviewNote,
            'sourceLink' => $post->sourceLink,
            'outputLink' => $post->outputLink,
            'parent' => $parent !== null ? ['title' => (string) $parent->title, 'href' => '/piano-editoriale/' . $parent->id] : null,
            'children' => array_map(static fn (EditorialPost $c) => ['title' => (string) $c->title, 'href' => '/piano-editoriale/' . $c->id, 'format' => (string) $c->format], $children),
            'mediaNotes' => $post->mediaNotes,
            'feedback' => $post->clientFeedback,
            'approvedAt' => $post->approvedAt !== null ? F::longDate(substr($post->approvedAt, 0, 10)) : null,
            'publishedUrl' => $post->publishedUrl,
        ];
    }

    /**
     * Cosa aspetta la revisione interna, per cliente: la coda dei titolari.
     *
     * @return array<int, array{customerId:int, name:string, count:int, first:string, posts:list<array>, action:string}>
     */
    public function reviewQueue(): array
    {
        $names = $this->customerNames();
        $queue = [];
        foreach ($this->posts->findAll(['stage' => 'revisione interna'], 'publish_date, id') as $post) {
            $id = (int) $post->customerId;
            $queue[$id] ??= ['customerId' => $id, 'name' => $names[$id] ?? '', 'count' => 0, 'first' => F::shortDate($post->publishDate, $this->today), 'posts' => [], 'action' => '/piano-editoriale/approva-cliente?cliente=' . $id];
            $queue[$id]['count']++;
            $queue[$id]['posts'][] = ['title' => (string) $post->title, 'href' => '/piano-editoriale/' . $post->id, 'when' => F::shortDate($post->publishDate, $this->today), 'channel' => (string) $post->channel, 'format' => (string) $post->format];
        }

        return $queue;
    }

    /** @return array<int,int> cliente => giri di modifiche sui contenuti degli ultimi 30 giorni e dei prossimi */
    private function revisionsLastMonth(): array
    {
        $result = [];
        foreach ($this->posts->findBetween(date('Y-m-d', strtotime($this->today . ' -30 days')), date('Y-m-d', strtotime($this->today . ' +30 days'))) as $post) {
            $result[(int) $post->customerId] = ($result[(int) $post->customerId] ?? 0) + (int) $post->revisionCount;
        }

        return $result;
    }

    private function chip(EditorialPost $post, array $people): array
    {
        return [
            'id' => (int) $post->id,
            'href' => '/piano-editoriale/' . $post->id,
            'title' => (string) $post->title,
            'channel' => (string) $post->channel,
            'format' => (string) $post->format,
            'stage' => (string) $post->stage,
            'tone' => self::STAGE_TONES[$post->stage] ?? 'neutral',
            'time' => $post->publishTime,
            'who' => $post->assignedUserId !== null ? ($people[(int) $post->assignedUserId] ?? null) : null,
            'late' => $post->isOpen() && !in_array($post->stage, ['programmato'], true) && (string) $post->publishDate < $this->today,
        ];
    }

    /** @return array<int,string> */
    private function customerNames(): array
    {
        $names = [];
        foreach ($this->customers->findAll() as $customer) {
            $names[(int) $customer->id] = $customer->displayName();
        }

        return $names;
    }

    /** @return array<int,string> */
    private function userInitials(): array
    {
        $result = [];
        foreach ($this->users->findAll() as $user) {
            $result[(int) $user->id] = F::initials(trim("{$user->firstName} {$user->lastName}") ?: (string) $user->username);
        }

        return $result;
    }
}
