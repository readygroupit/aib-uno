<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;
use App\Model\User;
use App\Repository\PasswordResetRepository;
use App\Repository\PermissionRepository;
use App\Repository\UserRepository;

/**
 * Login/logout, permessi effettivi in sessione, recupero password.
 * $_SESSION['user'] = ['id', 'profileId', 'username', 'permissions' => string[]].
 */
final class AuthService
{
    public function __construct(private readonly Container $container)
    {
    }

    public function currentUserId(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    public function login(string $identifier, string $password): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return null;
        }

        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get(UserRepository::class);

        foreach ($userRepository->findLoginCandidates($identifier) as $candidate) {
            if (!password_verify($password, (string) $candidate->passwordHash)) {
                continue;
            }

            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id' => $candidate->id,
                'profileId' => $candidate->profileId,
                'username' => $candidate->username,
                'permissions' => $this->computeEffectivePermissionCodes((int) $candidate->id, (int) $candidate->profileId),
            ];

            $userRepository->update((int) $candidate->id, ['last_login_at' => date('Y-m-d H:i:s')]);

            return $candidate;
        }

        return null;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public function hasPermission(string $code): bool
    {
        return in_array($code, $_SESSION['user']['permissions'] ?? [], true);
    }

    /**
     * Non rivela se l'identificativo esiste: chiamare sempre allo stesso
     * modo (es. "se l'account esiste, riceverai un'email") a prescindere
     * dall'esito.
     */
    public function requestPasswordReset(string $identifier): void
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return;
        }

        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get(UserRepository::class);
        /** @var PasswordResetRepository $resetRepository */
        $resetRepository = $this->container->get(PasswordResetRepository::class);
        $mail = $this->container->get(MailService::class);

        foreach ($userRepository->findLoginCandidates($identifier) as $candidate) {
            $token = bin2hex(random_bytes(32));

            $resetRepository->insert([
                'user_id' => $candidate->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            ]);

            $mail->send(
                (string) $candidate->email,
                'Recupero password',
                "Ciao {$candidate->username},\n\n"
                    . "Per reimpostare la password vai su: /password/reset/$token\n\n"
                    . "Il link scade tra un'ora. Se non sei stato tu a richiederlo, ignora questa email."
            );
        }
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        if ($token === '' || strlen($newPassword) < 8) {
            return false;
        }

        /** @var PasswordResetRepository $resetRepository */
        $resetRepository = $this->container->get(PasswordResetRepository::class);
        $reset = $resetRepository->findValidByTokenHash(hash('sha256', $token));

        if ($reset === null) {
            return false;
        }

        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get(UserRepository::class);
        $userRepository->update((int) $reset->userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);

        $resetRepository->update((int) $reset->id, ['used_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    /**
     * @return string[]
     */
    private function computeEffectivePermissionCodes(int $userId, int $profileId): array
    {
        /** @var PermissionRepository $permissionRepository */
        $permissionRepository = $this->container->get(PermissionRepository::class);

        return $permissionRepository->findEffectiveCodesForUser($userId, $profileId);
    }
}
