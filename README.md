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

`customers` (con i referenti, `customer_contacts`), `geo` (dati geografici
italiani reali, presi dalla libreria Core esistente -
`region`/`district`/`municipality`/`postal_code`,
`selectableDirectly=>false`), `leads`, `cases`, `case_participants`,
`document_requests`, `tasks`, `communications`, `scheduler`,
`ai_agent_tasks`, `services`, `customer_services`, `installments`,
`work_orders`, `appointments`, `planner`, `worklog`, `editorial`, `invoices`,
`client_health`, `media_library`, `client_requests`, `leaves`, `expenses`,
`quotes`. Nessuno e'
specifico di un cliente - generalizzati apposta a partire dal brief di un
progetto reale (Assilevi, gestione pratiche di assistenza voli; Brains at
Work, agenzia di comunicazione).

**Installare un pacchetto su un progetto che esiste gia'** (anche Uno):
`APPLICATION_ENV=localhost php8.4 bin/install-packages.php <nomi>` -
migrazioni con tutti i campi del catalogo (come il provisioner), dati di
riferimento, permessi `{pacchetto}.view/create/edit/delete` al profilo
amministratore (`PackagePermissionRepository`). Le migrazioni finiscono in
`db/migrations/applied` e arrivano in produzione da git gia' "applicate":
li' serve lo script SQL in `public/tmp` preparato insieme (stesse CREATE
TABLE + permessi, rilanciabile).

### Campi del manifest per i form

Oltre a `format`/`help`/`autocomplete`/`input`:
- `'options' => [...]`: menu a tendina, e il valore salvato deve essere uno di
  quelli (controllato in `AbstractEntityController::collectAndValidate()`);
- `'format' => 'boolean'`: interruttore si'/no (`TINYINT(1)`, salva 1/0);
- `'showIf' => ['field' => 'billing_type', 'in' => ['ricorrente']]`: il campo
  compare solo con quei valori dell'altro (un box senza campi visibili sparisce);
- `'help'`: compare sotto il campo, in piccolo;
- date con il calendario del browser, decimali anche con la virgola
  ("1.250,50" -> 1250.50);
- `?campo=valore` nell'url di "nuovo" precompila il form (es.
  `/servizi-clienti/nuovo?customer_id=5`);
- una sorgente di `EntityReferenceController` con `'fill'` compila altri
  campi quando si sceglie una voce (es. un servizio del listino porta
  descrizione, prezzo, rinnovo);
- `'format' => 'datetime'`: data e ora del browser (salvata `Y-m-d H:i:s`);
- `hiddenFields()` nel tool: campi che viaggiano col form senza vedersi (es.
  a quale commessa appartiene un'attivita', arriva da `?entity_type=...`);
- `'optionsSource' => 'staff'`: scelta multipla a chip dei colleghi, salvata
  come "2,3";
- `crossFieldErrors()` nel controller: controlli tra piu' campi (es. la fine
  dopo l'inizio); `afterDelete()` dopo un'eliminazione;
- `'autocomplete' => ['source' => ..., 'freeText' => true]`: il valore e' il
  testo (es. il CAP, `/geo/cap/cerca`), i suggerimenti compilano i campi
  collegati con `fill`;
- form.js: etichetta sul bordo dell'input, righe riempite fino in fondo
  (`packGrid()`), controllo prima dell'invio (obbligatori con `*`, errore
  sul pulsante e sul campo), "Per il cliente X" in testa ai form con
  `customer_id` (`formContext()`).

Dalla chat "nuovo X" (`new` + `values` di `AbstractEditEntityTool`) apre
`{baseUrl}/nuovo?campo=valore`: componente `open`, che hero.js apre come un
link; i nomi (colleghi, clienti) diventano id con `App\Support\ReferenceResolver`.
Il prompt di sistema ha la data di oggi e chi scrive. Le schermate dello
storico gia' viste si ricaricano dopo un salvataggio (`dataVersion` in hero.js).

Pagina di un elemento: `AbstractEditEntityTool::buildPage()` mette prima la
scheda (`detailComponents()`, es. la scheda cliente) e sotto il form chiuso
("Dati del cliente - Modifica"); dopo un salvataggio l'esito compare in cima
(componente `notice`). Hook del controller: `afterCreate()`,
`afterUpdate()`, `componentsAfterCreate()`.

## Clienti, servizi venduti e scadenzario

Prima fase del progetto Brains at Work (agenzia di comunicazione), fatta
come pacchetti generici e attiva anche su Uno (sviluppo):
- `customers`: tipo di rapporto (cliente, intermediario, fornitore,
  collaboratore), importanza (chiave/abituale/occasionale), settore,
  referente interno, "portato da" con percentuale/accordo
  dell'intermediario; referenti in `customer_contacts`
  (`/clienti/referenti/...`, chi decide in evidenza);
- `services`: listino, una tantum o ricorrente (mensile ... annuale), costo
  per voi (margine), fornitore, durata (servira' a pianificare);
- `customer_services` (`/servizi-clienti`): il servizio venduto, con data
  di rinnovo propria, rinnovo automatico, preavviso, piano di pagamento
  (unica soluzione, acconto e saldo, rate mensili), metodo di pagamento;
- `installments` (`/scadenzario`): una riga per ogni incasso atteso,
  generata da `App\Billing\PaymentPlanService` (alla creazione, e di
  nuovo se cambiano importo o piano e nulla e' ancora incassato). "In
  ritardo" non si salva: e' una scadenza passata ancora da incassare.

`App\Billing\AccountOverviewService` calcola per tutte e tre le schermate
valore annuo, prossimo rinnovo, incassi aperti/scaduti e **la cosa da fare
adesso** per ogni cliente (sollecita, conferma il rinnovo, avvisa
dell'addebito, proponi qualcosa): lista clienti con pannello
(`customer-list`, colonna "Situazione" = la cosa piu' urgente), scheda
cliente (`customer-profile`), scadenzario (`due-board`), servizio
(`service-summary`). Azioni con un clic (`public/js/accounts-ui.js`):
"Incassato", "Ho sollecitato" (salva solo la data), "Rinnova ora". Numeri e
date "a voce" in `App\Support\ItalianFormat`. Prompt: "clienti",
"scadenzario", "chi deve pagare", "rinnovi" (tool `list_customers`,
`show_due_schedule`, risposta in una frase con `replySummary()`). Dove
ci sono le pratiche (Assilevi) "Clienti" resta la vista pratiche
(`ListCustomerClaimsTool`, registrato dopo con lo stesso nome).

Rinnovi automatici: job `renew_customer_services` (handler
`RenewCustomerServicesHandler`, una volta al giorno) crea le scadenze dei
periodi arrivati, anche arretrati. L'addebito vero (Stripe, SEPA, PayPal) e
la fattura elettronica sono fasi successive.

Preset `brainsatwork` (`packages/provisioning/presets/brainsatwork`):
`generate-demo.php` crea nel database di sviluppo un'agenzia inventata
(clienti, intermediario, fornitori, listino, servizi, incassi passati
pagati e due in ritardo) e la esporta in `demo.sql`.

## Commesse e attivita'

Seconda fase Brains at Work, pacchetti generici:
- `work_orders` (`/commesse`): la commessa per un cliente (tipo, titolo,
  stato, priorita', responsabile, fornitore esterno, inizio, consegna,
  attesa del cliente, ultimo sollecito, ultimo movimento) e i **tipi di
  commessa** (`/tipi-commessa`) con le **fasi a modello** (ordine, giorno
  previsto dall'inizio, tempo, "aspetta il cliente", checklist);
- `tasks` (`/attivita`) ampliato: giorno previsto, priorita', tempo previsto
  e impiegato, ordine, aspetta il cliente, checklist, ricorrenza. Le fasi di
  una commessa sono attivita' con `entity_type = 'work_orders'` (niente FK:
  `tasks` resta installabile da solo).

`App\Work\WorkOrderService` fa da solo cio' che non va deciso:
- crea le fasi dal tipo (date dall'inizio, consegna dalla durata tipica);
- lo stato segue le fasi: "in attesa del cliente" quando tocca a una fase
  che aspetta il cliente, di nuovo "in corso" quando e' fatta;
- **ripianifica** (`replan()`): chiusa una fase, se la prossima e' gia'
  nel passato le rimaste slittano insieme a oggi, con gli stessi intervalli.

Pausa, consegna e annullamento restano decisioni dell'operatore.
"Il cliente ha risposto" chiude la fase di attesa e passa alla prossima.

`App\Work\TaskService` gestisce:
- fatta / riaperta, con il tempo impiegato;
- la prossima occorrenza di un'attivita' ricorrente, senza arretrati.

`App\Work\WorkOrderOverviewService` prepara i dati di tutte le schermate:
- bacheca per stato, con avanzamento, prossima fase e segnali: in ritardo,
  ferma da `STALE_DAYS`, giorni di attesa;
- scheda commessa con le fasi da spuntare e il previsto contro l'impiegato;
- attivita' per giorno: in ritardo, aspettano il cliente, oggi, 7 giorni,
  piu' avanti, senza data, fatte di recente;
- la frase "cosa fare adesso" di ogni commessa: sollecita, consegna in
  ritardo, la consegna rischia di slittare, ferma, da avviare.

I giorni delle fasi successive di una commessa ferma sono calcolati
(`effectiveDays()`, mai salvati): niente falsi ritardi a catena.

Componenti: `work-order-board`, `work-order-summary`, `task-board`,
`task-summary`, `type-steps` (JS comune in `public/js/work-ui.js`), stile
in `public/css/components/work.css`. La scheda cliente mostra le sue
commesse.

Prompt: "commesse", "lavori in corso", "attivita", "cosa devo fare",
"le mie attivita", "tipi di commessa".

## Calendario e Google Calendar

Terza fase Brains at Work.

**Appuntamenti** (`appointments`, `/appuntamenti`, prompt "calendario",
"agenda", "uscite") ampliati con:
- tipo: shooting, riprese, incontro dal cliente o in sede, call,
  sopralluogo, evento;
- luogo, viaggio in minuti, videochiamata;
- responsabile piu' colleghi (`attendee_user_ids` "2,3", campo a chip con
  `'optionsSource' => 'staff'`);
- cliente, collegamento a una commessa (`entity_type`/`entity_id`),
  checklist, promemoria;
- campi scritti dalla sincronizzazione: `google_event_id`, `meet_url`,
  `google_synced_at`, `source`.

Il collegamento ai contatti (`lead_id`) e' stato tolto: si portava dietro
il pacchetto `leads`.

`App\Calendar\CalendarOverviewService` prepara:
- la settimana, con gli appuntamenti all'altezza della loro ora,
  affiancati se si sovrappongono, il viaggio tratteggiato prima e dopo, le
  attivita' del giorno in testata;
- i conflitti: la stessa persona in due appuntamenti, viaggio compreso;
- l'elenco dei prossimi 14 giorni, che e' anche la vista da telefono;
- la scheda di un appuntamento, con mappa, Meet, partecipanti e checklist.

Componenti `calendar-week` e `appointment-summary`, stile
`public/css/components/calendar.css`. Clic su uno spazio vuoto della
settimana: nuovo appuntamento a quell'ora. Durata proposta per tipo
(shooting 8 h, call 30 min). Le schede di commessa e cliente mostrano i
loro appuntamenti.

**Google Calendar** (`App\Connector\GoogleCalendarConnector`, OAuth web
con refresh token):
- si collega da `/configurazione` con ID client e segreto di Google
  Cloud, poi si autorizza su Google;
- il ritorno arriva su `/configurazione/google/callback`, URI da
  registrare in Google Cloud; Google lo accetta in https, quindi va
  provato sul server, non su `*.localhost`;
- il connettore implementa `RedirectingConnectorInterface` e setup.js
  segue il `redirect`.

`App\Calendar\CalendarSyncService` sincronizza nei due sensi:
- verso Google: ogni salvataggio diventa un evento, con i colleghi
  invitati per email, il promemoria e il link Meet per le videochiamate
  (`conferenceData`); annullato o eliminato vuol dire evento cancellato;
- da Google, con il syncToken: eventi nuovi o spostati aggiornano o
  creano appuntamenti, quelli cancellati li annullano. Vince l'ultima
  modifica.

Se Google non risponde l'appuntamento resta "da mandare" (`findToPush()`):
lo recupera il job `sync_google_calendar` ogni 5 minuti.

`bin/run-scheduler.php` crea da solo i job di sistema (`ensureRecurring`):
sincronizzazione ogni 5 minuti e rinnovi una volta al giorno. Serve un cron
ogni minuto come utente www-data.

**Configurazione**: `SetupService` mostra solo i passaggi dei pacchetti
installati. Assilevi resta com'era; l'agenzia vede Google Calendar.

**Giorni lavorativi** (`App\Work\Workdays`): fasi delle commesse,
slittamenti e ricorrenze giornaliere non cadono di sabato o domenica.

## Organizer

Quarta fase Brains at Work: la giornata di ognuno messa in ordine dal
sistema.

**Pacchetto `planner`**
- Contiene solo gli orari di lavoro (`work_schedules`, pagina `/orari`):
  inizio, fine, pausa, giorni, `focus_percent`, cioe' la parte della
  giornata da riempire con il lavoro pianificato. Il resto resta margine
  per telefonate e imprevisti; per un socio commerciale e' bassa.
- Chi non ha un orario lavora 9-18, pausa 13-14, lun-ven, 85%.
- Ha permessi propri (`'permissions'` nel manifest, letto da
  `PackagePermissionRepository`): `planner.view` per la propria giornata,
  `planner.team` per vedere il team, spostare e riassegnare.
- Nuovi campi form: `'format' => 'time'` e `'optionsSource' => 'weekdays'`.

**Il motore: `App\Planner\DayPlanner::plan(persona, giorno)`**
1. Prende gli appuntamenti come blocchi fissi, con il viaggio prima e dopo.
2. Calcola le finestre libere nell'orario, tolta la pausa; per oggi parte
   da adesso.
3. Mette in fila le cose da fare:
   - solleciti: commesse ferme sul cliente da 3+ giorni al responsabile,
     incassi scaduti al referente interno del cliente;
   - quello rimasto indietro, dal piu' vecchio;
   - le scadenze del giorno;
   - poi per priorita'; a parita', le commesse con la consegna piu' vicina
     e i lavori piu' brevi.
4. Riempie le finestre fino alla parte pianificabile:
   - un lavoro lungo si spezza tra le finestre;
   - un lavoro piu' lungo del giorno si fa in parte, il resto "prosegue";
   - quello che non ci sta resta fuori.

Altre regole:
- Una fase che coincide con un appuntamento della stessa commessa quel
  giorno (es. la giornata di shooting) si fa durante l'appuntamento e non
  occupa altro tempo.
- Oggi e il prossimo giorno di lavoro si portano dietro gli arretrati.
- Non salva niente: si ricalcola sempre.

**`App\Planner\PlannerOverviewService`** prepara le schermate:
- "La mia giornata" (`/oggi`, `?giorno=`, `?persona=` con `planner.team`):
  - saluto con il bilancio ("non ci sta tutto: 1 h 30 restano fuori...");
  - percentuale di carico;
  - "Da sapere": consegne vicine, uscite di domani con cosa preparare,
    sovrapposizioni, uscite nello stesso posto in giorni diversi della
    settimana;
  - scaletta con la riga "Adesso";
  - "Non ci sta oggi", con "Sposta a domani";
  - "Aspettano il cliente", "Se avanzi tempo", "Fatto oggi";
  - dopo l'orario diventa il bilancio di fine giornata.
- "Il team" (`/team`):
  - per persona: carico, cosa sta facendo adesso e dopo, ritardi, ore
    libere o che non ci stanno;
  - proposte "passala a ..." da chi e' pieno a chi ha tempo
    (`/oggi/riassegna`);
  - la settimana a colori.

Componenti `day-plan` e `team-plan`, stile
`public/css/components/planner.css`.

Prompt: "oggi", "cosa devo fare (oggi)", "la mia giornata", "organizer"
(`show_my_day`; l'IA passa 'day' e 'person' per "cosa ha Lena domani");
"il team", "chi e' libero", "cosa stanno facendo" (`show_team`).

## Registro attivita'

Quinta fase Brains at Work: cosa ha fatto ognuno e quanto ci ha messo, per
capire quanto durano davvero i lavori (non per controllare le persone).

**Pacchetto `worklog`**
- Tabelle `work_entries` (persona, giorno, minuti, cosa, attivita',
  cliente) e `work_checkins` (quando ognuno ha risposto).
- Permessi propri: `worklog.view` per segnare il proprio lavoro,
  `worklog.team` per tempi e andamento.
- Le attivita' hanno `source_step_id`, la fase del modello da cui nascono,
  scritta da `WorkOrderService`: serve a confrontare lo stesso lavoro tra
  commesse diverse.

**Quando lo chiede: `App\Worklog\CheckinService`**
- Due momenti: alla pausa (la mattina) e mezz'ora prima della fine (il
  pomeriggio). Se si salta la mattina, la sera si chiede la giornata
  intera.
- Propone:
  - gli appuntamenti, gia' segnati come fatti con la loro durata;
  - le attivita' spuntate senza tempo;
  - cio' che il piano prevedeva in quel pezzo di giornata.
- Per ogni riga: Fatto / In parte / No e i minuti, con - e +; in piu' le
  cose fuori programma.
- "Fatto" chiude l'attivita' (commessa, ripianificazione, ricorrenze).
  Il tempo impiegato e' la somma delle voci.

**Dove si vede**
- Avviso discreto in basso quando e' il momento (`public/js/worklog-nudge.js`,
  `/registro/stato`, "Piu' tardi" lo rimanda di un'ora); pulsante "Segna
  cosa hai fatto" nel bilancio di fine giornata dell'organizer.
- Schermate: `/registro/check-in`, `/registro` (la propria settimana),
  `/registro/voci/...` (a mano, solo le proprie).

**Tempi medi: `App\Worklog\DurationStats`**
- Mediana per tipo di lavoro: la fase del modello, o il titolo normalizzato
  per le attivita' sciolte. Servono almeno 3 casi in 180 giorni.
- Con 3+ casi `DayPlanner` usa il tempo vero ("dal registro").
- Scarti da guardare, scritti come domande e non come sentenze:
  - un lavoro recente durato almeno 1,5 volte il solito;
  - una persona che sullo stesso lavoro impiega stabilmente il 40% in piu'
    degli altri.
- "Usa X nel modello" aggiorna la stima della fase (`/registro/modello`).

**Pannello dei titolari** (`/registro/andamento`, `WorklogOverviewService`):
- ore segnate nella settimana e chi oggi non ha ancora risposto;
- da guardare: chi e' oltre il carico, i casi fuori dal solito, le
  commesse ferme e le attivita' ferme. Per le fasi conta il giorno "vero",
  e una commessa ferma non si ripete fase per fase;
- tempi per tipo di lavoro;
- in fondo, sempre, la nota su privacy e art. 4 dello Statuto dei
  Lavoratori.

Componenti `check-in`, `worklog-week`, `work-insights`, stile
`public/css/components/worklog.css`. Prompt: "cosa ho fatto", "segna il
lavoro", "fine giornata", "il mio registro", "tempi", "andamento", "chi
non ha segnato".

## Piano editoriale

Sesta fase Brains at Work: i contenuti social di ogni cliente, dall'idea
alla pubblicazione, con la revisione interna e l'approvazione del cliente.

**Pacchetto `editorial`**
- `editorial_posts`: cliente, canale (Instagram, Facebook, TikTok,
  LinkedIn), formato, data e ora, tema, testo, cosa serve, stato (idea, in
  lavorazione, revisione interna, da approvare = dal cliente, approvato, da
  modificare, programmato, pubblicato, scartato).
  - Due mani: chi scrive il testo (`writer_user_id`) e chi lo realizza
    (`assigned_user_id`, grafica o video), ognuno con la sua attivita'.
  - Due approvazioni registrate a parte, con chi, quando e come:
    `internal_approved_by/at` e `approved_at/approved_via`.
  - `source_link` (materiale) e `output_link` (file finito) al posto delle
    colonne di link dell'Excel; `parent_post_id` (la storia nata dal post);
    `revision_count` (giri di modifiche).
- `editorial_quotas`: contenuti a settimana da contratto per cliente e
  canale (`/piano-editoriale/contratti`).
- `editorial_strategies`: la strategia social del cliente (obiettivi,
  pubblico, tono, rubriche, da fare e da evitare).
- Permesso proprio `editorial.approve` (revisione interna).

**Logica: `App\Editorial\EditorialService`**
- `advance()`: il passo successivo. "Pronto" va in revisione interna, o
  dritto al cliente se chi lo segna puo' approvare; "da modificare" conta
  il giro e riapre il lavoro per oggi (stima ridotta), con la nota della
  revisione o il commento del cliente.
- `approveAllInternally()` / `approveAllByClient()`: il piano del mese
  approvato in un colpo (dal piano editoriale e dall'area cliente).
- `syncTask()`: testo 4 giorni lavorativi prima, realizzazione 2 giorni
  prima, con i tempi del formato; per un'idea con chi scrive a parte prima
  solo "tema e testo"; per i video in checklist copertina e sottotitoli.
- Chi scrive e chi realizza, se mancano, dalla squadra del cliente
  (`defaultPeople()`).
- `fillGaps()`: le bozze che mancano al contratto; con la strategia
  `proposeIdeas()` fa proporre all'IA tema, testo e materiale.
- `deriveStory()`: la storia dal post.

**Schermate**
- `/piano-editoriale`: la settimana per cliente (con il link alla strategia
  e i giri di modifiche), "Da approvare internamente" con "Approva tutti"
  (solo per chi approva), "Mancano contenuti" con "Crea N bozze" e
  "Proponi con l'IA".
- `/piano-editoriale/:id`: anteprima, il passo successivo, le due
  approvazioni, materiale e file finito, "Crea la storia", "Scrivi con
  l'IA", "Link per il cliente".
- Organizer: a chi approva, "Approva i contenuti in revisione".

## Fatture e fattura elettronica

**Pacchetto `invoices`** (riscritto): fattura a una riga (descrizione,
imponibile, aliquota), numero progressivo per anno assegnato all'emissione,
stato SdI; `company_profiles` con i dati di chi emette (in Configurazione,
obbligatorio: `/fatture/dati-azienda`).

**`App\Invoicing`**
- `InvoiceService`: bozza da un incasso dello scadenzario (pulsante
  "Fattura"), emissione (numero, IVA, totale; di un incasso gia' arrivato
  nasce pagata), XML, invio, pagata (incassa anche l'incasso collegato).
  Incassato un incasso, anche la sua fattura emessa risulta pagata
  (`PaymentPlanService::markPaid()`).
- `FatturaPaXml`: XML FatturaPA FPR12 (TD01, una riga, regime ordinario o
  forfettario N2.2, codice SDI o PEC, IBAN per il bonifico). Sempre
  scaricabile (`/fatture/:id/xml`). Note di credito, ritenute, bollo,
  clienti esteri: non ancora.
- Invio allo SdI con un fornitore collegato (`InvoiceProviderInterface`):
  `FattureInCloudConnector` (crea la fattura su Fatture in Cloud e la invia
  da li') o `ArubaInvoiceConnector` (carica l'XML). Senza fornitore: XML da
  caricare a mano. Lo stato "consegnata/scartata" non torna ancora in
  automatico.
- `/fatture`: da fatturare, bozze, da inviare allo SdI, da incassare,
  pagate di recente.

**Pro forma** (`document_type`): richiesta di pagamento senza valore
fiscale, numerata a parte (PF2026/N), mai allo SdI; "Trasforma in fattura"
crea la fattura in bozza e passa l'incasso. Dal "Da fatturare" si puo'
preparare la pro forma invece della fattura.

## Pagamenti online

`App\Payments\PaymentService` con `StripeConnector` (carta e addebito SEPA)
e `PayPalConnector`, collegabili da Configurazione (facoltativi).
- Link di pagamento per un incasso ("Link" sulla riga dello scadenzario o
  "Paga ora" dall'area cliente): il cliente paga il lordo (totale della
  fattura o importo + IVA).
- Pagamento automatico: il cliente salva carta o mandato SEPA dall'area
  cliente (Stripe, mode=setup); il job giornaliero `charge_due_installments`
  addebita le scadenze arrivate.
- Conferme: webhook Stripe `/pagamenti/webhook/stripe` (rotta con
  `'csrf' => false`, la protegge la firma `Stripe-Signature`), ritorno da
  PayPal `/pagamenti/paypal/ritorno` che incassa l'ordine. L'incasso
  risulta incassato da solo.
- PayPal: solo pagamenti singoli (gli abbonamenti PayPal non ci sono).

## Temperatura dei clienti

**Pacchetto `client_health`**: `customer_touchpoints`, il registro dei
contatti (quando, come, chi, com'era, prossimo passo). Un prossimo passo
con data diventa un'attivita' di chi ha sentito il cliente.

**`App\ClientHealth\HealthScore`**: da 100 si tolgono punti per i segnali
(non sentito da 25/45 giorni, ultimo umore, risultati scarsi o parziali dei
servizi venduti - campo `results` -, pagamenti scaduti, commesse ferme sul
cliente); bene / da seguire / a rischio. Tiepido a ridosso del rinnovo o
scontento di recente = a rischio. Il suggerimento dipende dal segnale piu'
serio. Soglie costanti nella classe.

**Dove si vede**: `/clienti/temperatura` ("clienti a rischio"), pallino e
scheda "A rischio" nella lista clienti, riquadro "Temperatura" nella scheda
cliente con "Com'e' andata", e nell'organizzatore di chi segue il cliente
("Senti X", se non sentito da una settimana).

## Squadra del cliente

`customer_team_members` (pacchetto `customers`): chi segue il cliente e per
cosa (referente, social, grafica, video, sviluppo, ADV, strategia).
Riquadro "Squadra interna" nella scheda cliente; scheda "I miei" nella
lista clienti (referente o in squadra). Serve a proporre chi scrive e chi
realizza i contenuti e a chi va una richiesta. Il rapporto `influencer`
e' tra i tipi di contatto; i servizi venduti hanno un `reference` (es. il
dominio).

## Richieste dei clienti

Pacchetto `client_requests` (`/richieste`, `App\ClientRequests`): quello
che arriva al telefono, su WhatsApp o per email si segna in pochi secondi
(anche da "Segna una richiesta" nella scheda cliente).
- Chi la fa si propone dalla squadra del cliente (social per modifiche e
  contenuti, sviluppo per l'assistenza tecnica, il referente per il
  resto); diventa la sua attivita' nell'organizer, e l'attivita' fatta
  chiude la richiesta (`TaskService::complete()`).
- Le non assegnate le smista chi ha `client_requests.assign` (promemoria
  nell'organizer).
- Lavoro extra: con l'importo, fatto, va nello scadenzario con un clic
  ("Nello scadenzario").
- "Chi chiede di piu'" negli ultimi 30 giorni.

## Preventivi

Pacchetto `quotes` (`/preventivi`, `App\Quotes`): `quotes` e `quote_lines`.
- Righe dal listino (l'autocompletamento compila descrizione e prezzo), una
  tantum o ricorrenti, ognuna con il suo piano di pagamento; sconto, giri di
  modifiche inclusi, condizioni, pagamento (proposto dal cliente).
- Numero P2026/N alla creazione; "Prima di mandarlo manca" (righe, prezzi,
  dati del cliente, scadenza, pagamento).
- Approvazione (`quotes.approve`, promemoria nell'organizer) prima
  dell'invio; stampa/PDF da `/preventivi/:id/stampa`.
- Il cliente lo vede e lo accetta dalla sua area; accettato (o segnato
  dall'agenzia), ogni riga diventa un servizio venduto con le scadenze
  (`QuoteService::accept()`). Inviato e non accettato entro la validita':
  scaduto.

## Ferie, permessi e spese

- `leaves` (`/ferie`): ferie, permessi (anche a ore), ROL, malattia; chi
  approva (`leaves.approve`) decide con un clic. L'organizer non pianifica
  lavoro a chi e' via e toglie le ore del permesso; la squadra lo mostra.
- `expenses` (`/spese`): carburante, parcheggi, modelle, materiali, legate
  a cliente e commessa; approvazione e rimborso (`expenses.approve`). Le
  approvate sono il costo vivo della commessa (scheda commessa).
- Chi non approva vede e modifica solo le sue.

## Archivio foto e video

Pacchetto `media_library` (`/materiali`, `App\Media`): l'indice di foto e
video che restano su Google Drive (`GoogleDriveConnector`, sola lettura,
stesso ID client e callback del Calendario) o in una cartella del
server/NAS (`config 'media' => ['localPath' => ...]`, `LocalFolderSource`).
- `MediaLibraryService::sync()` (job `sync_media_library`, ogni ora): file
  nuovi, cambiati, spariti; il cliente si deduce dalla cartella.
- `MediaAnalyzer` (job `describe_media`, 15 file ogni 10 minuti): l'IA
  guarda l'immagine ridotta (Imagick) o fino a 8 fotogrammi del video
  (ffmpeg, video fino a 300 MB; oltre, l'anteprima di Google) e salva la
  descrizione: `description`, `tags`, persone, ambiente, occasione,
  orientamento e il JSON completo (`analysis`, con i momenti del video).
  Anteprima in `data/media-thumbs`.
- `MediaSearchService`: la richiesta a parole diventa filtri e parole (con
  sinonimi, dall'IA), il database trova i candidati, l'IA tiene quelli
  adatti con il perche'. Senza IA: ricerca per parole.
- Schede con "Apri" e "Link" (copia), scheda del file con i momenti del
  video, descrizione correggibile a mano, "Rifai la descrizione".
- Servono Imagick e ffmpeg sul server.
- `AiService::jsonWithImages()`: le immagini all'IA (OpenAI o Claude).

## Area cliente

`/area/:token`, pagina pubblica senza password (`PortalController`,
`PortalService`, vista `module/Index/view/portal`, stile `portal.css`): il
token lungo e casuale in `customers.portal_token` e' la chiave. Il cliente
vede cosa aspetta lui (proposte da accettare, contenuti da approvare,
anche tutti insieme, pagamenti), come paga
(metodo preferito, addebito automatico), servizi e rinnovi, fatture. Ogni
azione ricontrolla che la riga sia sua; POST con CSRF e redirect. Link da
"Area cliente" nella scheda cliente (`/clienti/:id/area-cliente`, `?nuovo=1`
lo sostituisce) o da "Link per il cliente" su un contenuto.

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

**Importazione dal foglio** (`src/App/Import`, pagina `/importazione`,
prompt "importa"): una tantum o quando serve, niente scheduler. Sorgente:
il foglio Google collegato (`GoogleSheetsConnector::readAllTabs()`) o un file
.xlsx caricato (`XlsxReader`, senza librerie). `TabMapper` riconosce ogni
scheda (contatti, contatti da modulo, pratiche, rimborsi, documenti, da
saltare) e il ruolo di ogni colonna con una chiamata all'IA per scheda,
mandando solo intestazioni e valori ricorrenti (mai nomi, email, telefoni);
senza IA ripiega su parole chiave. `SheetImporter` pulisce (telefoni, date,
aeroporti -> tratta, data dall'ID Jotform se manca), scarta righe di prova,
collega contatti -> pratiche/clienti -> rimborsi/documenti; i contatti si
abbinano solo per ID invio (i doppioni restano separati), il resto per ID,
email, telefono, nome. Rilanciabile: aggiorna, non duplica. Opzione "togli
prima i dati demo" (eliminazione logica, `ImportRepository`). Il foglio non
viene mai modificato.

**Analisi libera** (`src/App/Analysis`, tool `analyze_data`): quando nessuna
schermata pronta risponde esattamente a una domanda sui dati, l'IA
1. riceve la mappa dei dati (`DataCatalog`: tabelle dei pacchetti
   installati + utenti, etichette dai manifest, valori presenti nei campi a
   categorie, collegamenti; mai password/token/chiavi/credenziali/permessi);
2. chiede i dati con un linguaggio controllato (aggregati con
   raggruppamenti per campo o per data, filtri, periodi, elenchi di righe,
   percorsi tra tabelle collegate come `case_id.lead_id.airline`): li
   traduce in SQL di sola lettura `AnalysisRepository`, solo su campi del
   catalogo e con parametri; un'interrogazione sbagliata viene corretta
   dall'IA una volta;
3. compone la pagina con i mattoncini (`header`, `kpis` con una card
   protagonista scura, `chart` rows/columns, `breakdown`, `table`,
   `insight`, `group`) seguendo le regole di gerarchia ed estetica in
   `AnalysisService::COMPOSE_RULES`; `PageComposer` valida tutto e lo
   traduce nei componenti (`ChartComponent`, `InsightComponent`,
   `PanelComponent`, `StatBoxComponent`, `BreakdownComponent`,
   `DataTableComponent`).

2 chiamate all'IA (3 con la scelta del tool, 4 se serve la correzione),
20-35 secondi: nel frattempo la chat mostra frasi di attesa. Piano e
risultati finiscono in `data/logs/<data>/analysis.log`. Le schermate pronte
restano la via veloce: il prompt di sistema (`AiService::converse()`) manda
ad `analyze_data` solo cio' che nessun altro tool copre esattamente.

**Navigazione tra le schermate** (`hero.js`): ogni risultato (dal prompt o
da un link cliccato dentro i risultati, aperto in Ajax da `openInPanel()`)
e' una voce della cronologia del browser (`pushEntry()`,
`history.state.unoEntry`): il tasto indietro del browser e il pulsante
"Indietro" del pannello tornano alla schermata precedente, non alla pagina
caricata prima. Le pagine che non rispondono a componenti si aprono
normalmente.

**Operato degli agenti** (`ShowAgentActivityTool`, componente
`agent-activity`): questa settimana contro la precedente, agente per agente;
la gestione/programmazione resta su `ShowAgentsTool`.

**Primo messaggio sulla home** (`public/js/greetings.js`): sulla home con
cruscotto l'assistente saluta per primo nella colonna della chat, con una
frase a caso tra una dozzina (nome di chi e' collegato, saluto dell'ora) e
tre spunti cliccabili; sulla home vuota di Uno ruota la frase sotto
"Ciao, sono Uno". Le frasi si cambiano in quel file.

`App\Prompt\PromptToolRegistry` - stesso principio di registrazione di
`JobHandlerRegistry`/`EventDispatcher`. Ogni "capacita'" (`App\Prompt\Tool\*`)
dichiara `name/description/inputSchema/menuLabel/requiredPermission/triggers`
e `execute()` che torna dati componente (mai HTML - stesso motore di Fase 2).

- `App\Service\AiService` - chiamata HTTP diretta (niente SDK, `uno` non ha
  Composer) a OpenAI (Chat Completions) o Claude (Messages API), scelto da
  `ai.provider` in `config/autoload/global.php` (oggi `openai`, modello
  `gpt-5-mini`): il modello sceglie una capacita' (tool) o fa una domanda di
  chiarimento se la richiesta non e' chiara. Usato anche dagli agenti
  (`RunAiAgentTaskHandler`). Chiavi in `config/autoload/secrets.php`
  (fuori da git: GitHub blocca i push con segreti; modello in
  `secrets.php.dist`), da creare a mano su ogni server; i progetti generati
  ne ricevono una copia dal provisioner.
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
`local.php`; sul PC e' facoltativo (es. un'altra chiave IA). I progetti
generati hanno in piu' `config/autoload/project.php` (versionato: nome,
slug, database per ambiente). Ordine: global -> secrets (chiavi API, fuori da git) -> project -> local.
Dal terminale del PC: `APPLICATION_ENV=localhost php8.4 bin/...`.

Database: Uno `dev_aib_uno` (sviluppo) / `prod_aib_uno` (produzione); progetti
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

## Utenti, profili e menu

- `/utenti`: nuovo utente, profilo, password provvisoria o "Manda il link
  per la password" (lo stesso flusso di "password dimenticata", serve la
  posta configurata), disattivazione. L'amministratore (id 1) resta
  amministratore.
- `/profili` (`ProfilesController`, `EditProfileTool`): i ruoli e i loro
  permessi, una casella per area. `users.manage` non si toglie
  all'amministratore. I permessi nuovi valgono dal prossimo accesso.
- Menu (`ShowMenuTool`): voci raggruppate per sezione nell'ordine di
  `App\Support\Sections` (Lavoro, Contenuti, Clienti, Amministrazione,
  Impostazioni), con le voci di tutti i giorni in testa (`FIRST`) e la
  prima frase della descrizione.
- Il provisioner non copia nei progetti gli script SQL di Uno
  (`public/tmp`), l'archivio di prova e le anteprime.

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
- MySQL: database `dev_aib_uno`, utente/password di sviluppo in
  `config/autoload/global.php` (ramo `APPLICATION_ENV=localhost`).
- Test: nessun framework di test automatico - verifica manuale via browser
  reale o script `php -r` contro un database di prova (creato e distrutto
  per l'occasione, mai contro `uno` stesso).
