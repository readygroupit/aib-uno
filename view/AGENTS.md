# AGENTS.md — dev_magnofood (cliente e-commerce)

E-commerce alimentare Magnofood. Leggi prima `/var/www/core/docs/conventions.md` (regole comuni), `/var/www/core/AGENTS.md`, `/var/www/readyecommerce/AGENTS.md`.

## Librerie
core + **readyecommerce** (`PATH_PROJECT` in `public/external.php`). `PROJECT_NAME = "readyecommerce"` (ereditato): config in `config/autoload/readyecommerce.{myconfig,projectconfig}.php`, asset custom in `public/custom/readyecommerce/`.
Tutta la logica e-commerce (catalogo, carrello, checkout, pagamenti, ordini, backend gestionale) è nella libreria: cercala lì prima di scrivere codice qui.

## Cosa c'è nel progetto
| Area | Dove |
|---|---|
| Hook Frontend (menu categorie, prodotti in evidenza, spedizione gratuita, loghi, config pagine 101–106, `loadProductListAction`) | `module/Frontend/src/Frontend/Controller/Abstracts/ModuleProjectAbstractController.php` |
| Controller Frontend (sottoclassi sottili delle abstract di readyecommerce) | `module/Frontend/src/Frontend/Controller/` — home `ReadyecommerceController`, `ProdottoController`, `CategorieController` (+`elenco`), `CartController`, `CheckoutController`, `CustomerAreaController`, `AreaProfessionistiController` (registrazione professionisti) |
| Pagine di contenuto specifiche | `Chisiamo`, `CustomerCare`, `ShowCooking`, `WeddingPlanner`, `CateringEEventi`, `Pagamenti`, `Spedizioni`, `ResiERimborsi` |
| Dashboard backend | `module/Backend/src/Backend/Controller/ReadyecommerceController.php` + `view/backend/readyecommerce/index.phtml` + `public/backend/js/controller/readyecommerceController/indexAction.js` |
| Tema e markup | `module/Frontend/view/{layout,frontend,templates/layout,templates/helpers (52),templates/mail (22)}` |
| JS/CSS | `public/frontend/js/project.js`, `public/frontend/js/controller/<ctrl>Controller[/<action>Action].js`, `public/frontend/css/{base,style}.css` |
| Traduzioni | `language/` (it/en `.po/.mo`) |

## Specifico del progetto (non spostare nelle librerie)
Tema grafico, layout, template helper/mail, testi e pagine di contenuto sopra, `AreaProfessionisti`, loghi e canonical `magnofood.com`.
Candidati a salire in readyecommerce **solo se utili a tutti i clienti e con richiesta esplicita**: parti generiche di `ModuleProjectAbstractController` (albero menu, `loadProductListAction`).

## Eccezioni / attenzione
- `ComingsoonController`, `CookiePolicyController`, `FaqController`, `LogoutController`, `PrivacyPolicyController`, `TerminiECondizioniController` sono copie identiche della libreria: modificare quelli della libreria non ha effetto qui (vince il progetto).
- `ModuleProjectAbstractController::onDispatch` esegue query a ogni pagina: nuove query vanno nei Repository di readyecommerce/core, non qui.
- Script inline nei layout e accesso a `$_SERVER` in alcune view (`layout.phtml`, `checkout/confirm.phtml`): non estendere il pattern.
- `public/wc-products.csv` è un export WooCommerce per l'import: dato, non codice.
