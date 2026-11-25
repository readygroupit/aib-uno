<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Core\Container;
use App\Model\Customer;
use App\Model\WorkOrder;
use App\Package\PackageCatalogService;
use App\Repository\CustomerTouchpointRepository;
use App\Repository\EditorialStrategyRepository;
use App\Service\AiService;

/**
 * I testi dell'assistente: il "come" e il "perche'". Con l'IA configurata
 * li scrive su misura (sapendo del cliente quello che c'e' nel
 * gestionale: settore, note, strategia, ultimi contatti); senza, un testo
 * pronto che va bene lo stesso. Mai un errore per l'IA che non risponde:
 * si torna al testo pronto.
 */
final class AssistantWriter
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Sollecito al cliente: preventivo senza risposta o materiale/approvazione che non arriva.
     *
     * @return array{subject:string, body:string, ai:bool}
     */
    public function reminder(string $kind, ?WorkOrder $order, ?Customer $customer, string $what, string $signature): array
    {
        $name = $customer?->displayName() ?? '';
        $project = $order?->title ?? '';
        $fallback = match ($kind) {
            'quote' => [
                'subject' => "Preventivo per {$project}",
                'body' => "Buongiorno,\n\nvi scrivo per sapere se avete avuto modo di guardare il preventivo che vi abbiamo mandato per {$project}. "
                    . "Se c'e' qualcosa da chiarire o da rivedere, siamo a disposizione anche per una telefonata.\n\nUn saluto,\n{$signature}",
            ],
            'followup' => [
                'subject' => 'Un saluto da ' . (defined('APP_NAME') ? APP_NAME : 'noi'),
                // $what e' il promemoria interno ("proporre il sito"): non va scritto al cliente cosi' com'e'.
                'body' => "Buongiorno,\n\nvi scrivo per un aggiornamento: come state andando? "
                    . "Se avete qualche idea in testa o vi serve una mano, ci sentiamo volentieri anche solo per una chiacchierata.\n\nUn saluto,\n{$signature}",
            ],
            'seasonal' => [
                'subject' => 'Prepariamo insieme ' . trim(explode(':', $what)[0]) . '?',
                'body' => "Buongiorno,\n\nsi avvicina " . mb_strtolower(trim(explode(':', $what)[0])) . " e ci piacerebbe aiutarvi a viverlo al meglio: "
                    . "abbiamo qualche idea da proporvi. Vi va di sentirci questa settimana per prepararlo insieme?\n\nUn saluto,\n{$signature}",
            ],
            default => [
                'subject' => "{$project}: ci serve un vostro riscontro",
                'body' => "Buongiorno,\n\nper andare avanti con {$project} ci serve un vostro riscontro su: {$what}. "
                    . "Appena ci rispondete riprendiamo subito il lavoro.\n\nGrazie, un saluto,\n{$signature}",
            ],
        };
        $purpose = match ($kind) {
            'quote' => "Scopo: risentire il cliente sul preventivo inviato e non ancora accettato per il progetto \"{$project}\".",
            'followup' => 'Scopo: farsi risentire con un cliente che non sentiamo da un po\'' . ($what !== '' ? " (promemoria interno di chi lo segue, da non citare alla lettera: {$what})" : '') . '. Niente vendita aggressiva: interesse vero, magari uno spunto utile per la sua attivita\'.',
            'seasonal' => "Scopo: proporre al cliente di preparare insieme un periodo importante per la sua attivita' ({$what}), con un accenno concreto a cosa si potrebbe fare per lui, e chiedere una telefonata.",
            default => "Scopo: sollecitare con garbo il cliente, il progetto \"{$project}\" aspetta da lui: {$what}.",
        };

        $ai = $this->ai();
        if ($ai === null) {
            return $fallback + ['ai' => false];
        }
        try {
            $answer = $ai->json(
                "Scrivi un'email breve e cordiale in italiano, da un'agenzia di comunicazione a un suo cliente. "
                . 'Tono professionale ma caldo, niente frasi fatte, massimo 90 parole nel testo. '
                . 'Rispondi con un oggetto JSON {"subject": "...", "body": "..."}; il testo finisce con "Un saluto," e la firma data.',
                implode("\n", array_filter([
                    $purpose,
                    "Cliente: {$name}",
                    $this->customerContext($customer),
                    "Firma: {$signature}",
                ])),
                800
            );
            if (trim((string) ($answer['subject'] ?? '')) !== '' && trim((string) ($answer['body'] ?? '')) !== '') {
                return ['subject' => trim((string) $answer['subject']), 'body' => trim((string) $answer['body']), 'ai' => true];
            }
        } catch (\Throwable) {
        }

        return $fallback + ['ai' => false];
    }

    /** L'idea per il portfolio di un progetto consegnato, nello stile del cliente se lo conosciamo. */
    public function portfolioIdea(WorkOrder $order, ?Customer $customer): string
    {
        $tone = null;
        if ($customer !== null && in_array('editorial', PackageCatalogService::installedNames(), true)) {
            $tone = $this->container->get(EditorialStrategyRepository::class)->findByCustomer((int) $customer->id)?->tone;
        }
        $fallback = "Prepara un'immagine per il portfolio con il lavoro approvato di {$order->title}: il risultato finale in primo piano, "
            . 'una o due applicazioni reali (es. biglietto, insegna, post) e due righe su cosa abbiamo fatto'
            . ($tone !== null && trim($tone) !== '' ? ", con il tono del cliente: {$tone}." : ', nello stile del cliente.');

        $ai = $this->ai();
        if ($ai === null) {
            return $fallback;
        }
        try {
            $answer = $ai->json(
                "Sei l'assistente di un'agenzia di comunicazione. Proponi in 2-3 frasi, in italiano, come presentare nel portfolio dell'agenzia "
                . 'un progetto appena consegnato: cosa mostrare e con che stile, coerente con il cliente. Rispondi con {"idea": "..."}.',
                // Anche quello che si e' segnato nelle fasi (esiti, font, colori, file): l'IA sa com'e' andata.
                implode("\n", array_filter(["Progetto: {$order->title}", 'Cliente: ' . ($customer?->displayName() ?? ''), $this->customerContext($customer), $this->container->get(\App\Work\TaskWorkService::class)->projectContext((int) $order->id)])),
                500
            );
            if (trim((string) ($answer['idea'] ?? '')) !== '') {
                return trim((string) $answer['idea']);
            }
        } catch (\Throwable) {
        }

        return $fallback;
    }

    /** Quello che sappiamo gia' del cliente, in poche righe (per l'IA e per preparare un briefing). */
    public function customerContext(?Customer $customer): string
    {
        if ($customer === null) {
            return '';
        }
        $lines = [];
        if (trim((string) $customer->sector) !== '') {
            $lines[] = "Settore: {$customer->sector}";
        }
        if (trim((string) $customer->notes) !== '') {
            $lines[] = 'Note: ' . mb_substr(trim((string) $customer->notes), 0, 400);
        }
        $installed = PackageCatalogService::installedNames();
        // Il profilo (dal briefing o scritto a mano) prima di tutto: e' quello che il cliente ci ha detto di se'.
        if (\App\Knowledge\KnowledgeService::isInstalled()) {
            foreach ($this->container->get(\App\Knowledge\KnowledgeService::class)->forCustomer((int) $customer->id)['profile'] as $entry) {
                $lines[] = $entry['label'] . ': ' . mb_substr(str_replace("\n", ' ', $entry['text']), 0, 300);
            }
        }
        if (in_array('editorial', $installed, true)) {
            $strategy = $this->container->get(EditorialStrategyRepository::class)->findByCustomer((int) $customer->id);
            if ($strategy !== null) {
                foreach (['objectives' => 'Obiettivi', 'audience' => 'Pubblico', 'tone' => 'Tono'] as $field => $label) {
                    if (trim((string) $strategy->{$field}) !== '') {
                        $lines[] = "{$label}: " . mb_substr(trim((string) $strategy->{$field}), 0, 200);
                    }
                }
            }
        }
        if (in_array('client_health', $installed, true)) {
            foreach ($this->container->get(CustomerTouchpointRepository::class)->findByCustomer((int) $customer->id, 2) as $touch) {
                if (trim((string) $touch->summary) !== '') {
                    $lines[] = 'Contatto del ' . date('d/m/Y', strtotime((string) $touch->contactDate)) . ': ' . mb_substr(trim((string) $touch->summary), 0, 200);
                }
            }
        }

        return implode("\n", $lines);
    }

    private function ai(): ?AiService
    {
        $ai = $this->container->get(AiService::class);

        return $ai->isConfigured() ? $ai : null;
    }
}
