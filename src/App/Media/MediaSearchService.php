<?php

declare(strict_types=1);

namespace App\Media;

use App\Core\Container;
use App\Model\MediaAsset;
use App\Repository\CustomerRepository;
use App\Repository\MediaAssetRepository;
use App\Service\AiService;

/**
 * La ricerca a parole nell'archivio ("foto per una comunione senza
 * persone"). Non si riguardano i file: si leggono le descrizioni salvate.
 *
 * 1. La richiesta diventa filtri (foto/video, senza persone, cliente,
 *    interno/esterno, verticale...) e parole, con i sinonimi: lo fa l'IA,
 *    o senza IA una lettura semplice delle parole.
 * 2. Il database trova i candidati (descrizione, parole chiave, cartella).
 * 3. Con l'IA, si tengono solo quelli che vanno davvero bene, nell'ordine
 *    giusto e con il perche'.
 */
final class MediaSearchService
{
    private const STOPWORDS = ['cercami', 'cerca', 'trova', 'trovami', 'dammi', 'mostrami', 'voglio', 'delle', 'degli', 'della', 'dello', 'dei', 'del', 'per', 'una', 'uno', 'con', 'che', 'sono', 'vanno', 'bene', 'adatte', 'adatti', 'adatto', 'adatta', 'generica', 'generico', 'generiche', 'generici', 'qualche', 'alcune', 'alcuni', 'foto', 'immagini', 'immagine', 'video', 'filmati', 'senza', 'persone', 'riferimenti', 'nessuna', 'archivio', 'materiale', 'materiali'];

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param array $forced filtri scelti a mano nella schermata (tipo, cliente, senza persone)
     * @return array{items: list<array{asset: MediaAsset, reason: ?string}>, filters: array, words: string[], understood: string}
     */
    public function search(string $query, array $forced = []): array
    {
        $query = trim($query);
        $ai = $this->container->get(AiService::class);
        $plan = $query !== '' && $ai->isConfigured() ? $this->interpretWithAi($query) : null;
        $plan ??= $this->interpretSimply($query);
        $filters = array_filter($forced + $plan['filters'], static fn ($v) => $v !== null && $v !== false && $v !== '');

        $repo = $this->container->get(MediaAssetRepository::class);
        $candidates = $repo->search($filters, $plan['words'], 40);
        // Nessun candidato: si allentano i filtri dedotti (prima si tiene foto/video e persone, poi solo le parole).
        if ($candidates === [] && $plan['words'] !== []) {
            $candidates = $repo->search($forced + array_intersect_key($filters, ['kind' => 1, 'noPeople' => 1]), $plan['words'], 40);
        }
        if ($candidates === [] && $plan['words'] !== []) {
            $candidates = $repo->search($forced, $plan['words'], 40);
        }

        $items = array_map(static fn (MediaAsset $a) => ['asset' => $a, 'reason' => null], $candidates);
        if ($query !== '' && $candidates !== [] && $ai->isConfigured()) {
            try {
                $items = $this->rerank($query, $candidates);
            } catch (\Throwable) {
                // Senza il secondo giro dell'IA restano i risultati del database.
            }
        }

        return ['items' => array_slice($items, 0, 24), 'filters' => $filters, 'words' => $plan['words'], 'understood' => $plan['understood']];
    }

    /** @return ?array{filters: array, words: string[], understood: string} */
    private function interpretWithAi(string $query): ?array
    {
        $customers = $this->container->get(CustomerRepository::class)->findAll();
        $system = 'Trasforma una richiesta di foto o video in una ricerca nell\'archivio di un\'agenzia. '
            . 'Rispondi solo con JSON: {"parole": ["parole e sinonimi da cercare nelle descrizioni, al singolare, max 8"], '
            . '"tipo": "immagine|video|null", "senza_persone": true|false, "con_persone": true|false, "ambiente": "interno|esterno|null", '
            . '"orientamento": "verticale|orizzontale|quadrato|null", "cliente": "nome esatto dalla lista o null", "capito": "la richiesta riassunta in poche parole"}. '
            . 'Metti un filtro SOLO se la richiesta lo dice esplicitamente (altrimenti null o false): niente orientamento, ambiente o cliente dedotti. '
            . '"Senza riferimenti di persone" o "senza persone" vuol dire senza persone visibili.';
        $names = implode('; ', array_slice(array_map(static fn ($c) => $c->displayName(), $customers), 0, 200));
        try {
            $result = $this->container->get(AiService::class)->json($system, "Richiesta: {$query}\nClienti: {$names}", 600);
        } catch (\Throwable) {
            return null;
        }
        $customerId = null;
        foreach ($customers as $customer) {
            if (!empty($result['cliente']) && mb_strtolower($customer->displayName()) === mb_strtolower((string) $result['cliente'])) {
                $customerId = (int) $customer->id;
            }
        }

        return [
            'filters' => [
                'kind' => in_array($result['tipo'] ?? null, ['immagine', 'video'], true) ? $result['tipo'] : null,
                'noPeople' => ($result['senza_persone'] ?? false) === true,
                'withPeople' => ($result['con_persone'] ?? false) === true,
                'setting' => in_array($result['ambiente'] ?? null, ['interno', 'esterno'], true) ? $result['ambiente'] : null,
                'orientation' => in_array($result['orientamento'] ?? null, ['verticale', 'orizzontale', 'quadrato'], true) ? $result['orientamento'] : null,
                'customerId' => $customerId,
            ],
            'words' => array_values(array_filter(array_map(static fn ($w) => mb_strtolower(trim((string) $w)), (array) ($result['parole'] ?? [])), static fn ($w) => mb_strlen($w) >= 3)),
            'understood' => (string) ($result['capito'] ?? $query),
        ];
    }

    /** Senza IA: le parole della richiesta, e i filtri piu' ovvi. */
    private function interpretSimply(string $query): array
    {
        $lower = mb_strtolower($query);
        $words = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $lower) ?: [],
            static fn ($w) => mb_strlen($w) >= 3 && !in_array($w, self::STOPWORDS, true)
        ));

        return [
            'filters' => [
                'kind' => str_contains($lower, 'video') ? 'video' : (preg_match('/\b(foto|immagin)/u', $lower) === 1 ? 'immagine' : null),
                'noPeople' => preg_match('/senza\s+(persone|gente|riferimenti)/u', $lower) === 1,
            ],
            'words' => $words,
            'understood' => $query,
        ];
    }

    /**
     * L'IA legge le descrizioni dei candidati e tiene quelli adatti.
     *
     * @param MediaAsset[] $candidates
     * @return list<array{asset: MediaAsset, reason: ?string}>
     */
    private function rerank(string $query, array $candidates): array
    {
        $byId = [];
        $lines = [];
        foreach ($candidates as $asset) {
            $byId[(int) $asset->id] = $asset;
            $lines[] = "#{$asset->id} [{$asset->kind}" . ($asset->peopleCount !== null ? ", persone: {$asset->peopleCount}" : '') . '] ' . mb_substr((string) $asset->description, 0, 400) . ' | ' . $asset->tags;
        }
        $system = 'Scegli dall\'elenco i file adatti alla richiesta, dal piu\' adatto, scartando quelli che non vanno. '
            . 'Rispondi solo con JSON: {"risultati": [{"id": 12, "perche": "pochissime parole"}]}.';
        $result = $this->container->get(AiService::class)->json($system, "Richiesta: {$query}\n\nFile:\n" . implode("\n", $lines), 1500);

        $items = [];
        foreach ((array) ($result['risultati'] ?? []) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if (isset($byId[$id])) {
                $items[] = ['asset' => $byId[$id], 'reason' => isset($row['perche']) ? (string) $row['perche'] : null];
                unset($byId[$id]);
            }
        }

        return $items;
    }
}
