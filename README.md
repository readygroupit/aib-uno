# Uno

Uno e' due cose insieme:

1. **Un framework** MVC minimale in PHP puro, zero dipendenze esterne (niente
   Composer, niente Laminas). Diventera' anche il framework su cui girano i
   progetti generati (stesso HTML/CSS/motore JS, stesse convenzioni).
2. **Un configuratore**: l'idea finale e' che descrivendo a voce o per testo
   il progetto che serve ("un gestionale per un'azienda che vende X, con
   clienti, pratiche, documenti..."), Uno assembli un progetto vero scegliendo
   da un catalogo di **pacchetti** installabili (schema DB + eventuale
   codice), e lo metta in piedi (cartella, database, vhost) da solo.

Il nome/mascotte viene dal fumetto Disney *PK New Adventures* (Paperino
supereroe): "Uno" e' l'IA del fumetto. Il logo (sfera petrol/teal con un
personaggio astratto) e' stato scelto dall'utente e ritagliato con
trasparenza in `public/img/`.

**Stato al 2026-10-02**: Uno e' il configuratore - la sua home sono i
Progetti, nel suo database ci sono solo utenti/permessi e `projects`. Il
gestionale Assilevi e' stato generato da qui (preset `assilevi`) in
`/var/www/aib/dev_assilevi` (repo `readygroupit/aib-assilevi`, database
`dev_aib_assilevi`, http://assilevi.localhost): da li' in poi vive per conto
suo. Il codice dei pacchetti resta in Uno perche' serve a generare.
Le capacita' del prompt si registrano solo se i loro pacchetti sono installati
(`PromptToolRegistry::register($classe, ...$pacchetti)`), quindi Uno non
mostra Pratiche e un progetto non mostra Progetti.

**Stato reale (2026-09-20)**: il framework, l'autenticazione, il sistema a
componenti, il sistema a pacchetti, lo scheduler/eventi e un'interfaccia a
prompt collegata a Claude funzionano e sono stati verificati end-to-end.
**Il motore di provisioning vero (creare davvero un nuovo progetto da zero)
non esiste ancora** - e' il pezzo piu' importante rimasto, vedi "Cosa manca"
in fondo.

## Regole che non si toccano

Queste non sono suggerimenti, sono state stabilite esplicitamente e violate
per errore piu' volte in questa sessione - controllarle sempre prima di
aggiungere codice:

- **Zero Composer, zero librerie esterne.** Autoload a mano
  (`autoload.php`, mappa namespace -> cartella). Se un giorno servira'
  davvero una libreria, si reintroduce Composer allora, non prima.
- **Query solo nei Repository.** Mai una stringa SQL in un Controller,
  Service, o altrove - sempre un metodo su una sottoclasse di
  `App\Repository\AbstractRepository`. `App\Service\DbService` e' l'unica
  eccezione (e' il driver di basso livello su cui i Repository sono
  costruiti). Controllare con:
  `grep -rlE "SELECT |INSERT INTO|UPDATE |DELETE FROM" src module bin | grep -v Repository | grep -v DbService`
- **HTML solo in `view/*.phtml`** (o nel renderer JS quando il contenuto e'
  generato via JSON - vedi sotto). Mai una stringa HTML costruita dentro una
  classe PHP.
- **Container auto-wiring**: ogni classe risolta via `Container::get()` deve
  avere `Container $container` come **unico** argomento del costruttore
  (risolve le proprie dipendenze internamente). Il container non fa
  reflection: se il costruttore chiede altro, esplode.
- **Codice sempre in inglese**: tabelle, colonne, classi, file, cartelle,
  codici permesso/config, chiavi degli array delle route. Solo le label/testi
  mostrati all'utente e i path delle URL restano in italiano.
- **Mai commenti che spiegano il "cosa"** (i nomi devono bastare) - solo il
  "perche'" quando non e' ovvio (un vincolo nascosto, una scelta di design
  che qualcuno potrebbe voler "sistemare" senza sapere perche' e' cosi').

## Architettura

### Kernel (`src/App/Core/`)

`Application` (dispatch), `Container` (DI, vedi sopra), `Router`
(`:param`/`[opzionale]`), `Request`/`Response`, `Config` (dot-notation su
`config/autoload/{global,local}.php`), `View` (`.phtml`, `$this->e()` per
escaping, `$this->asset($path)` per il cache-busting - vedi sotto).

### Moduli (`module/<Nome>/`)

Aree funzionali con proprio namespace, `config/routes.php` (aggregate via
glob in `config/routes.php` alla radice), `src/Controller|Service|...`,
`view/`. Oggi c'e' solo `Index` (home/prompt/utenti/area riservata demo).

### Cache-busting asset

`View::asset('/css/x.css')` appende `?v=<filemtime>` (stesso meccanismo di
Core: `Common\Helper\URLHelper`). **Ogni** `<link>`/`<script src>` in un
layout deve passare da li', mai un path nudo. In piu', i file `.js` importati
da altri `.js` (`import './x.js'`, non passano mai da un tag HTML quindi
`asset()` non li tocca) hanno `Cache-Control: no-cache` sia nel server di
sviluppo (`public/index.php`, shim per `PHP_SAPI==='cli-server'`) sia sulla
vhost Apache reale (`public/.htaccess`, `mod_headers`).

## Fase 0 - entita' base

Tabelle sempre presenti in ogni progetto (non sono pacchetti):
`profiles`, `users`, `password_resets`, `permission_groups`, `permissions`,
`profile_permissions`, `user_permissions`, `config_groups`, `configs`,
`attachments`.

- **Soft delete ovunque**: colonna `status` (0=eliminato, 1=attivo).
  `AbstractRepository::delete()` non fa mai un `DELETE` reale.
- **Audit automatico**: `created_at/by`, `updated_at/by`, timbrati da
  `AbstractRepository::insert()/update()`. Niente FK su queste due colonne
  (evita dipendenze circolari).
- **Unicita' scoped al soft-delete**: colonna generata `STORED`
  (`IF(status<>0, col, NULL)`) + `UNIQUE KEY` sulla colonna generata - un
  record eliminato libera il suo valore univoco per il riuso.
- **Permessi/config gerarchici**: `permission_groups`/`config_groups` sono
  alberi self-referenziati (`parent_id`). `configs` supporta dipendenze
  (`depends_on_config_id`+`depends_on_value`) - i "connettori" (Klarna, SMTP,
  ecc.) sono semplicemente un `config_group` con dentro dei `config` tipati,
  non un concetto a parte.
- **`attachments`** e' polimorfica (`entity`+`entity_id`, niente FK
  possibile) - qualunque pacchetto puo' appoggiarcisi senza duplicare lo
  storage file.

## Fase 1 - autenticazione

`AuthService` (login/logout/permessi effettivi in sessione/reset password),
`AuthController` (redirect a `/login` se non autenticato, eccezione
`ForbiddenException` -> 403 se manca il permesso richiesto via
`$requiredPermission`). Permesso effettivo = permessi del profilo + grant
diretti utente - revoke diretti utente, calcolato **al login**, cache in
sessione (non si aggiorna finche' non si rifa' login).

Credenziali: utenti demo con la password scelta dall'utente (non scriverla qui).

## Fase 2 - componenti, JSON non HTML

**Decisione architetturale piu' importante del progetto**: ogni pagina e'
fatta di *componenti* (`App\View\Component\*Component::toData()`) che
restituiscono **solo dati JSON**, mai HTML. Lo stesso identico JS
(`public/js/components/*.js`, registrato in `registry.js`) disegna il DOM sia
per il caricamento diretto di una pagina (JSON incorporato in
`<script id="page-data">`, letto da `app.js`) sia per le risposte AJAX
(`Content-Type: application/json` puro). Questo e' il meccanismo su cui gira
anche l'interfaccia a prompt (vedi sotto) - non e' stato costruito due volte.

Componenti esistenti: `DataTableComponent` (con azioni per riga: un bottone
se una sola azione, un menu a tendina se piu' di una - filtrate per permesso
lato server, mai fidarsi del client), `StatBoxComponent` (sparkline
calcolata in JS), `MenuGridComponent` (griglia di box, non una sidebar - **
regola esplicita dell'utente: mai una sidebar**), `HeroComponent` (il prompt,
vedi sotto).

**Regola del layout**: tutto deve stare dentro una `.card` (rettangolo con
bordo/ombra/radius) - niente testo o elementi sciolti sullo sfondo.

## Fase 3 - pacchetti installabili

`packages/<nome>/package.php` e' un manifest **PHP puro** (mai JSON: zero
costo di parsing, commenti nativi, `SomeClass::class` verificabile
dall'IDE). Un pacchetto e' un **catalogo di campi possibili**, non uno
schema fisso.

### La decisione di design (discussa a lungo, non ridiscuterla senza nuove prove)

Una tabella sola per entita', sempre. I campi si aggiungono con
`ALTER TABLE ... ADD COLUMN` in coda quando servono (MySQL 8
`ALGORITHM=INSTANT` lo rende un'operazione di metadati, non un rischio anche
su una tabella con dati veri). **Quali campi diventano colonne reali, e quali
sono obbligatori, si decide per ogni progetto al momento dell'installazione**
- non e' una proprieta' fissa del pacchetto (prova concreta: il progetto
Istrumed ha clienti solo aziendali, niente nome/cognome - "obbligatorio" per
un pacchetto "clienti" non significa niente in astratto).

Dividere in piu' tabelle e' giustificato **solo** per un vero pattern di
accesso diverso (es. `communications`/`communication_contents`: metadati
letti di continuo, corpo del messaggio pesante letto solo quando serve) o per
entita' normalizzate indipendenti (es. `geo`: regione/provincia/comune sono
cose a se', non "campi opzionali" di qualcos'altro). Mai per "alcuni campi
sono opzionali" da solo - le tabelle di estensione per quello sono state
proposte e scartate esplicitamente.

### Meccanica

- `App\Package\PackageManifest` - carica il manifest.
- `App\Package\PackageInstaller::build($manifest, $selection)` - dato cosa e'
  stato scelto per questo progetto, genera `core` (le `CREATE TABLE`, con FK
  quando un campo ha `'references'`) e `pending` (una `ALTER TABLE` per ogni
  campo del catalogo non scelto ora - file pronti ma non eseguiti, letteralmente
  "il pacchetto consegnato ma non installato").
- `App\Package\PackageInstallerRunner::installMany($installs, $targetDir)` -
  risolve l'ordine tra piu' pacchetti (topological sort su `dependsOn` **e**
  sulle dipendenze derivate dai campi selezionati che hanno
  `'references' => ['package' => '...']` - non installa mai un pacchetto
  intero per un campo che non e' stato scelto), scrive `db/schema.sql`,
  `db/migrations/pending/*.sql`, copia `db/seed/*.sql` se il pacchetto ne ha
  uno.
- **Un campo non puo' chiamarsi** `id`/`status`/`created_at`/`created_by`/
  `updated_at`/`updated_by` (collide con le colonne di sistema -
  `PackageInstaller` lo blocca a runtime, e' successo per errore due volte).
- **Se un pezzo di codice (handler/motore) scrive incondizionatamente su un
  campo**, quel campo deve essere `'base' => true` nel manifest, non solo
  `'defaultRequired'` - altrimenti un'installazione che non lo seleziona
  rompe a runtime (successo con `cases.primary_customer_id` e coi timestamp
  dello scheduler).

### Pacchetti esistenti

`customers`, `geo` (dati geografici italiani reali, presi dalla libreria Core
esistente - `region`/`district`/`municipality`/`postal_code`,
`selectableDirectly=>false`), `leads`, `cases`, `case_participants`,
`document_requests`, `tasks`, `communications`, `scheduler`,
`ai_agent_tasks`. Nessuno e' specifico di un cliente - generalizzati apposta
a partire dal brief di un progetto reale (Assilevi, gestione pratiche di
assistenza voli).

## Scheduler + eventi (motore reale, non solo schema)

- `App\Job\JobHandlerRegistry`/`JobRunner` - job programmati (una tantum o
  ricorrenti via `interval_minutes`, niente cron-expression completa per
  scelta), eseguiti da `bin/run-scheduler.php` (pensato per un vero cron di
  sistema).
- `App\Event\EventDispatcher` - **ogni** `insert()/update()/delete()` di
  `AbstractRepository` emette un evento (`"{tabella}.created/updated/deleted"`)
  gratis, per qualunque pacchetto presente o futuro. `listen()` accetta
  anche una closure (non solo una classe), perche' "quale evento fa scattare
  quale azione" e' configurazione specifica del progetto
  (`config/events.php`, opzionale, mai committato per `uno` stesso).
- `App\Job\Handler\RunAiAgentTaskHandler` - ponte generico tra `scheduler` e
  `ai_agent_tasks`: crea solo il record della proposta (`approval_status`
  di default `pending`, mai `not_required` se non specificato - il default
  piu' sicuro), **non chiama nessuna AI vera**. Il comportamento reale degli
  agenti resta il pezzo non costruito.

## Interfaccia a prompt

`App\Prompt\PromptToolRegistry` - stesso principio di registrazione di
`JobHandlerRegistry`/`EventDispatcher`. Ogni "capacita'" (`App\Prompt\Tool\*`)
dichiara `name/description/inputSchema/menuLabel/requiredPermission/triggers`
e `execute()` che torna dati componente (mai HTML - stesso motore di Fase 2).

- `App\Service\ClaudeService` - chiamata HTTP diretta alla Messages API
  (niente SDK ufficiale, `uno` non ha Composer), tool-use: Claude sceglie una
  capacita' o fa una domanda di chiarimento (nessun tool) se la richiesta non
  e' chiara. Chiave in `config/autoload/local.php` -> `claude.apiKey`
  (gitignored, da valorizzare a mano).
- **Fast-path locale**: `PromptController::matchLocalTool()` controlla prima
  se il messaggio corrisponde (esatto o contenuto) a una delle
  `triggers()` di una capacita' gia' registrata - se si', esegue subito senza
  chiamare Claude (gratis, istantaneo, utile anche in produzione oltre che
  per testare senza credito). **Non e'** un resolver NLU generico (scartato
  apposta in Fase 2) - solo frasi letterali dichiarate da ogni capacita'.
- `config/prompt-tools.php` - registra le capacita' di `uno` all'avvio
  (`show_menu`, `list_users`, `list_permissions` oggi).

### L'hero (`HeroComponent` + `public/js/components/hero.js`)

Due stati visivi, cambiati con la classe `hero-wrap--top`:
- **Iniziale** (pagina appena caricata): logo grande (76px) + saluto due
  righe ("Ciao, sono Uno" / "il tuo assistente...") sopra il box, box senza
  icona dentro.
- **Dopo il primo invio** (o su una pagina dove il prompt parte gia' in
  alto - non ancora costruito, vedi "Cosa manca"): logo grande nascosto,
  icona piccola (30px) in linea a sinistra della casella, **niente** scritta.

Nota tecnica se si tocca questa logica: per verificare se un elemento e'
davvero visibile quando l'antenato potrebbe essere `display:none`, usare
`element.offsetParent !== null`, **non** `getComputedStyle(el).display` (che
riflette solo la regola propria dell'elemento, non l'ereditarieta').

**Voce**: `SpeechRecognition` nativa del browser, `continuous`+
`interimResults` attivi (il testo si popola mentre parli, non solo a fine
frase). Icona rossa pulsante mentre ascolta. Frase vocale **"invia comando"**
(due parole apposta, rara, controllata anche sui risultati provvisori - non
si aspetta piu' `isFinal`, che con l'ascolto continuo puo' non arrivare mai
se non c'e' una pausa vera) invia senza toccare tastiera/mouse. Scorciatoia
**Ctrl+Shift+M** avvia/ferma il microfono come un click.

**Icone**: Font Awesome 7 Pro, stile *regular*, SVG copiate da
`/var/www/core/public/fontawesome/svgs/regular/` (licenza gia' in uso nel
progetto Core) e inlineate in `public/js/icons.js` - **unica eccezione
voluta** alla regola "mai innerHTML con dati dal server": sono stringhe
statiche del nostro bundle, non dati esterni.

**Comparsa componenti**: `registry.js` -> `mountComponents(root, components)`
- unica funzione (usata sia dal caricamento pagina sia dalle risposte del
prompt), li fa comparire in ordine con dissolvenza scaglionata
(`index * 90ms`), non tutti insieme.

## Config per ambiente

Come nei progetti di Core: `config/autoload/global.php` (versionato) ha i
valori di sviluppo e di produzione e sceglie con `APPLICATION_ENV`:
`localhost` (SetEnv dei vhost del PC) = sviluppo, qualunque altro valore o
nessuno (vhost `prod`, cron di root) = produzione. In produzione non c'e'
`local.php`; sul PC e' facoltativo (solo la chiave Claude). I progetti
generati hanno in piu' `config/autoload/project.php` (versionato: nome,
slug, database per ambiente). Ordine: global -> project -> local.
Dal terminale del PC: `APPLICATION_ENV=localhost php8.4 bin/...`.

Database: Uno `uno` (sviluppo) / `prod_uno` (produzione); progetti
`dev_aib_<slug>` / `prod_aib_<slug>`. Cartelle: `/var/www/aib/dev_<slug>` /
`/var/www/aib/prod_<slug>` (anche Uno: `prod_uno`). Indirizzi:
`http://<slug>.localhost` / `https://<slug>.aibrains.it`, calcolati ogni
volta dallo slug (`ProjectProvisioner::projectUrl()`): le colonne
`projects.url` e `projects.path` sono solo storiche, cosi' lo stesso
database vale nei due ambienti.

## Provisioning

**Creazione (pacchetto `provisioning`)**: da `/progetti` in Uno - nome,
identificativo, preset, pacchetti, dati demo - con
`App\Provisioning\ProjectProvisioner`:

- cartella (`dirPattern`): codice del framework + SOLO i pacchetti scelti e
  le loro dipendenze (niente `.git`, `design`, README, local.php);
- database (`dbPattern`): `db/schema.sql` (tabelle di base), poi i pacchetti
  con lo stesso percorso di un'installazione normale (MigrationWriter ->
  MigrationRunner, i file finiscono in `db/migrations/applied` del progetto),
  i seed dei pacchetti (geo), `db/seed.sql` e la rimozione dei permessi dei
  pacchetti non installati (menu e Guida filtrano per permesso);
- dati demo del preset (`packages/provisioning/presets/<chiave>/demo.sql`),
  con le date spostate in avanti di quanto passato da `demoReferenceDate`;
- `config/autoload/project.php` e `setup.status = pending` (primo accesso).

Se un passaggio fallisce, cartella e database appena creati vengono rimossi.

- **Sviluppo** (`runInRequest`): il progetto nasce dentro la richiesta e
  risponde subito grazie al vhost jolly
  `/etc/apache2/sites-available/zz-uno-projects.conf` (`*.localhost` ->
  `/var/www/projects/%1/public`, collegamento creato dal provisioner verso
  `/var/www/aib/dev_<slug>`; caricato per ultimo: i vhost con ServerName
  esplicito vincono).
- **Produzione**: Uno mette il progetto in coda (`projects.provisioning_status`
  `queued` -> `running` -> `ready` | `failed`, log in `provisioning_log`) e lo
  crea il cron di root `bin/provision-queue.php`, che poi passa la cartella a
  www-data e, per Uno e per ogni progetto pronto, crea vhost e certificato
  se mancano (`App\Provisioning\SiteInstaller`, come gli script
  `new_readyservices_site.sh` di Core): prototipo
  `packages/provisioning/vhost/aibrains.conf` (`__HOST__`, `__DIR__`, niente
  PHP fuori da index.php) -> `/etc/apache2/sites-available/prod_aib_<slug>.conf`,
  `a2ensite`, `apachectl configtest` (se fallisce lo toglie), reload,
  `certbot --apache --redirect`. Fatto quando esiste il `-le-ssl.conf`; se
  certbot fallisce riprova una volta l'ora. Crontab di root:

  ```
  * * * * * /usr/bin/php8.4 /var/www/aib/prod_uno/bin/provision-queue.php >> /var/log/uno-provisioning.log 2>&1
  ```

**Primo accesso** (`App\Service\FirstAccessService`,
`Auth\Controller\FirstAccessController`, `/primo-accesso`): un progetto
nuovo non si usa finche' non e' configurato (tabella `settings`:
`setup.status`, `setup.account`, `project.*`). Si entra solo dal link
`<url>/primo-accesso?t=<slug cifrato>` (`App\Support\FirstAccessToken`,
AES-256 con `provisioning.tokenKey`, uguale su Uno e progetti), che Uno
mostra con "Copia link primo accesso". Passo 1: l'amministratore sceglie
nome, email (= nome utente) e password, e resta collegato. Passo 2: nome,
ragione sociale, email, logo (obbligatori), P.IVA, telefono, indirizzo; il
logo va in `public/uploads/` (fuori da git). Ogni pagina riporta al passo
mancante, con i dati gia' inseriti; chiuso il wizard il link non conta piu'.
Senza tabella `settings` o senza `setup.status` (Uno, Assilevi) il progetto
vale come configurato. Preset oggi: `assilevi` (assistenza reclami voli).

## Configurazione: cosa manca per essere operativi

`/configurazione` (prompt: "configurazione") e la scheda "Cosa manca ancora"
del tour di benvenuto mostrano la stessa checklist con barra di avanzamento
(`App\Service\SetupService::payload()`, renderer unico `public/js/setup.js`).
Solo i passaggi **obbligatori** contano nella percentuale (oggi: Jotform,
Google Sheets, almeno un modello di messaggio); WhatsApp, ConciliaWeb e
classificazione del disservizio sono facoltativi. `done: null` = attivita'
manuale non verificabile, ne' fatta ne' mancante.

- **Connettori** (`src/App/Connector/`): `JotformConnector` (chiave API),
  `GoogleSheetsConnector` (account di servizio, JWT firmato con openssl),
  `WhatsAppConnector` (Cloud API Meta). Ognuno espone `setupSpec()` (passi +
  campi della procedura guidata) e `connect()`, che verifica contro il
  servizio vero e salva in `connector_credentials` SOLO se funziona.
  **"Collegato" oggi significa credenziali verificate**: la sincronizzazione
  dei contatti/fogli e l'invio WhatsApp non esistono ancora.
- **Passaggi-sezione**: portano a una pagina con `?setup=<chiave>`, che mostra
  in cima la spiegazione (`SetupService::HINTS`, componente `setup-hint`,
  iniettato da `AbstractController::renderPage()`).
- Pacchetto `message_templates` (`/modelli-messaggio`): testi predefiniti
  per email/WhatsApp; il campo `body` usa `'input' => 'textarea'` nel
  manifest (opt-in del form generico).
- Chiavi salvate in chiaro in `extra_config`/`api_key` (come gia' previsto da
  `connectors`): cifratura a riposo ancora da fare.

**Agenti (demo)**: `/agenti` + flusso "I tuoi colleghi digitali" in cima alla
home (`App\Service\AgentService`, pacchetto `agents`, volti SVG in
`public/js/avatars.js`). 8 agenti con nome e volto leggono dati veri e
scrivono messaggi con testi preparati - **nessuna chiamata a Claude**: i
metodi `gen*` sono il punto in cui metterla. `is_enabled` e `autonomy`
(suggerisce / chiede approvazione / agisce da solo) governano davvero il
comportamento; `schedule` e `rule_text` sono solo salvati. Le azioni
approvate scrivono nel gestionale (comunicazione "inviata", classificazione)
ma non mandano nulla fuori. "Ricomincia la demo" rigenera il flusso.

**La Guida** (`public/js/onboarding.js`, testi in `config/guide.php` di
ogni progetto). Due forme:
- **in pagina**: "cosa puoi fare", "aiuto", "chi sei" o la voce "Guida" del
  menu profilo mostrano le schede di `config/guide.php` + le funzioni
  disponibili (`ShowWizardTool`, componente 'wizard'), con risposta e
  suggerimenti in chat (`reply`/`suggestions` della guida);
- **modale**: si apre da sola sulla home al primo accesso (salvo
  `'autoOpen' => false`, come su Uno: la home e' solo il prompt) e finche'
  c'e' configurazione obbligatoria da completare (scheda "Cosa manca ancora",
  riquadro rosso nella toolbar).

## Cosa manca (in ordine di priorita' concordato)

1. **Provisioning vero (Fase 4)** - il pezzo che sblocca tutto il resto.
   Deciso: va costruito come un pacchetto (`provisioning`) installato su
   `uno`, non come codice fisso nel core, cosi' un progetto generato puo'
   opzionalmente avere il proprio mini-configuratore ristretto a un
   sottoinsieme di pacchetti (basta copiargli dentro solo quei
   `packages/*/package.php` - la restrizione e' gratis, non serve un
   meccanismo apposta).
2. UI minima per i pacchetti gia' installati (liste leads/cases/
   ai_agent_tasks, riusando `DataTableComponent` come per `/utenti`).
3. Una fetta verticale vera di comportamento AI (es. `RunAiAgentTaskHandler`
   che chiama davvero Claude invece di creare solo il record).
4. Pattern "pacchetto connettore" per sistemi esterni (Jotform/Google
   Sheets/ecc.) - discusso concettualmente (config per le credenziali,
   tabella generica tipo `oauth_connections` per i token, un pacchetto per
   servizio esterno), mai costruito.
5. Prompt presente in ogni pagina (non solo home), fisso in alto
   (`position: sticky`) - oggi l'hero esiste solo su `IndexController`.
6. Pagina di modifica utente (`/utenti/:id`) - la rotta non esiste, il
   bottone "Modifica" in `/utenti` non porta da nessuna parte.
7. `legal_proceedings`/`payments` (pacchetti identificati ma non costruiti),
   `LoggerService` dedicato, CSRF sui form di auth.

## Sviluppo locale

- PHP 8.4 (`php8.4 -S 0.0.0.0:8935 -t public public/index.php`, configurato
  in `.claude/launch.json` del progetto `workspace-ecommerce` come
  `uno-static`), oppure la vhost Apache `uno.localhost`.
- MySQL: database `uno`, utente/password di sviluppo in
  `config/autoload/global.php` (ramo `APPLICATION_ENV=localhost`).
- Test: nessun framework di test automatico - verifica manuale via browser
  reale o script `php -r` contro un database di prova (creato e distrutto
  per l'occasione, mai contro `uno` stesso).
