<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Task;

final class TaskRepository extends AbstractRepository
{
    protected string $table = 'tasks';
    protected string $modelClass = Task::class;

    /** Attivita' attive non completate con scadenza entro $before (formato 'Y-m-d H:i:s') - usato dal Report. */
    public function countDueBy(string $before): int
    {
        $row = $this->db->fetchRow(
            'SELECT COUNT(*) AS total FROM tasks WHERE status != 0 AND completed_at IS NULL AND due_at IS NOT NULL AND due_at <= :before',
            ['before' => $before]
        );

        return (int) ($row['total'] ?? 0);
    }
}
