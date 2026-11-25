# Istruzioni per agenti AI su questo repository

Leggi prima **`README.md`** (nella stessa cartella) - contiene lo stato
completo del progetto, l'architettura e cosa manca. Questo file riassume solo
le regole che, se violate, rompono qualcosa o vanno contro decisioni gia'
prese e discusse a lungo con l'utente - non ridiscuterle senza un motivo
nuovo e concreto.

## Le 5 regole che contano di piu'

1. **Zero Composer, zero librerie esterne.** Non proporre di installarne.
2. **Query solo dentro `src/App/Repository/*.php`.** Mai SQL in un
   Controller, Service o altrove.
3. **HTML solo in `view/*.phtml`**, oppure costruito lato client in
   `public/js/components/*.js` quando il server risponde con JSON puro (e'
   il meccanismo standard del progetto, vedi README "Fase 2").
4. **Ogni classe risolta da `Container::get()` deve avere `Container` come
   unico argomento del costruttore** e risolvere le proprie dipendenze
   internamente - niente altri parametri nel costruttore.
5. **Codice sempre in inglese** (tabelle, colonne, classi, file, codici
   permesso/config, chiavi delle route). Solo le label mostrate all'utente e
   i path delle URL restano in italiano.

## Verifica prima di dire "fatto"

Non dichiarare un cambiamento completo solo perche' il codice "sembra
giusto". Prima di riportarlo come concluso:
- Lint minimo: `php8.4 -l <file>` per ogni file PHP toccato.
- Se il cambiamento e' visibile in pagina: verificarlo per davvero (server
  locale `php8.4 -S`, o la vhost `uno.localhost` gia' configurata), non solo
  a occhio sul codice.
- Se si tocca un pacchetto (`packages/*/package.php`): generare lo schema
  con `App\Package\PackageInstallerRunner` e caricarlo su un database di
  prova usa-e-getta (mai contro il database `uno` reale) prima di dire che
  funziona.

## Config e produzione

`config/autoload/global.php` e' per ambiente (`APPLICATION_ENV=localhost` =
sviluppo, altrimenti produzione), come in Core: in produzione non esiste
`local.php`. Script da terminale sul PC: `APPLICATION_ENV=localhost php8.4
bin/...` (senza, puntano al database di produzione). Coda dei progetti,
vhost/certbot e primo accesso: README -> "Provisioning".

## File statici sempre versionati

Ogni JS/CSS deve arrivare al browser con `?v=filemtime`: CSS e `app.js`
con `View::asset()`, tutti gli altri moduli JS (anche quelli importati da
altri moduli) tramite l'import map di `View::importMap()` nel layout. Un
file nuovo e' coperto da solo se sta in `public/js` (JS) o e' linkato con
`asset()` (CSS). Mai far svuotare la cache al browser.

## Schermate di riepilogo: gerarchia e stile

Nei risultati che non sono liste (report, cruscotto) il dato piu' importante
va in evidenza: `StatBoxComponent` con `'feature' => true` (card scura in
petrolio, una sola per schermata), poi i contatori, poi le ripartizioni
(`BreakdownComponent`: anello, dato chiave in chip, legenda). Stile: niente
bordini colorati sui box, sfumature agli angoli solo nei colori del tema
(petrolio, giallino accent).

## Mini guida ovunque (decisione del 24/11/2026)

Ogni pagina, scheda, sezione e riquadro dove l'utente puo' bloccarsi ha la
sua mini guida: un'icona "i" accanto al titolo che apre in un modale (non
nella pagina, per non occupare spazio) una spiegazione breve: cosa c'e',
come si compila, un consiglio. Il server manda `guide`
nei dati del componente (una frase o `{title, text, steps[], tip}`); il
JS usa `guideParts()` di `public/js/guide.js`. Gia' pronta per: riquadri
dei form (`'guide'` nel `layout` del manifest), titolo del form,
`page-tabs`, `service-header`, le parti del processo (`type-steps`).
Una pagina o un componente nuovo nasce con la sua guida.

Due posti fissi per i testi (25/11/2026), in `config/page-guides.php`:
- `'pages'` / `'entities'`: il pulsante "Come funziona questa pagina" in
  basso a destra, su ogni pagina (`public/js/page-help.js`, `/guida-pagina`,
  `App\Help\PageGuideService`, che sceglie per nome di rotta). Una rotta
  nuova si aggiunge qui; elenchi, schede e "nuovo" delle entita' si
  compongono da `'entities'`.
- `'boxes'`: la "i" dei riquadri dei form, per entita' del manifest e titolo
  del riquadro (`PackageManifest::layout()` la aggiunge se il manifest non
  ne ha una sua). Un riquadro nuovo nasce con la sua riga qui.

Cosa c'e' in un riquadro si dice dentro il riquadro: una riga sotto il
titolo (`'description'` nei box del layout). Niente testi sotto le schede.

Pagine con piu' parti: schede di pagina (`page-tabs`, icona e colore per
scheda). Una scheda puo' mostrare una sezione del form della pagina (un
solo "Salva"), con eventuali altri riquadri sotto. Le parti lunghe partono
chiuse.

Pagine "di lavoro" (es. un'attivita'): prima cosa fare, poi il resto. Se
ci sono piu' sezioni, si usano schede: la prima e' quella del lavoro
(compatta, si fa e si spunta); i dati gia' compilati, che si leggono o si
cambiano solo se serve, stanno in una scheda loro ("Dettagli"). Non si
mette una lista di spunte sopra e i contenuti collegati sotto: ogni voce
porta con se' quello che serve (spunta a sinistra, testo a destra, sotto
risposta o note). Dove si scrive si salva da solo, mostrando "Salvato".
Un solo pulsante principale per pagina. Per costruirla si usa
`AbstractEditEntityTool::tabbedPage()`: intestazione, schede di lavoro e,
in fondo, la scheda con il form ("Dettagli" o un nome adatto). Esempi:
`EditTaskTool` (Da fare / Dettagli) e `EditQuoteTool` (Righe / Dati del
preventivo). Da telefono l'intestazione va in colonna, con i pulsanti
sotto il titolo.

Mai `window.confirm`, `alert` o `prompt`: le conferme sono `confirmModal()`
e i testi da copiare `copyModal()` (in `public/js/modal.js`); le domande con
una riga di testo usano `askText()` (`work-ui.js`).

Ogni fase di un processo si fa dalla sua pagina: la scheda "Da fare" mostra
quello che la fase chiede, secondo il tipo (`EditTaskTool::stepTools()`):
- data chiave: il form della data, che salva e resta sulla pagina
  (`reloadAfterSave`);
- approvazione: "Approva" e "Rimanda indietro";
- attesa del cliente: "Registra una modifica chiesta" e "Ho sollecitato";
- appuntamento: "Fissa l'appuntamento";
- consegna: le cose da consegnare, da spuntare;
- squadra: i ruoli con le persone;
- briefing e raccolta informazioni: le risposte sotto ogni voce;
- fasi del preventivo: il pulsante del preventivo;
- facoltativa: anche "Non la facciamo".

Un tipo di fase nuovo nasce con il suo strumento qui.

Ogni fase di progetto ha anche il suo spazio di lavoro
(`EditTaskTool::workArea()`, `TaskWorkService`):
- l'esito o le note, con un titolo adatto al tipo ("Cosa e' emerso",
  "Cosa ha risposto il cliente"...);
- una nota per ogni voce della checklist ("+ nota");
- i file: sul Drive nella "Cartella dei file" della fase; senza Drive
  restano in `storage/uploads` e partono da soli con il sync.

Tutto si salva da solo e l'assistente lo legge
(`TaskWorkService::projectContext()`). Quello che si fa in una fase non
deve finire fuori dal gestionale.

Elementi "figli" (righe del preventivo, fasi/ruoli/date/consegne del
processo, squadra/date/consegne/modifiche del progetto, referenti, contatti
e diario del cliente) e ogni "nuovo" aperto da una pagina (con i dati
nell'indirizzo, es. "+ Spesa", "+ Fase"): si aprono nel **pannello laterale**
(`public/js/drawer.js`), non in una schermata nuova. Stesso form e stesso
salvataggio della loro pagina; salvato, il pannello si chiude e la pagina
sotto si ricarica. Un figlio nuovo va aggiunto a `DRAWER_PATHS`.

Campi obbligatori: `'base' => true` (colonna NOT NULL), oppure
`'required' => true` quando la colonna ammette NULL ma nel form il dato
serve (es. la data di una data chiave, il ruolo di chi entra in squadra).
Danno l'asterisco nel form e il controllo nel server
(`AbstractEntityController::collectAndValidate()`). Con `'showIf'`
l'obbligo vale solo quando il campo si vede (es. "A" solo se l'email si
manda). I controlli tra piu' campi vanno in `crossFieldErrors()`.

Campi: un campo da solo in una riga si allarga fino in fondo (`packGrid`).
Quindi le combo e i campi corti si mettono in riga con altri (`'width'` nel
manifest) e i riquadri si affiancano dove conviene (`'area' => 'sidebar'`).

## Analisi libera (IA che interroga i dati e compone la pagina)

`src/App/Analysis` + tool `analyze_data`: vedi README -> "Interfaccia a
prompt". Regole: l'IA non scrive mai SQL (solo il linguaggio controllato
tradotto da `AnalysisRepository`); campi sensibili e tabelle di sistema
restano fuori da `DataCatalog`; un mattoncino nuovo va aggiunto sia alle
regole di `AnalysisService::COMPOSE_RULES` sia a `PageComposer`. Durante lo
sviluppo provare con piani/composizioni scritti a mano (nessuna chiamata
all'IA), l'IA vera solo per la verifica finale.

## Pacchetti su un progetto esistente

`bin/install-packages.php` installa pacchetti anche dopo la creazione
(Uno compreso). In produzione le migrazioni arrivano da git gia' in
`applied`: ogni volta va preparato e provato (su un database usa-e-getta)
lo script SQL in `public/tmp/AAAA_MM_GG_HHMM_...sql` con le stesse tabelle e
i permessi (un manifest puo' dichiarare permessi propri con
`'permissions'`, come `planner`). Campi del manifest per i form (`options`, `boolean`, `showIf`,
`help`, `fill`): README -> "Campi del manifest per i form".

## Pagine pubbliche

Le uniche raggiungibili senza login: `/area/:token` (area cliente, il token
e' la chiave: ogni azione deve ricontrollare che la riga sia di quel
cliente, compresa l'accettazione dei preventivi) e le conferme dei
pagamenti (`/pagamenti/...`). Anteprime e file dell'archivio
(`/materiali/:id/anteprima`, `/file`) solo da collegati. Il webhook Stripe
ha `'csrf' => false` nella rotta: lo protegge la firma. Nessun'altra rotta
deve togliere il CSRF. Il token della demo non va mai esportato in
`demo.sql` (override a NULL in `generate-demo.php`).

## Chi vede cosa

Ferie, spese: chi non ha il permesso `.approve` vede e modifica solo le
proprie (controllo nel controller, non solo nel menu). Le approvazioni
(contenuti, preventivi, ferie, spese) sono permessi a parte da "modificare":
un manifest li dichiara in `'permissions'`.

## Dove sono le cose

Vedi `README.md` -> sezione "Architettura" per la mappa completa. In breve:
framework in `src/App/`, moduli applicativi in `module/`, pacchetti
installabili (schema DB) in `packages/`, JS in `public/js/`, CSS in
`public/css/`, viste in `view/` e `module/*/view/`.

## Cosa e' deliberatamente incompleto

Non provare a "riparare" o completare questi punti senza che l'utente lo
chieda esplicitamente - sono deferiti con intenzione, non dimenticati:
la pagina di modifica utente
(`/utenti/:id`), il comportamento reale degli agenti AI (oggi solo un
record, nessuna chiamata a un LLM), i connettori verso sistemi esterni, il
prompt presente su ogni pagina. Lista completa e aggiornata in `README.md`
-> "Cosa manca".
