<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\PasswordReset;

final class PasswordResetRepository extends AbstractRepository
{
    protected string $table = 'password_resets';
    protected string $modelClass = PasswordReset::class;

    public function findValidByTokenHash(string $tokenHash): ?PasswordReset
    {
        $sql = 'SELECT * FROM password_resets
                WHERE token_hash = :tokenHash AND used_at IS NULL AND expires_at >= :now AND status != 0';

        $row = $this->db->fetchRow($sql, ['tokenHash' => $tokenHash, 'now' => date('Y-m-d H:i:s')]);

        return $row ? PasswordReset::fromArray($row) : null;
    }
}
