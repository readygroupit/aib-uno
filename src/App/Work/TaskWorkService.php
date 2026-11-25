<?php

declare(strict_types=1);

namespace App\Work;

use App\Core\Container;
use App\Model\Task;
use App\Repository\TaskRepository;
use App\Support\ItalianFormat as F;

/**
 * Quello che si segna lavorando a una fase, dalla sua scheda "Da fare":
 * l'esito e le note (es. cosa e' emerso alla presentazione interna), una
 * nota per ogni voce della checklist (es. i font scelti per le bozze) e
 * l'approvazione del cliente (data ed email). Resta nel gestionale, dove
 * la squadra e l'assistente lo ritrovano (projectContext()).
 */
final class TaskWorkService
{
    public function __construct(private readonly Container $container)
    {
    }

    public function saveOutcome(int $taskId, string $text): bool
    {
        if (!$this->tasks()->find($taskId) instanceof Task) {
            return false;
        }
        $this->tasks()->update($taskId, ['outcome' => trim($text) !== '' ? trim($text) : null]);

        return true;
    }

    /** La nota di una voce della checklist (indice della riga). */
    public function saveItemNote(int $taskId, int $index, string $text): bool
    {
        $task = $this->tasks()->find($taskId);
        if (!$task instanceof Task || $index < 0) {
            return false;
        }
        $notes = self::itemNotes($task);
        if (trim($text) === '') {
            unset($notes[$index]);
        } else {
            $notes[$index] = trim($text);
        }
        $this->tasks()->update($taskId, ['item_notes' => $notes !== [] ? json_encode($notes, JSON_UNESCAPED_UNICODE) : null]);

        return true;
    }

    /** @return array<int,string> indice della voce => nota */
    public static function itemNotes(Task $task): array
    {
        $decoded = json_decode((string) $task->itemNotes, true);
        if (!is_array($decoded)) {
            return [];
        }
        $notes = [];
        foreach ($decoded as $index => $text) {
            if (is_numeric($index) && is_string($text) && trim($text) !== '') {
                $notes[(int) $index] = $text;
            }
        }

        return $notes;
    }

    /**
     * Il cliente ha approvato: la data e l'email (o come ha approvato) si
     * segnano nella fase, che si chiude.
     *
     * @return string|null l'errore, null se e' andata
     */
    public function clientApproved(int $taskId, string $date, string $how): ?string
    {
        $task = $this->tasks()->find($taskId);
        if (!$task instanceof Task) {
            return "Attivita' non trovata.";
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return "Scrivi la data dell'approvazione.";
        }
        if ($date > date('Y-m-d')) {
            return "La data dell'approvazione non puo' essere nel futuro.";
        }
        if (trim($how) === '') {
            return "Incolla l'email del cliente (o scrivi come ha approvato).";
        }
        $this->tasks()->update($taskId, ['outcome_date' => $date, 'outcome' => trim($how)]);
        if ($task->isOpen()) {
            $this->container->get(TaskService::class)->complete($taskId);
        }

        return null;
    }

    /**
     * Per l'assistente: cosa si e' segnato nelle fasi del progetto (esiti,
     * note sulle voci) e i file caricati. Righe brevi, le piu' utili prima.
     */
    public function projectContext(int $workOrderId): string
    {
        $lines = [];
        foreach ($this->tasks()->findForEntity('work_orders', $workOrderId) as $task) {
            $parts = [];
            if (trim((string) $task->outcome) !== '') {
                $parts[] = ($task->outcomeDate !== null ? 'il ' . F::shortDate((string) $task->outcomeDate, date('Y-m-d')) . ': ' : '') . trim((string) $task->outcome);
            }
            $items = array_values(array_filter(array_map('trim', explode("\n", (string) $task->checklist))));
            foreach (self::itemNotes($task) as $index => $note) {
                $label = trim((string) preg_replace('/^\[x\]\s*/i', '', $items[$index] ?? ''));
                $parts[] = ($label !== '' ? "{$label}: " : '') . $note;
            }
            if ($parts !== []) {
                $lines[] = "- {$task->title}: " . implode('; ', $parts);
            }
        }
        if (in_array('documents', \App\Package\PackageCatalogService::installedNames(), true)) {
            $files = $this->container->get(\App\Repository\DocumentRepository::class)->findForWorkOrder($workOrderId, 20);
            if ($files !== []) {
                $lines[] = '- File: ' . implode(', ', array_map(static fn ($d) => $d->name . ($d->description ? " ({$d->description})" : ''), $files));
            }
        }

        return $lines !== [] ? "Cosa si e' segnato nel progetto:\n" . implode("\n", $lines) : '';
    }

    private function tasks(): TaskRepository
    {
        return $this->container->get(TaskRepository::class);
    }
}
