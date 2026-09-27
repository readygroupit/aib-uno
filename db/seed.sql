-- Dati minimi di bootstrap: un profilo amministratore, un utente admin,
-- un esempio di permesso raggruppato e un esempio di config con dipendenza
-- (switch "Klarna abilitato" -> campo "api key" visibile solo se acceso).
--
-- Utente di test: username "admin" / password "admin123".

SET NAMES utf8mb4;

INSERT INTO profiles (id, name, description, status, created_at, created_by)
VALUES (1, 'Amministratore', 'Accesso completo al sistema', 1, NOW(), NULL);

INSERT INTO users (id, profile_id, username, email, password_hash, first_name, last_name, status, created_at, created_by)
VALUES (1, 1, 'admin', 'admin@example.com', '$2y$12$SkK1Upu9ogquYYPPXAtVWuZelW3ZOOo72CfLX/n27u5Z7FEf6X4VK', 'Admin', 'Sistema', 1, NOW(), NULL);

-- da qui in poi created_by = 1 (l'utente admin appena creato)

-- i "code" sono identificativi di codice: sempre in inglese, anche
-- quando il progetto/UI e' in italiano (name/description restano
-- italiani, sono quelli mostrati davvero all'utente)
INSERT INTO permission_groups (id, parent_id, code, name, description, sort_order, status, created_at, created_by)
VALUES
    (1, NULL, 'administration', 'Amministrazione', NULL, 0, 1, NOW(), 1),
    (2, 1, 'users', 'Utenti', 'Gestione utenti e profili', 0, 1, NOW(), 1),
    (3, 1, 'leads', 'Contatti', 'Gestione contatti/lead', 0, 1, NOW(), 1),
    (4, 1, 'customers', 'Clienti', 'Gestione clienti', 0, 1, NOW(), 1),
    (5, 1, 'tasks', 'Attivita', 'Gestione attivita/promemoria', 0, 1, NOW(), 1),
    (6, 1, 'appointments', 'Appuntamenti', 'Gestione appuntamenti', 0, 1, NOW(), 1),
    (7, 1, 'catalog', 'Catalogo', 'Gestione catalogo prodotti', 0, 1, NOW(), 1),
    (8, 1, 'services', 'Servizi', 'Gestione servizi', 0, 1, NOW(), 1),
    (9, 1, 'interventions', 'Interventi', 'Gestione interventi tecnici', 0, 1, NOW(), 1),
    (10, 1, 'blog', 'Blog', 'Gestione articoli blog', 0, 1, NOW(), 1),
    (11, 1, 'campaigns', 'Campagne marketing', 'Gestione campagne marketing', 0, 1, NOW(), 1),
    (12, 1, 'invoices', 'Fatture', 'Gestione fatture', 0, 1, NOW(), 1),
    (13, 1, 'cases', 'Pratiche', 'Gestione pratiche/fascicoli', 0, 1, NOW(), 1);

-- users/leads restano un permesso unico "X.manage" (non ancora
-- retrofittati su AbstractEntityController, vedi memoria progetto). Da
-- customers in poi sono invece 4 permessi granulari per dominio
-- (view/create/edit/delete in 'category') invece di un unico "X.manage":
-- non tutti hanno gli stessi poteri su una CRUD, chi vede non
-- necessariamente scrive, chi crea non necessariamente elimina.
INSERT INTO permissions (id, permission_group_id, code, name, description, sort_order, status, created_at, created_by, category)
VALUES
    (1, 2, 'users.manage', 'Gestione utenti', 'Creare, modificare, disabilitare utenti', 0, 1, NOW(), 1, NULL),
    (2, 3, 'leads.manage', 'Gestione contatti', 'Creare, modificare, eliminare contatti', 0, 1, NOW(), 1, NULL),
    (3, 4, 'customers.view', 'Visualizzare clienti', 'Vedere l''elenco e aprire il dettaglio.', 0, 1, NOW(), 1, 'view'),
    (4, 4, 'customers.create', 'Creare clienti', 'Aggiungere nuovi elementi.', 0, 1, NOW(), 1, 'create'),
    (5, 4, 'customers.edit', 'Modificare clienti', 'Modificare elementi esistenti.', 0, 1, NOW(), 1, 'edit'),
    (6, 4, 'customers.delete', 'Eliminare clienti', 'Eliminare elementi esistenti.', 0, 1, NOW(), 1, 'delete'),
    (7, 5, 'tasks.view', 'Visualizzare attivita', 'Vedere l''elenco e aprire il dettaglio.', 0, 1, NOW(), 1, 'view'),
    (8, 5, 'tasks.create', 'Creare attivita', 'Aggiungere nuovi elementi.', 0, 1, NOW(), 1, 'create'),
    (9, 5, 'tasks.edit', 'Modificare attivita', 'Modificare elementi esistenti.', 0, 1, NOW(), 1, 'edit'),
    (10, 5, 'tasks.delete', 'Eliminare attivita', 'Eliminare elementi esistenti.', 0, 1, NOW(), 1, 'delete'),
    (11, 6, 'appointments.view', 'Visualizzare appuntamenti', 'Vedere l''elenco e aprire il dettaglio.', 0, 1, NOW(), 1, 'view'),
    (12, 6, 'appointments.create', 'Creare appuntamenti', 'Aggiungere nuovi elementi.', 0, 1, NOW(), 1, 'create'),
    (13, 6, 'appointments.edit', 'Modificare appuntamenti', 'Modificare elementi esistenti.', 0, 1, NOW(), 1, 'edit'),
    (14, 6, 'appointments.delete', 'Eliminare appuntamenti', 'Eliminare elementi esistenti.', 0, 1, NOW(), 1, 'delete'),
    (15, 7, 'catalog.view', 'Visualizzare prodotti', 'Vedere l''elenco e aprire il dettaglio.', 0, 1, NOW(), 1, 'view'),
    (16, 7, 'catalog.create', 'Creare prodotti', 'Aggiungere nuovi elementi.', 0, 1, NOW(), 1, 'create'),
    (17, 7, 'catalog.edit', 'Modificare prodotti', 'Modificare elementi esistenti.', 0, 1, NOW(), 1, 'edit'),
    (18, 7, 'catalog.delete', 'Eliminare prodotti', 'Eliminare elementi esistenti.', 0, 1, NOW(), 1, 'delete'),
    (19, 8, 'services.view', 'Visualizzare servizi', 'Vedere l''elenco e aprire il dettaglio.', 0, 1, NOW(), 1, 'view'),
    (20, 8, 'services.create', 'Creare servizi', 'Aggiungere nuovi elementi.', 0, 1, NOW(), 1, 'create'),
    (21, 8, 'services.edit', 'Modificare servizi', 'Modificare elementi esistenti.', 0, 1, NOW(), 1, 'edit'),
    (22, 8, 'services.delete', 'Eliminare servizi', 'Eliminare elementi esistenti.', 0, 1, NOW(), 1, 'delete'),
    (23, 9, 'interventions.view', 'Visualizzare interventi', 'Vedere l''elenco e aprire il dettaglio.', 0, 1, NOW(), 1, 'view'),
    (24, 9, 'interventions.create', 'Creare interventi', 'Aggiungere nuovi elementi.', 0, 1, NOW(), 1, 'create'),
    (25, 9, 'interventions.edit', 'Modificare interventi', 'Modificare elementi esistenti.', 0, 1, NOW(), 1, 'edit'),
    (26, 9, 'interventions.delete', 'Eliminare interventi', 'Eliminare elementi esistenti.', 0, 1, NOW(), 1, 'delete'),
    (27, 10, 'blog.view', 'Visualizzare articoli', 'Vedere l''elenco e aprire il dettaglio.', 0, 1, NOW(), 1, 'view'),
    (28, 10, 'blog.create', 'Creare articoli', 'Aggiungere nuovi elementi.', 0, 1, NOW(), 1, 'create'),
    (29, 10, 'blog.edit', 'Modificare articoli', 'Modificare elementi esistenti.', 0, 1, NOW(), 1, 'edit'),
    (30, 10, 'blog.delete', 'Eliminare articoli', 'Eliminare elementi esistenti.', 0, 1, NOW(), 1, 'delete'),
    (31, 11, 'campaigns.view', 'Visualizzare campagne', 'Vedere l''elenco e aprire il dettaglio.', 0, 1, NOW(), 1, 'view'),
    (32, 11, 'campaigns.create', 'Creare campagne', 'Aggiungere nuovi elementi.', 0, 1, NOW(), 1, 'create'),
    (33, 11, 'campaigns.edit', 'Modificare campagne', 'Modificare elementi esistenti.', 0, 1, NOW(), 1, 'edit'),
    (34, 11, 'campaigns.delete', 'Eliminare campagne', 'Eliminare elementi esistenti.', 0, 1, NOW(), 1, 'delete'),
    (35, 12, 'invoices.view', 'Visualizzare fatture', 'Vedere l''elenco e aprire il dettaglio.', 0, 1, NOW(), 1, 'view'),
    (36, 12, 'invoices.create', 'Creare fatture', 'Aggiungere nuovi elementi.', 0, 1, NOW(), 1, 'create'),
    (37, 12, 'invoices.edit', 'Modificare fatture', 'Modificare elementi esistenti.', 0, 1, NOW(), 1, 'edit'),
    (38, 12, 'invoices.delete', 'Eliminare fatture', 'Eliminare elementi esistenti.', 0, 1, NOW(), 1, 'delete'),
    (39, 13, 'cases.view', 'Visualizzare pratiche', 'Vedere l''elenco e aprire il dettaglio.', 0, 1, NOW(), 1, 'view'),
    (40, 13, 'cases.create', 'Creare pratiche', 'Aggiungere nuovi elementi.', 0, 1, NOW(), 1, 'create'),
    (41, 13, 'cases.edit', 'Modificare pratiche', 'Modificare elementi esistenti.', 0, 1, NOW(), 1, 'edit'),
    (42, 13, 'cases.delete', 'Eliminare pratiche', 'Eliminare elementi esistenti.', 0, 1, NOW(), 1, 'delete');

INSERT INTO profile_permissions (profile_id, permission_id, status, created_at, created_by)
VALUES
    (1, 1, 1, NOW(), 1),
    (1, 2, 1, NOW(), 1),
    (1, 3, 1, NOW(), 1),
    (1, 4, 1, NOW(), 1),
    (1, 5, 1, NOW(), 1),
    (1, 6, 1, NOW(), 1),
    (1, 7, 1, NOW(), 1),
    (1, 8, 1, NOW(), 1),
    (1, 9, 1, NOW(), 1),
    (1, 10, 1, NOW(), 1),
    (1, 11, 1, NOW(), 1),
    (1, 12, 1, NOW(), 1),
    (1, 13, 1, NOW(), 1),
    (1, 14, 1, NOW(), 1),
    (1, 15, 1, NOW(), 1),
    (1, 16, 1, NOW(), 1),
    (1, 17, 1, NOW(), 1),
    (1, 18, 1, NOW(), 1),
    (1, 19, 1, NOW(), 1),
    (1, 20, 1, NOW(), 1),
    (1, 21, 1, NOW(), 1),
    (1, 22, 1, NOW(), 1),
    (1, 23, 1, NOW(), 1),
    (1, 24, 1, NOW(), 1),
    (1, 25, 1, NOW(), 1),
    (1, 26, 1, NOW(), 1),
    (1, 27, 1, NOW(), 1),
    (1, 28, 1, NOW(), 1),
    (1, 29, 1, NOW(), 1),
    (1, 30, 1, NOW(), 1),
    (1, 31, 1, NOW(), 1),
    (1, 32, 1, NOW(), 1),
    (1, 33, 1, NOW(), 1),
    (1, 34, 1, NOW(), 1),
    (1, 35, 1, NOW(), 1),
    (1, 36, 1, NOW(), 1),
    (1, 37, 1, NOW(), 1),
    (1, 38, 1, NOW(), 1),
    (1, 39, 1, NOW(), 1),
    (1, 40, 1, NOW(), 1),
    (1, 41, 1, NOW(), 1),
    (1, 42, 1, NOW(), 1);

INSERT INTO config_groups (id, parent_id, code, name, description, sort_order, status, created_at, created_by)
VALUES
    (1, NULL, 'payments', 'Pagamenti', NULL, 0, 1, NOW(), 1),
    (2, 1, 'klarna', 'Klarna', 'Integrazione Klarna', 0, 1, NOW(), 1);

INSERT INTO configs (id, config_group_id, `key`, name, description, type, value, sort_order, status, created_at, created_by)
VALUES (1, 2, 'payments.klarna.enabled', 'Klarna abilitato', 'Attiva il pagamento con Klarna', 4, '0', 0, 1, NOW(), 1);

INSERT INTO configs (id, config_group_id, `key`, name, description, type, value, depends_on_config_id, depends_on_value, sort_order, status, created_at, created_by)
VALUES (2, 2, 'payments.klarna.api_key', 'API key Klarna', 'Chiave API fornita da Klarna', 1, NULL, 1, '1', 1, 1, NOW(), 1);
