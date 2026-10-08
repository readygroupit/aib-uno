<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Core\Config;
use App\Core\Container;
use App\Model\Project;
use App\Package\MigrationRunner;
use App\Package\MigrationWriter;
use App\Package\PackageCatalogService;
use App\Package\PackageInstallerRunner;
use App\Package\PackageManifest;
use App\Repository\ProjectDatabaseRepository;
use App\Repository\ProjectRepository;
use App\Support\FirstAccessToken;

/**
 * Genera un progetto nuovo a partire da Uno: cartella con il codice del
 * framework e SOLO i pacchetti scelti (piu' le loro dipendenze), un
 * database con la loro struttura, l'utente admin e - se il preset li ha -
 * i dati demo. Cartella, database e indirizzo vengono dai pattern di
 * config 'provisioning' (diversi per sviluppo e produzione).
 *
 * In sviluppo (runInRequest) il progetto nasce dentro la richiesta e
 * risponde subito su <slug>.localhost (vhost jolly). In produzione queue()
 * lo mette in coda e lo crea il cron di root (bin/provision-queue.php ->
 * runQueued()), che poi fa anche vhost e certificato (SiteInstaller).
 *
 * Il progetto nasce "da configurare": il primo accesso passa dal link con
 * lo slug cifrato (firstAccessUrl(), vedi FirstAccessController).
 *
 * Le tabelle dei pacchetti passano dallo stesso percorso di un'installazione
 * normale (MigrationWriter -> MigrationRunner), non da un dump di Uno: cosi'
 * il progetto nasce con le sue migrazioni in db/migrations/applied e
 * PackageCatalogService sa cosa c'e' installato.
 *
 * Se un passaggio fallisce, cartella e database appena creati vengono
 * rimossi: si puo' riprovare con lo stesso nome.
 */
final class ProjectProvisioner
{
    private const RESERVED_SLUGS = ['uno', 'www', 'projects', 'localhost'];

    // Copiati a parte (solo quelli scelti) o rigenerati per il progetto.
    private const COPY_EXCLUDES = [
        '.git', 'nbproject', 'design', 'data/logs', 'data/cache', 'db/migrations',
        'packages', 'config/autoload/local.php', 'config/autoload/project.php', 'config/guide.php',
        'README.md', 'AGENTS.md', 'public/uploads',
    ];

    // Il generatore di progetti e l'installatore di pacchetti restano su Uno:
    // un progetto li riceve solo scegliendo esplicitamente 'provisioning'.
    // Gli asset JS/CSS restano (app.js li importa), senza server dietro sono inerti.
    private const CONFIGURATOR_FILES = [
        'src/App/Provisioning', 'src/App/Model/Project.php', 'src/App/Repository/ProjectRepository.php',
        'src/App/Repository/ProjectDatabaseRepository.php', 'src/App/View/Component/ProjectsBoardComponent.php',
        'src/App/Prompt/Tool/ShowProjectsTool.php', 'src/App/Prompt/Tool/InstallPackageTool.php',
        'src/App/Prompt/Tool/ListPackagesTool.php', 'module/Index/src/Controller/ProjectsController.php',
        'bin/create-project.php',
    ];

    private Config $config;
    private PackageCatalogService $catalog;
    private MigrationWriter $writer;
    private ProjectRepository $projects;

    public function __construct(Container $container)
    {
        $this->config = $container->get(Config::class);
        $this->catalog = $container->get(PackageCatalogService::class);
        $this->writer = $container->get(MigrationWriter::class);
        $this->projects = $container->get(ProjectRepository::class);
    }

    /** @return array<string, array<string, mixed>> chiave => preset */
    public function presets(): array
    {
        $presets = [];
        foreach (glob(ROOT_PATH . '/packages/provisioning/presets/*/preset.php') ?: [] as $file) {
            $key = basename(dirname($file));
            $presets[$key] = (require $file) + ['key' => $key, 'hasDemo' => is_file(dirname($file) . '/demo.sql')];
        }

        return $presets;
    }

    /**
     * Pacchetti sceglibili, con le dipendenze che si portano dietro.
     *
     * @return list<array{name: string, label: string, category: ?string, description: string, requires: string[]}>
     */
    public function selectablePackages(): array
    {
        $result = [];
        foreach ($this->catalog->list() as $package) {
            if ($package['name'] === 'provisioning') {
                continue;
            }
            $order = $this->resolve([$package['name']])['order'];
            $result[] = [
                'name' => $package['name'],
                'label' => $package['label'],
                'category' => $package['category'],
                'description' => $package['description'],
                'requires' => array_values(array_diff($order, [$package['name']])),
            ];
        }

        return $result;
    }

    public function slugFor(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name), '-'));

        return substr($slug, 0, 40);
    }

    /**
     * Produzione: controlla i dati e mette il progetto in coda
     * (provisioning_status 'queued'); lo crea il cron con runQueued().
     *
     * @param string[] $packages
     * @throws ProvisioningException
     */
    public function queue(string $name, string $slug, array $packages, ?string $presetKey, bool $withDemo): Project
    {
        $name = trim($name);
        $slug = strtolower(trim($slug));
        $preset = $presetKey !== null ? ($this->presets()[$presetKey] ?? null) : null;
        $this->validate($name, $slug, $packages, $presetKey, $preset);

        [$dir, , $dbName, $url] = $this->targets($slug, null, null);
        if (is_dir($dir)) {
            throw new ProvisioningException("La cartella {$dir} esiste gia': scegli un altro identificativo.");
        }
        if (ProjectDatabaseRepository::databaseExists($this->serverPdo(), $dbName)) {
            throw new ProvisioningException("Il database {$dbName} esiste gia': scegli un altro identificativo.");
        }

        $id = $this->projects->insert([
            'name' => $name,
            'slug' => $slug,
            'db_name' => $dbName,
            'path' => $dir,
            'url' => $url,
            'preset' => $presetKey,
            'packages' => json_encode(array_values(array_unique($packages))),
            'with_demo_data' => $withDemo ? 1 : 0,
            'provisioning_status' => 'queued',
            'provisioning_log' => null,
        ]);

        return $this->projects->find($id);
    }

    /**
     * Cron: crea un progetto messo in coda da queue(). Lo stato passa a
     * 'running', poi 'ready' o 'failed' (con il log dei passaggi).
     *
     * @return string[] log
     */
    public function runQueued(Project $project): array
    {
        $this->projects->update((int) $project->id, ['provisioning_status' => 'running']);

        try {
            $result = $this->provision(
                (string) $project->name,
                (string) $project->slug,
                json_decode((string) $project->packages, true) ?: [],
                $project->preset,
                (bool) $project->withDemoData,
                null,
                null,
                (int) $project->id
            );
            $log = $result['log'];
        } catch (ProvisioningException $e) {
            $log = [...$e->log, $e->getMessage()];
            $this->projects->update((int) $project->id, ['provisioning_status' => 'failed', 'provisioning_log' => implode("\n", $log)]);

            return $log;
        }

        return $log;
    }

    /**
     * @param string[] $packages pacchetti scelti (le dipendenze si aggiungono da sole)
     * @param string|null $targetDir cartella diversa da projectsDir/<slug> (es. un
     *     clone git gia' pronto): puo' esistere se contiene solo .git, e
     *     projectsDir/<slug> diventa un collegamento verso di lei
     * @param string|null $dbName database diverso da dbPattern: puo'
     *     esistere se e' vuoto
     * @param int|null $projectId riga gia' in coda (queue()): si aggiorna
     *     invece di inserirne una nuova
     * @return array{project: Project, log: string[]}
     * @throws ProvisioningException con il log dei passaggi fatti
     */
    public function provision(string $name, string $slug, array $packages, ?string $presetKey, bool $withDemo, ?string $targetDir = null, ?string $dbName = null, ?int $projectId = null): array
    {
        $name = trim($name);
        $slug = strtolower(trim($slug));
        $preset = $presetKey !== null ? ($this->presets()[$presetKey] ?? null) : null;
        $log = [];

        $this->validate($name, $slug, $packages, $presetKey, $preset, $projectId);

        [$dir, $link, $dbName, $url] = $this->targets($slug, $targetDir, $dbName);
        $resolved = $this->resolve($packages);

        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbName) !== 1) {
            throw new ProvisioningException('Nome del database non valido (solo lettere, cifre e underscore).', $log);
        }

        $server = $this->serverPdo();
        // Una cartella gia' esistente si accetta solo se e' un clone vuoto
        // (solo .git): mai sovrascrivere codice di qualcun altro.
        $adoptDir = is_dir($dir) && array_diff(scandir($dir) ?: [], ['.', '..', '.git']) === [];
        if (is_dir($dir) && !$adoptDir) {
            throw new ProvisioningException("La cartella {$dir} esiste gia' e non e' vuota: scegli un altro identificativo o svuotala.", $log);
        }
        if ($dir !== $link && (is_dir($link) || is_link($link))) {
            throw new ProvisioningException("Esiste gia' {$link}: l'indirizzo {$url} e' gia' in uso.", $log);
        }
        $adoptDb = ProjectDatabaseRepository::databaseExists($server, $dbName);
        if ($adoptDb && !(new ProjectDatabaseRepository($this->projectPdo($dbName)))->isEmpty($dbName)) {
            throw new ProvisioningException("Il database {$dbName} esiste gia' e contiene tabelle: scegli un altro nome o svuotalo.", $log);
        }

        $createdDir = false;
        $createdDb = false;

        try {
            $createdDir = true;
            $this->copyCode($dir, $resolved['order']);
            if ($dir !== $link) {
                symlink($dir, $link);
            }
            $log[] = "Codice copiato in {$dir} (framework + " . count($resolved['order']) . ' pacchetti).';

            if (!$adoptDb) {
                ProjectDatabaseRepository::createDatabase($server, $dbName);
            }
            $createdDb = true;
            $pdo = $this->projectPdo($dbName);
            $database = new ProjectDatabaseRepository($pdo);
            $database->setForeignKeyChecks(false);
            SqlScript::run($pdo, (string) file_get_contents(ROOT_PATH . '/db/schema.sql'));
            $log[] = "Database {$dbName} creato con le tabelle di base (utenti, permessi, configurazioni).";

            $this->writer->writeBatch($resolved['order'], $resolved['selections'], $dir);
            $applied = (new MigrationRunner())->apply($pdo, "{$dir}/db/migrations/pending", "{$dir}/db/migrations/applied");
            $log[] = 'Pacchetti installati: ' . implode(', ', $resolved['order']) . ' (' . count($applied) . ' migrazioni).';

            foreach ($resolved['order'] as $package) {
                $seed = ROOT_PATH . "/packages/{$package}/seed.sql";
                if (is_file($seed)) {
                    SqlScript::run($pdo, (string) file_get_contents($seed));
                    $log[] = "Dati di riferimento di '{$package}' caricati.";
                }
            }

            SqlScript::run($pdo, (string) file_get_contents(ROOT_PATH . '/db/seed.sql'));
            $database->removePermissionGroups(array_values(array_diff($this->allPackageNames(), $resolved['order'])));
            $log[] = 'Utente amministratore e permessi creati (solo per i pacchetti installati).';

            if ($withDemo && $preset !== null && $preset['hasDemo']) {
                $demo = (string) file_get_contents(ROOT_PATH . "/packages/provisioning/presets/{$presetKey}/demo.sql");
                SqlScript::run($pdo, $demo);
                preg_match_all('/INSERT\s+INTO `([a-z_]+)`/', $demo, $matches);
                $shift = time() - strtotime((string) ($preset['demoReferenceDate'] ?? 'now'));
                $database->shiftDates($dbName, array_values(array_unique($matches[1])), $shift);
                $log[] = 'Dati demo caricati (' . count($matches[1]) . ' righe), con le date riportate a oggi.';
            }

            $database->setForeignKeyChecks(true);
            $appName = $preset['appName'] ?? $name;
            $database->markSetupPending();
            $log[] = 'Progetto da configurare al primo accesso (link con lo slug cifrato).';
            $this->writeProjectConfig($dir, $slug, $dbName, $appName);
            $guide = $presetKey !== null && is_file(ROOT_PATH . "/packages/provisioning/presets/{$presetKey}/guide.php")
                ? ROOT_PATH . "/packages/provisioning/presets/{$presetKey}/guide.php"
                : ROOT_PATH . '/packages/provisioning/guide.default.php';
            copy($guide, "{$dir}/config/guide.php");
            $this->writeReadme($dir, $appName, $url, $dbName, $resolved['order'], $presetKey !== null ? ($preset['label'] ?? $presetKey) : null);
            $log[] = 'Configurazione, Guida e README scritti.';
        } catch (\Throwable $e) {
            $log[] = 'Errore: ' . $e->getMessage();
            $this->rollback($dir, $link, $createdDir, $adoptDir, $dbName, $createdDb, $server);
            $log[] = 'Annullato: cartella e database rimossi.';

            throw new ProvisioningException('La creazione del progetto non e\' andata a buon fine: ' . $e->getMessage(), $log, $e);
        }

        $log[] = "Pronto su {$url}";
        $row = [
            'name' => $name,
            'slug' => $slug,
            'db_name' => $dbName,
            'path' => $dir,
            'url' => $url,
            'preset' => $presetKey,
            'packages' => json_encode($resolved['order']),
            'with_demo_data' => $withDemo ? 1 : 0,
            'provisioning_status' => 'ready',
            'provisioning_log' => implode("\n", $log),
        ];
        if ($projectId !== null) {
            $this->projects->update($projectId, $row);
            $id = $projectId;
        } else {
            $id = $this->projects->insert($row);
        }

        return ['project' => $this->projects->find($id), 'log' => $log];
    }

    /**
     * Indirizzo del progetto calcolato dalla config (provisioning.urlPattern)
     * e non letto dalla colonna projects.url: lo stesso database funziona sul
     * PC di sviluppo (*.localhost) e in produzione (*.aibrains.it).
     */
    public function projectUrl(string $slug): string
    {
        return sprintf($this->config->get('provisioning')['urlPattern'], $slug);
    }

    /**
     * Link del primo accesso: lo slug cifrato abilita il wizard finche' il
     * progetto non e' configurato, poi non serve piu'.
     */
    public function firstAccessUrl(string $slug): string
    {
        return $this->projectUrl($slug) . '/primo-accesso?t=' . FirstAccessToken::forSlug($slug, (string) $this->config->get('provisioning')['tokenKey']);
    }

    /** Cartella del progetto in questo ambiente (la colonna projects.path e' storica). */
    public function projectDir(string $slug): string
    {
        return $this->targets($slug, null, null)[0];
    }

    /**
     * Cartella, collegamento (vhost jolly in sviluppo), database e
     * indirizzo del progetto, dai pattern di config 'provisioning'.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function targets(string $slug, ?string $targetDir, ?string $dbName): array
    {
        $settings = $this->config->get('provisioning');
        $dir = $targetDir !== null ? rtrim($targetDir, '/') : sprintf($settings['dirPattern'], $slug);
        $link = !empty($settings['linkDir']) ? rtrim($settings['linkDir'], '/') . '/' . $slug : $dir;

        return [$dir, $link, $dbName ?? sprintf($settings['dbPattern'], str_replace('-', '_', $slug)), $this->projectUrl($slug)];
    }

    private function validate(string $name, string $slug, array $packages, ?string $presetKey, ?array $preset, ?int $projectId = null): void
    {
        if ($name === '') {
            throw new ProvisioningException('Dai un nome al progetto.');
        }
        if (preg_match('/^[a-z][a-z0-9-]{1,39}$/', $slug) !== 1 || str_ends_with($slug, '-')) {
            throw new ProvisioningException('L\'identificativo puo\' contenere solo lettere minuscole, cifre e trattini, e deve iniziare con una lettera (diventa l\'indirizzo del progetto).');
        }
        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            throw new ProvisioningException("L'identificativo \u{ab}{$slug}\u{bb} e' riservato.");
        }
        $same = array_filter($this->projects->findAll(['slug' => $slug]), static fn ($p) => (int) $p->id !== $projectId);
        if ($same !== []) {
            throw new ProvisioningException("Esiste gia' un progetto \u{ab}{$slug}\u{bb}.");
        }
        if ($presetKey !== null && $preset === null) {
            throw new ProvisioningException('Preset sconosciuto.');
        }
        if ($packages === []) {
            throw new ProvisioningException('Scegli almeno un pacchetto.');
        }
        foreach ($packages as $package) {
            if ($package === 'provisioning' || !$this->catalog->exists($package)) {
                throw new ProvisioningException("Pacchetto sconosciuto: {$package}.");
            }
        }
    }

    /**
     * Tutti i campi di ogni pacchetto, i negoziabili come facoltativi (NULL):
     * il progetto nasce completo, senza campi in sospeso, e accetta i dati
     * demo (che hanno valori vuoti anche su campi 'defaultRequired'). Le dipendenze portate da un campo con
     * 'references' (es. customers -> geo) si aggiungono finche' l'elenco
     * non smette di crescere.
     *
     * @param string[] $packages
     * @return array{order: string[], selections: array<string, array>}
     */
    private function resolve(array $packages): array
    {
        $runner = new PackageInstallerRunner();
        $order = array_values(array_unique($packages));

        do {
            $selections = [];
            foreach ($order as $package) {
                $selections[$package] = $this->fullSelection($package);
            }
            $previous = $order;
            $order = $runner->resolveOrder($selections);
        } while (count($order) !== count($previous));

        foreach ($order as $package) {
            $selections[$package] ??= $this->fullSelection($package);
        }

        return ['order' => $order, 'selections' => $selections];
    }

    private function fullSelection(string $package): array
    {
        $manifest = new PackageManifest($package);
        $selection = [];
        foreach ($manifest->entities() as $entityKey => $entity) {
            foreach ($entity['fields'] as $fieldKey => $field) {
                if (!($field['base'] ?? false)) {
                    $selection[$entityKey][$fieldKey] = 'optional';
                }
            }
        }

        return $selection;
    }

    /** @return string[] */
    private function allPackageNames(): array
    {
        return array_column($this->catalog->list(), 'name');
    }

    /** @param string[] $packages */
    private function copyCode(string $dir, array $packages): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Impossibile creare la cartella {$dir}");
        }

        $skip = in_array('provisioning', $packages, true) ? self::COPY_EXCLUDES : [...self::COPY_EXCLUDES, ...self::CONFIGURATOR_FILES];
        $excludes = implode(' ', array_map(static fn ($e) => '--exclude=' . escapeshellarg('/' . $e), $skip));
        $this->exec('rsync -a ' . $excludes . ' ' . escapeshellarg(ROOT_PATH . '/') . ' ' . escapeshellarg($dir . '/'));

        mkdir("{$dir}/packages", 0775, true);
        foreach ($packages as $package) {
            $this->exec('rsync -a ' . escapeshellarg(ROOT_PATH . "/packages/{$package}") . ' ' . escapeshellarg("{$dir}/packages/"));
        }

        foreach (['data/logs', 'data/cache', 'db/migrations/pending', 'db/migrations/applied', 'public/uploads'] as $sub) {
            if (!is_dir("{$dir}/{$sub}")) {
                mkdir("{$dir}/{$sub}", 0775, true);
            }
            touch("{$dir}/{$sub}/.gitkeep");
        }
    }

    /**
     * config/autoload/project.php del progetto, versionato con il suo
     * codice: nome, slug e database per ambiente. Le credenziali restano
     * quelle di global.php (copiato da Uno), come nei progetti di Core.
     */
    private function writeProjectConfig(string $dir, string $slug, string $dbName, string $appName): void
    {
        $patterns = ['localhost' => 'dev_aib_%s', 'production' => 'prod_aib_%s'];
        $key = str_replace('-', '_', $slug);
        $devDb = $this->config->get('env') === 'localhost' ? $dbName : sprintf($patterns['localhost'], $key);
        $prodDb = $this->config->get('env') === 'localhost' ? sprintf($patterns['production'], $key) : $dbName;
        $export = static fn ($value) => var_export($value, true);

        file_put_contents("{$dir}/config/autoload/project.php", <<<PHP
<?php

declare(strict_types=1);

// Generato da Uno (ProjectProvisioner): nome, slug e database di questo
// progetto. Credenziali e resto della config in global.php.
\$isDev = getenv('APPLICATION_ENV') === 'localhost';

return [
    'app' => [
        'name' => {$export($appName)},
        'slug' => {$export($slug)},
    ],
    'db' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=' . (\$isDev ? {$export($devDb)} : {$export($prodDb)}) . ';charset=utf8mb4',
    ],
];

PHP);
    }

    private function rollback(string $dir, string $link, bool $createdDir, bool $adoptDir, string $dbName, bool $createdDb, \PDO $server): void
    {
        if ($dir !== $link && is_link($link)) {
            unlink($link);
        }
        if ($createdDir && is_dir($dir)) {
            // Clone adottato: si toglie quello che e' stato copiato, .git resta.
            $targets = $adoptDir
                ? array_map(static fn ($entry) => "{$dir}/{$entry}", array_diff(scandir($dir) ?: [], ['.', '..', '.git']))
                : [$dir];
            foreach ($targets as $target) {
                $this->exec('rm -rf ' . escapeshellarg($target));
            }
        }
        if ($createdDb) {
            // Anche un database adottato torna vuoto com'era.
            ProjectDatabaseRepository::dropDatabase($server, $dbName);
            ProjectDatabaseRepository::createDatabase($server, $dbName);
        }
    }

    /** @param string[] $packages */
    private function writeReadme(string $dir, string $appName, string $url, string $dbName, array $packages, ?string $presetLabel): void
    {
        $list = implode("\n", array_map(static fn ($p) => "- `{$p}`", $packages));
        $origin = $presetLabel !== null ? "dal preset \"{$presetLabel}\"" : 'senza preset';
        $date = date('Y-m-d');

        file_put_contents("{$dir}/README.md", <<<MD
# {$appName}

Gestionale generato da Uno il {$date} {$origin}.

- Indirizzo: {$url}
- Database: `{$dbName}` (nome per ambiente in `config/autoload/project.php`,
  credenziali in `config/autoload/global.php`)
- Primo accesso: dal link con lo slug cifrato che mostra Uno (`/primo-accesso?t=...`):
  si sceglie l'accesso dell'amministratore e si completano i dati del progetto

## Pacchetti installati

{$list}

Le tabelle sono nate dalle migrazioni in `db/migrations/applied`; nuove
modifiche allo schema si aggiungono in `db/migrations/pending` e si applicano
con `php8.4 bin/migrate.php`.

## Regole del codice

Stesso framework di Uno: niente Composer, SQL solo nei Repository, HTML solo
nelle viste o nei componenti JS, costruttori con il solo `Container`, codice
in inglese e testi per l'utente in italiano. I testi della Guida sono in
`config/guide.php`.

MD);
    }

    private function exec(string $command): void
    {
        exec($command . ' 2>&1', $output, $code);
        if ($code !== 0) {
            throw new \RuntimeException('Comando fallito: ' . implode(' ', $output));
        }
    }

    private function serverPdo(): \PDO
    {
        $db = $this->config->get('db');
        preg_match('/host=([^;]+)/', (string) $db['dsn'], $host);

        return new \PDO('mysql:host=' . ($host[1] ?? '127.0.0.1') . ';charset=utf8mb4', $db['username'], $db['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    private function projectPdo(string $dbName): \PDO
    {
        $db = $this->config->get('db');
        preg_match('/host=([^;]+)/', (string) $db['dsn'], $host);

        return new \PDO('mysql:host=' . ($host[1] ?? '127.0.0.1') . ";dbname={$dbName};charset=utf8mb4", $db['username'], $db['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }
}
