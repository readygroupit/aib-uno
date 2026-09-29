<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;
use App\Model\User;
use App\Repository\PasswordResetRepository;
use App\Repository\PermissionRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;

/**
 * Login/logout, permessi effettivi in sessione, recupero password.
 * $_SESSION['user'] = ['id', 'profileId', 'username', 'firstName',
 * 'lastName', 'profileName', 'permissions' => string[]]. Nome/ruolo sono
 * cache-ati qui allo stesso modo dei permessi (vedi hasPermission()):
 * letti una volta al login, non si aggiornano finche' non si rientra se
 * un admin rinomina l'utente o gli cambia profilo nel frattempo - stesso
 * compromesso gia' accettato, non una svista nuova.
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

            /** @var ProfileRepository $profileRepository */
            $profileRepository = $this->container->get(ProfileRepository::class);
            $profile = $candidate->profileId !== null ? $profileRepository->find($candidate->profileId) : null;

            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id' => $candidate->id,
                'profileId' => $candidate->profileId,
                'username' => $candidate->username,
                'firstName' => $candidate->firstName,
                'lastName' => $candidate->lastName,
                'profileName' => $profile->name ?? '',
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

    /**
     * Cambio password "sono loggato, conosco quella attuale" (dalla
     * pagina Il mio profilo) - diverso da resetPassword() sopra, che e'
     * il flusso "l'ho dimenticata" via email/token. Richiede comunque la
     * password attuale anche se l'utente e' gia' autenticato: una
     * sessione rubata non deve bastare a cambiare la password.
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        if (strlen($newPassword) < 8) {
            return false;
        }

        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get(UserRepository::class);
        $user = $userRepository->find($userId);

        if ($user === null || !password_verify($currentPassword, (string) $user->passwordHash)) {
            return false;
        }

        $userRepository->update($userId, ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)]);

        return true;
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
