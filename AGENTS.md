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

## Analisi libera (IA che interroga i dati e compone la pagina)

`src/App/Analysis` + tool `analyze_data`: vedi README -> "Interfaccia a
prompt". Regole: l'IA non scrive mai SQL (solo il linguaggio controllato
tradotto da `AnalysisRepository`); campi sensibili e tabelle di sistema
restano fuori da `DataCatalog`; un mattoncino nuovo va aggiunto sia alle
regole di `AnalysisService::COMPOSE_RULES` sia a `PageComposer`. Durante lo
sviluppo provare con piani/composizioni scritti a mano (nessuna chiamata
all'IA), l'IA vera solo per la verifica finale.

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
