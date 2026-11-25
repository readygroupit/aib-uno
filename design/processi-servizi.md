# Processi dei servizi — progetto della fase 1

Stato: fatto il 18/11/2026 (fase 1), riportato in Brains at Work con il processo Brand Identity (`bin/processi.php`).
Differenze dal progetto: gli id dei ruoli/fasi/date al posto delle chiavi testuali; le date chiave "si fissano" con una fase a se' (es. "Fissare il briefing") separata dalla fase che le vive ("Briefing operativo"); `notifications.in_app` esplicito.
Primo caso: Brand Identity di Brains at Work (scheda per Peppe:
https://claude.ai/artifact/1Kgj888qLoU9ZNM5HTPQbq).

## Obiettivo

Un servizio del listino porta con sé il suo processo: fasi, ruoli, date
chiave, approvazioni e deliverable. Aggiunto a un cliente, anche solo
potenziale, il processo genera il progetto. Il progetto ha:
- le attività assegnate per ruolo;
- le persone avvisate;
- le date chiave con le loro azioni (calendario, email al cliente);
- gli avanzamenti automatici legati al preventivo.

Generico, quindi si fa in Uno. Il modello Brand Identity e le persone sono
solo di Brains at Work (`bin/dati-iniziali.php`).

## Scelta di fondo: si estendono le commesse, niente entità nuova

La commessa (`work_orders`) è già il "progetto del cliente": ha tipo,
fasi→attività, slittamento, attesa del cliente. Si estende in tre modi:
1. **parte prima del preventivo**, con stati commerciali;
2. **ruoli e partecipanti** al posto del solo `owner_user_id`;
3. **date chiave, deliverable, modifiche** come tabelle figlie.

Il tipo di commessa diventa il "processo" del servizio. Nell'interfaccia lo
chiamiamo **Progetto** e **Processo**; i nomi tecnici restano.

## Schema

### Modello (pacchetto `work_orders`)

**`work_order_types`**, in più:
- `service_id` INT NULL → `services.id`: il servizio del listino che usa
  questo processo. Sta qui e non in `services`, perché `work_orders` dipende
  da `services` e non il contrario. Da verificare che il manifest gestisca
  un riferimento a un pacchetto facoltativo.

**`work_order_type_roles`** (nuova): i ruoli del processo.
| colonna | tipo | note |
|---|---|---|
| work_order_type_id | INT | |
| role_key | VARCHAR(40) | es. `sales`, `quotes`, `team_leader`, `creative_lead`, `helpers` |
| label | VARCHAR(80) | "Coordinatore creativo" |
| default_user_id | INT NULL | proposta iniziale |
| customer_team_role | VARCHAR(30) NULL | ruolo della squadra cliente da cui proporre la persona (`grafica`...) |
| is_multiple | TINYINT | più persone (gli "aiuti") |
| is_required | TINYINT | `helpers` = no → copre le varianti "solo Sara" / "Sara con aiuti" |
| position | SMALLINT | |

Le tre varianti di squadra della Brand Identity non hanno bisogno di una
tabella: l'esterno è semplicemente un utente con profilo "Collaboratore
esterno" messo nel ruolo `creative_lead`, e "con aiuti" significa che il
ruolo `helpers` ha qualcuno dentro.

**`work_order_type_milestones`** (nuova): le date chiave.
| colonna | tipo | note |
|---|---|---|
| work_order_type_id | INT | |
| milestone_key | VARCHAR(40) | `briefing`, `work_start`, `internal_review`, `client_review` |
| label | VARCHAR(80) | |
| set_by_role_key | VARCHAR(40) | chi la fissa |
| creates_appointment | TINYINT | invito in calendario ai partecipanti coinvolti |
| client_email_subject | VARCHAR(200) NULL | se presente, all'impostazione si mostra l'email da rivedere |
| client_email_body | TEXT NULL | segnaposto: `{cliente}`, `{data}`, `{squadra}`, `{referente}`... |
| after_step_key | VARCHAR(40) NULL | si può fissare solo dopo quella fase (presentazione cliente dopo l'ok interno) |
| position | SMALLINT | |

**`work_order_type_steps`**, in più:
| colonna | tipo | note |
|---|---|---|
| step_key | VARCHAR(40) | riferimento stabile |
| phase | VARCHAR(60) | "Contatto", "Preventivo", "Avvio", "Creatività", "Cliente" |
| kind | VARCHAR(20) | `work`, `approval`, `client_wait`, `milestone`, `system` |
| role_key | VARCHAR(40) | chi la fa (per `approval` chi approva) |
| milestone_key | VARCHAR(40) NULL | la fase coincide con una data chiave |
| completes_on | VARCHAR(40) NULL | evento che la chiude da solo: `quote.drafted`, `quote.approved`, `quote.sent`, `quote.accepted`, `milestone.set` |
| on_reject_step_key | VARCHAR(40) NULL | per `approval`: la fase che si riapre se non approvata (le correzioni) |
| is_repeatable | TINYINT | correzioni interne, modifiche del cliente |
| is_optional | TINYINT | la squadra decide se farla (es. moodboard): si può saltare |

`offset_days` diventa facoltativo: la Brand Identity non ha tempi standard,
e le date vengono dalle date chiave.

**`work_order_type_deliverables`** (nuova): label, description,
`always_included`, position.

### Istanza

**`work_orders`**, in più:
- `stage`: si aggiungono `contatto`, `preventivo` e `non acquisita` prima
  di `da avviare`;
- `quote_id` INT NULL;
- `source_channel` VARCHAR(30) (sito, email, telefono, passaparola, altro);
- `lost_reason` VARCHAR(255) NULL.

**`work_order_members`** (nuova): work_order_id, user_id, role_key,
added_at. Sostituisce nei fatti `owner_user_id`, che resta come "chi l'ha
creata". Essere membri dà visibilità sul cliente: `CustomerScope` va esteso.

**`work_order_milestones`** (nuova): work_order_id, milestone_key, label,
scheduled_at DATETIME NULL, appointment_id NULL, client_email_sent_at NULL,
done_at NULL.

**`work_order_deliverables`** (nuova): work_order_id, label, description,
done_at NULL, done_by NULL. Si creano dal modello all'avvio (quelle
`always_included`) e dal preventivo (le voci vendute).

**`work_order_change_requests`** (nuova): work_order_id, requested_at,
description, assigned_user_id, is_extra, quote_id NULL, stage (`aperta`,
`fatta`, `annullata`).

**`tasks`**, in più: `role_key`, `iteration` (giro di correzione o modifica).
Chi fa l'attività è il membro del ruolo; se il ruolo è vuoto l'attività
resta senza assegnatario e l'assistente lo segnala.

### Notifiche (nuovo pacchetto `notifications`, generico)

**`notifications`**: user_id, type, title, body, url, entity_type,
entity_id, created_at, read_at NULL, emailed_at NULL.

**`notification_preferences`**: user_id, type, in_app TINYINT, email
TINYINT. Se manca la riga vale il default del tipo.

Tipi della fase 1: `project_member_added`, `task_assigned`,
`approval_requested`, `milestone_set`, `client_waiting`. Nella fase 2 si
aggiungono `assistant_alert` e `mention`.

Campanella nell'intestazione con il numero dei non letti, più una pagina
"Notifiche". Le email partono da un job ogni 5 minuti
(`send_notification_emails`), raggruppate per persona.

## Flusso

1. **Avvio dal cliente.** Nella scheda cliente, "Avvia servizio" →
   scegli il servizio → si crea la commessa dal suo processo, in stato
   `contatto` (o `preventivo` se si parte da lì). I ruoli si propongono da
   `default_user_id` e dalla squadra del cliente; si creano le attività;
   partono le notifiche ai membri.
2. **Preventivo.** "Prepara preventivo" dalla commessa crea il preventivo
   con `work_order_id`. In `QuoteService` gli eventi bozza / approvato /
   inviato / accettato chiudono le fasi con `completes_on`. `accept()`, se
   il preventivo ha una commessa, collega a quella il `customer_service`
   creato e la passa a `da avviare`; i deliverable venduti diventano
   checklist. Rifiutato → `non acquisita` con il motivo.
3. **Squadra.** La fase "Comporre la squadra" si chiude salvando i membri.
   Le attività dei ruoli appena coperti si assegnano e si notificano.
4. **Date chiave.** Fissare una data apre una finestra con la data e, se il
   modello ha l'email per il cliente, l'email già compilata e modificabile.
   Conferma → appuntamento con invito (Calendar), email al cliente
   (`MailService`, rispettando la modalità prova), fase chiusa, notifica.
5. **Approvazioni.** Una fase `approval` è un'attività dell'approvatore con
   due pulsanti: Approva (chiude e va avanti) e Rimanda (riapre
   `on_reject_step_key` con `iteration + 1` e una nota).
6. **Modifiche del cliente.** Dalla commessa, "Modifica richiesta":
   compresa o extra; se extra, eventuale preventivo aggiuntivo.
7. **Approvazione del cliente.** Il cliente approva per email e non entra
   nel gestionale (confermato il 18/11/2026). Le fasi `client_wait` e
   l'approvazione finale le segna il Coordinatore: data, come è arrivata,
   eventuale email allegata.
8. **Fine.** Ultima fase fatta e deliverable spuntati → `consegnata`.

Fase 1 senza eventi generici: chiamate dirette tra servizi (come oggi
`afterCreate`). Il dispatcher con `config/events.php` arriva con la fase 2
(regole dell'assistente) e la fase 4 (Drive).

## Interfaccia

- **Progetto** (pagina della commessa): iter per fasi con lo stato di ogni
  attività e il suo assegnatario, le 4 date chiave in alto, squadra,
  deliverable, modifiche, preventivo collegato.
- **Processo** (pagina del tipo di commessa): editor di fasi, ruoli, date
  chiave, deliverable ed email; collegamento al servizio del listino.
- Scheda cliente: "Avvia servizio" e l'elenco dei progetti.
- Prompt: `start_service` (avvia un servizio per un cliente) e
  `set_milestone` (fissa una data chiave), con conferma.

## Da tenere presente

- `MailService` oggi usa `@mail()`, senza SMTP vero: per le email ai
  clienti e ai colleghi serve un invio affidabile (SMTP o API). È un
  prerequisito.
- Brains at Work non ha il pacchetto `message_templates`: per questo le
  email delle date chiave stanno nella riga della data chiave.
- Migrazione dei dati esistenti: le commesse attuali hanno
  `owner_user_id` → diventa membro con ruolo `owner`; i tipi esistenti
  restano validi (campi nuovi facoltativi).
- Per la produzione di Brains at Work: migrazione in `db/migrations/pending`
  e script `public/tmp/AAAA_MM_GG_HHMM_processi_servizi.sql`.

## Fase 2: l'assistente (fatta il 18/11/2026)

Pacchetto `assistant`, vedi README di Uno -> "Assistente". Scelte:
- la giornata si calcola sul momento dai dati (niente coda da tenere
  allineata); solo cio' che e' gia' stato detto o proposto finisce in
  `assistant_actions`, per non ripetersi;
- il "cosa" e il "quando" vengono dalle regole (deterministiche), il
  "come" (testo dei solleciti, idea per il portfolio) dall'IA se c'e';
- autonomia per regola: solo avviso / chiede conferma / in automatico;
  oggi solo i solleciti al cliente hanno un'azione, il resto e' avviso.

## Fase 3: conoscenza del cliente e piani dal prompt (fatta il 18/11/2026)

Vedi README di Uno -> "Conoscenza del cliente e piani dal prompt". Scelte:
- profilo e diario in un pacchetto a parte (`client_knowledge`): testi
  lunghi letti solo quando servono, non a ogni elenco clienti;
- la roadmap e' una vista del diario (`on_roadmap`), non un'entita' nuova;
- il prompt "a piu' passaggi" e' un solo giro dell'IA che scrive il piano
  intero come argomento di `plan_actions`; i passi si eseguono solo dopo la
  conferma, dal piano risolto lato server.

## Fase 4: Documenti sul Drive (fatta il 18/11/2026)

Vedi README di Uno -> "Documenti". Scelte (da confermare con l'agenzia):
- cancellazione: dal gestionale si mette nel cestino del Drive, mai
  cancellato per sempre; una cosa nel cestino o cancellata sul Drive qui
  resta come traccia (`trashed_at`) e torna se viene ripristinata;
- Drive e' dell'agenzia: le scritture sono reali anche in modalita' prova
  (come il calendario);
- nessun file si scarica nel gestionale: solo le informazioni, il file si
  apre sul Drive.

## Fase 5: qualificazione e follow-up (fatta il 18/11/2026, senza i nuovi servizi)

Vedi README di Uno -> "Qualificazione, follow-up e periodi forti". Valori di
partenza (da confermare con l'agenzia, tutti modificabili): caldo 3 giorni,
tiepido 10, freddo 30; cliente non sentito 30 giorni (chiave 15); 11
periodi forti con il loro anticipo. readySite e gli altri servizi restano
da definire con l'iter, come la Brand Identity.

## Fuori da questa fase

Assistente proattivo e autonomia configurabile (fase 2); briefing guidato,
profilo del cliente e prompt a più passaggi (fase 3); Drive bidirezionale
(fase 4); qualificazione e follow-up (fase 5).
