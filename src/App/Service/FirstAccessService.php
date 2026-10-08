<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Container;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Support\FirstAccessToken;

/**
 * Primo accesso a un progetto generato da Uno. Finche' 'setup.status' non
 * e' 'done' il progetto non si usa: si entra solo dal link con lo slug
 * cifrato (FirstAccessToken), si sceglie l'accesso dell'amministratore
 * (passo 'account', poi si e' collegati) e si completano i dati del
 * progetto (passo 'project'). Un passo lasciato a meta' viene riproposto
 * con i dati gia' inseriti.
 *
 * Stato nella tabella settings del progetto: 'setup.status'
 * (pending|done), 'setup.account' (done), 'project.*' (dati del wizard).
 * Senza tabella o senza 'setup.status' (Uno, progetti nati prima) il
 * progetto vale come configurato.
 */
final class FirstAccessService
{
    public const ADMIN_USER_ID = 1;

    public const PROJECT_FIELDS = [
        'name' => ['label' => 'Nome del progetto', 'required' => true],
        'company' => ['label' => 'Ragione sociale', 'required' => true],
        'vat' => ['label' => 'Partita IVA', 'required' => false],
        'email' => ['label' => 'Email di contatto', 'required' => true, 'type' => 'email'],
        'phone' => ['label' => 'Telefono', 'required' => false],
        'address' => ['label' => 'Indirizzo', 'required' => false],
    ];

    private const LOGO_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    private const LOGO_MAX_BYTES = 2 * 1024 * 1024;

    private ?bool $pending = null;

    public function __construct(private readonly Container $container)
    {
    }

    public function isPending(): bool
    {
        if ($this->pending !== null) {
            return $this->pending;
        }
        if ($_SESSION['setupDone'] ?? false) {
            return $this->pending = false;
        }

        $settings = $this->settings();
        $this->pending = $settings->tableExists() && $settings->value('setup.status') === 'pending';
        if (!$this->pending) {
            $_SESSION['setupDone'] = true;
        }

        return $this->pending;
    }

    public function accountDone(): bool
    {
        return $this->isPending() && $this->settings()->value('setup.account') === 'done';
    }

    public function tokenMatches(string $token): bool
    {
        $slug = (string) $this->container->get(Config::class)->get('app.slug');
        $key = (string) ($this->container->get(Config::class)->get('provisioning')['tokenKey'] ?? '');

        return $slug !== '' && $key !== '' && $token !== '' && FirstAccessToken::slugFrom($token, $key) === $slug;
    }

    /** @return array{firstName: string, lastName: string, email: string} */
    public function accountValues(): array
    {
        $user = $this->container->get(UserRepository::class)->find(self::ADMIN_USER_ID);
        $done = $this->accountDone();

        return [
            'firstName' => $done ? (string) $user?->firstName : '',
            'lastName' => $done ? (string) $user?->lastName : '',
            'email' => $done ? (string) $user?->email : '',
        ];
    }

    /**
     * @return string|null errore da mostrare, null se salvato (e collegato)
     */
    public function saveAccount(string $firstName, string $lastName, string $email, string $password, string $confirm): ?string
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $email = trim($email);
        $keepPassword = $password === '' && $this->accountDone();

        if ($firstName === '' || $lastName === '') {
            return 'Inserisci nome e cognome.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Inserisci un indirizzo email valido.';
        }
        if (!$keepPassword && strlen($password) < 8) {
            return 'La password deve avere almeno 8 caratteri.';
        }
        if (!$keepPassword && $password !== $confirm) {
            return 'Le due password non coincidono.';
        }

        $users = $this->container->get(UserRepository::class);
        $data = ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'username' => $email];
        if (!$keepPassword) {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $users->update(self::ADMIN_USER_ID, $data);
        $this->settings()->set('setup.account', 'done');
        $this->container->get(AuthService::class)->loginUser($users->find(self::ADMIN_USER_ID));

        return null;
    }

    /** @return array<string, string> campo => valore (piu' 'logo': url o '') */
    public function projectValues(): array
    {
        $saved = $this->settings()->valuesByPrefix('project.');
        $values = [];
        foreach (array_keys(self::PROJECT_FIELDS) as $field) {
            $values[$field] = (string) ($saved["project.{$field}"] ?? '');
        }
        $values['name'] = $values['name'] !== '' ? $values['name'] : APP_NAME;
        $values['logo'] = (string) ($saved['project.logo'] ?? '');

        return $values;
    }

    /**
     * Salva sempre quello che e' stato inserito (anche a meta'), poi
     * chiude il primo accesso se e' tutto completo.
     *
     * @param array<string, string> $input
     * @param array|null $logo file caricato (Request::file())
     * @return string|null errore da mostrare, null se completato
     */
    public function saveProject(array $input, ?array $logo): ?string
    {
        $settings = $this->settings();
        foreach (array_keys(self::PROJECT_FIELDS) as $field) {
            $settings->set("project.{$field}", trim((string) ($input[$field] ?? '')));
        }

        if ($logo !== null) {
            $error = $this->storeLogo($logo);
            if ($error !== null) {
                return $error;
            }
        }

        $values = $this->projectValues();
        foreach (self::PROJECT_FIELDS as $field => $spec) {
            if ($spec['required'] && $values[$field] === '') {
                return "Manca: {$spec['label']}.";
            }
        }
        if (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            return 'L\'email di contatto non e\' valida.';
        }
        if ($values['logo'] === '') {
            return 'Carica il logo del progetto.';
        }

        $settings->set('setup.status', 'done');
        $_SESSION['setupDone'] = true;
        $this->pending = false;

        return null;
    }

    private function storeLogo(array $file): ?string
    {
        if ((int) $file['size'] > self::LOGO_MAX_BYTES) {
            return 'Il logo deve pesare al massimo 2 MB.';
        }
        $type = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extension = self::LOGO_TYPES[$type] ?? null;
        if ($extension === null) {
            return 'Il logo deve essere un\'immagine PNG, JPG o WebP.';
        }

        $dir = ROOT_PATH . '/public/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return 'Impossibile salvare il logo (cartella uploads non scrivibile).';
        }
        foreach (glob("{$dir}/logo.*") ?: [] as $old) {
            unlink($old);
        }
        if (!move_uploaded_file((string) $file['tmp_name'], "{$dir}/logo.{$extension}")) {
            return 'Impossibile salvare il logo.';
        }
        $this->settings()->set('project.logo', "/uploads/logo.{$extension}?v=" . time());

        return null;
    }

    private function settings(): SettingRepository
    {
        return $this->container->get(SettingRepository::class);
    }
}
