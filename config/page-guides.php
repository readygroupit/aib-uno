<?php

declare(strict_types=1);

/**
 * Le guide delle pagine ("Come funziona questa pagina", vedi
 * App\Help\PageGuideService). Una pagina nuova si aggiunge qui.
 *
 * - 'pages': per nome di rotta (config/routes.php), la guida completa.
 * - 'entities': per gli elenchi e le schede generati da entityRoutes():
 *   il nome dell'elemento, il plurale, cosa contiene ('what') e, se serve,
 *   un consiglio ('tip') o un testo apposta per il nuovo ('new') o la
 *   scheda ('edit'). Elenco, scheda e nuovo si compongono da qui.
 */
return [
    'pages' => [
        'index' => [
            'title' => 'La home',
            'text' => "Al centro la ricerca: scrivi cosa vuoi fare o trovare, anche a parole tue (es. \"progetti in ritardo\", \"nuovo cliente Rossi\"). Sotto, i tuoi colleghi digitali con le cose piu' urgenti per te.",
            'steps' => [
                'Nora segue i progetti, Teo i clienti, Ada preventivi e contatti, Leo i periodi forti e le idee.',
                'Ogni scheda dice a chi tocca; un clic porta dove si fa.',
                '"Tutti i tuoi agenti" apre la pagina completa, con quello che hanno fatto per te.',
            ],
            'tip' => 'Le cose arrivano da sole: l\'assistente controlla ogni ora fasi, date, clienti da risentire.',
        ],
        'assistant' => [
            'title' => 'I tuoi agenti',
            'text' => "Ogni agente segue una parte del lavoro e ti dice cosa fare, prima che tu lo chieda. Sceglilo in alto: ti saluta con la cosa piu' urgente, poi le sue cose divise per urgenza.",
            'steps' => [
                '"Cosa ho fatto per te": avvisi mandati e messaggi preparati.',
                '"Come lavoro": le regole che segue (chi ha il permesso le cambia con "Cambia").',
                'Alcune cose hanno il messaggio gia\' pronto: "Rileggi e manda".',
            ],
        ],
        'agents' => [
            'title' => 'I tuoi agenti',
            'text' => "Ogni agente segue una parte del lavoro e ti dice cosa fare, prima che tu lo chieda.",
            'steps' => ['Scegli un agente in alto.', 'Sotto: le sue cose per urgenza, cosa ha fatto per te, come lavora.'],
        ],
        'assistant-rules' => [
            'title' => "Come lavora l'assistente",
            'text' => "Le regole degli agenti: cosa controllano, quanti giorni prima avvisano e cosa fanno da soli.",
            'steps' => [
                'Accendi o spegni una regola.',
                'Autonomia: "solo avviso", "prepara e chiede conferma" o "fa da solo" (le cose delicate restano con conferma).',
                'I giorni: es. "risentire un cliente dopo 21 giorni di silenzio".',
            ],
            'tip' => 'Vale per tutta l\'agenzia: cambiale con calma.',
        ],
        'notifications' => [
            'title' => 'Avvisi',
            'text' => "Tutto quello che ti riguarda: una fase che tocca a te, una data fissata, un'approvazione da dare, chi ti ha menzionato.",
            'steps' => ['Clicca un avviso per aprire la cosa a cui si riferisce.', '"Tutti letti" li segna come letti.', '"Preferenze": quali ricevere, nel gestionale o anche per email.'],
        ],
        'notifications-preferences' => [
            'title' => 'Quali avvisi ricevere',
            'text' => "Per ogni tipo di avviso scegli se riceverlo nel gestionale (campanella) e/o per email.",
            'tip' => 'Le email partono ogni pochi minuti, raccolte.',
        ],
        'work_orders' => [
            'title' => 'Progetti',
            'text' => "Tutti i progetti, per stato: in trattativa, in corso, in attesa del cliente, consegnati. Ognuno mostra a che punto e' e la prossima fase.",
            'steps' => ['Clicca un progetto per aprirlo.', '"+ Nuovo" ne crea uno: scegli il servizio e le fasi arrivano da sole.', 'I progetti fermi o in ritardo sono in evidenza.'],
        ],
        'quotes' => [
            'title' => 'Preventivi',
            'text' => "I preventivi per stato: bozza, da approvare, approvati, inviati, accettati. In alto quanto vale quello in corso.",
            'steps' => ['Clicca un preventivo per aprirlo.', 'Un preventivo si prepara di solito dal progetto ("Prepara il preventivo"): nasce gia\' collegato.', 'Approva, segna inviato e accettato dalla sua pagina: le fasi del progetto si chiudono da sole.'],
        ],
        'appointments' => [
            'title' => 'Calendario',
            'text' => "Gli appuntamenti della settimana: incontri, shooting, presentazioni. Si sincronizzano con Google Calendar.",
            'steps' => ['Clicca un appuntamento per aprirlo (si apre nel pannello).', '"+ Appuntamento" ne fissa uno.', 'Le date chiave dei progetti (briefing, presentazioni) creano da sole l\'appuntamento.'],
        ],
        'tasks' => [
            'title' => "Attivita'",
            'text' => "Le cose da fare: le tue e quelle della squadra, comprese le fasi dei progetti.",
            'steps' => ['Spunta un\'attivita\' semplice quando e\' fatta; la freccia apre le fasi con qualcosa da inserire.', 'Clicca il titolo per aprirla.', '"+ Nuovo" aggiunge un\'attivita\'.'],
        ],
        'tasks-edit' => [
            'title' => "L'attivita'",
            'text' => "In alto cosa, per quale progetto, quando e chi la fa (si cambia dal menu). \"Da fare\" e' dove si lavora; \"Dettagli\" contiene il form.",
            'steps' => [
                'Spunta le voci; sotto ogni voce puoi aggiungere una nota (+ nota).',
                'Scrivi l\'esito o le note: si salvano da sole.',
                'Carica i file: vanno su Drive, nella cartella della fase.',
                'Alcune fasi chiedono qualcosa (una data, un\'approvazione, le risposte del cliente): si fa qui, e la fase si chiude.',
            ],
            'tip' => 'L\'icona "i" accanto a "Da fare" spiega cosa chiede questa fase.',
        ],
        'work_orders-edit' => [
            'title' => 'Il progetto',
            'text' => "In alto per chi e', a che punto e', le azioni del momento e la cosa da fare adesso. Sotto, le schede.",
            'steps' => [
                'Fasi: le cose da fare in ordine ("Nascondi le fatte" lascia solo quelle da fare).',
                'Squadra e date: chi ci lavora, le date chiave, gli appuntamenti.',
                'Consegne: cosa riceve il cliente e le modifiche che chiede.',
                'Cliente e file: diario, roadmap, documenti.',
            ],
            'tip' => 'Fase, spesa, persona, data: si aggiungono nel pannello laterale, senza lasciare la pagina.',
        ],
        'quotes-edit' => [
            'title' => 'Il preventivo',
            'text' => "In alto stato, cliente, totale e le azioni (approva, segna inviato, stampa). \"Prima di mandarlo manca\" dice cosa completare, con i pulsanti per farlo.",
            'steps' => ['Righe: le voci e i totali, piu\' cosa ha chiesto il cliente.', 'Dati del preventivo: scadenza, pagamento, sconto, testi.', '"Stampa / PDF" apre il documento da mandare.'],
        ],
        'customers-edit' => [
            'title' => 'Il cliente',
            'text' => "In alto chi e' e com'e' (caldo, freddo, da seguire...). Sotto, le schede.",
            'steps' => [
                'Lavori: progetti e appuntamenti.',
                "Rapporto: com'e' il cliente, temperatura, roadmap delle idee, diario.",
                'Persone: chi lo segue da noi e i suoi referenti.',
                'Dati: anagrafica, fatturazione, pagamento.',
            ],
            'tip' => 'Un\'idea della roadmap diventa un progetto con "Diventa un progetto".',
        ],
        'services-edit' => [
            'title' => 'Il servizio',
            'text' => "Cosa vende l'agenzia e come si lavora per farlo. Il processo e' la ricetta: ogni progetto di questo servizio nasce con le sue fasi.",
            'steps' => ['Il servizio: nome, descrizione; sotto le cartelle sul Drive e la durata.', 'Vendita: prezzo indicativo, costi, fatturazione.', 'Processo di lavoro: ruoli, date chiave, fasi, consegne.'],
        ],
        'installments' => [
            'title' => 'Scadenzario',
            'text' => "I pagamenti in arrivo e quelli scaduti, cliente per cliente.",
            'steps' => ['Segna un pagamento come incassato.', 'Manda il sollecito o il link per pagare.', 'Dal pagamento si fa la fattura.'],
        ],
        'client_requests' => [
            'title' => 'Richieste dei clienti',
            'text' => "Le richieste arrivate (email, telefono, WhatsApp): da prendere in carico, in lavorazione, fatte.",
            'steps' => ['"Prendo io" la assegna a te.', 'Una richiesta extra si fattura a parte.', 'Le email possono arrivare qui da sole.'],
        ],
        'editorial_posts' => [
            'title' => 'Piano editoriale',
            'text' => "I contenuti social dei clienti, per giorno e per stato: da preparare, da approvare, programmati, pubblicati.",
            'steps' => ['Clicca un contenuto per scriverlo o cambiarlo.', 'Il cliente approva dal suo link.', 'I contenuti approvati si pubblicano da soli.'],
        ],
        'expenses' => [
            'title' => 'Spese',
            'text' => "Le spese della squadra: da approvare, approvate, rimborsate. Una spesa di un progetto finisce nei suoi costi.",
            'steps' => ['"+ Spesa" ne aggiunge una (anche dal progetto).', 'Chi approva le decide da qui.'],
        ],
        'leave_requests' => [
            'title' => 'Ferie e permessi',
            'text' => "Le richieste di ferie e permessi della squadra e chi e' assente.",
            'steps' => ['"+ Nuovo" fa una richiesta.', 'Chi approva decide da qui; il calendario lo sa.'],
        ],
        'documents' => [
            'title' => 'Documenti',
            'text' => "Le cartelle e i file del Drive dell'agenzia, visti dal gestionale: clienti, progetti, file caricati dalle fasi.",
            'steps' => ['Apri una cartella; carica un file o crea una cartella (vanno sul Drive).', 'La ricerca legge nomi e descrizioni: scrivi cosa contiene un file per ritrovarlo.', 'Le novita\' del Drive arrivano qui da sole ogni 15 minuti.'],
        ],
        'documents-folder' => [
            'title' => 'Una cartella',
            'text' => "I file e le sottocartelle, come sul Drive. Quello che fai qui (carica, rinomina, cestino) succede anche sul Drive.",
            'tip' => 'Il cestino e\' quello del Drive: si recupera per 30 giorni.',
        ],
        'setup' => [
            'title' => 'Configurazione',
            'text' => "I collegamenti esterni (Google: Calendar e Drive, social, pagamenti), il logo, la modalita' prova.",
            'steps' => ['"Cosa manca ancora": i passaggi per partire.', 'Collega Google per calendario e Drive.', 'Modalita\' prova: le email ai clienti non partono davvero; spegnila quando sei pronto.'],
        ],
        'report' => [
            'title' => 'Report',
            'text' => "I numeri dell'agenzia: lavoro, incassi, clienti. Puoi chiedere un'analisi a parole al prompt.",
        ],
        'planner-today' => [
            'title' => 'La tua giornata',
            'text' => "Le cose di oggi in ordine: fasi, attivita', appuntamenti.",
            'steps' => ['Spunta quello che fai.', '"Rimanda" sposta a un altro giorno.'],
        ],
        'planner-team' => [
            'title' => 'La squadra',
            'text' => "Chi fa cosa nei prossimi giorni, per vedere chi e' carico e chi no.",
            'steps' => ['Passa un\'attivita\' a un altro con "Riassegna".'],
        ],
        'worklog-week' => [
            'title' => 'Ore della settimana',
            'text' => "Le ore lavorate, giorno per giorno e progetto per progetto.",
            'steps' => ['Segna le ore di oggi.', 'Il modello settimanale le riempie dagli orari.'],
        ],
        'worklog-insights' => [
            'title' => 'Dove va il tempo',
            'text' => "Quante ore vanno su ogni cliente e progetto, rispetto al previsto.",
        ],
        'client-health' => [
            'title' => 'Temperatura dei clienti',
            'text' => "Come va il rapporto con ogni cliente, dai contatti e dai servizi attivi: chi e' da risentire subito.",
            'steps' => ['Clicca un cliente per aprirlo.', "Dopo una telefonata, \"Com'e' andata\" la registra."],
        ],
        'labour-cost' => [
            'title' => 'Costo del lavoro',
            'text' => "Quanto costa un'ora di lavoro e quanto e' costato ogni progetto, per fare i prezzi giusti.",
        ],
        'import' => [
            'title' => 'Importazione',
            'text' => "Porta nel gestionale clienti, contatti e altri dati da un foglio Google o da un file Excel.",
            'steps' => ['Scegli il foglio o il file.', 'Controlla come le colonne diventano campi.', 'Importa.'],
        ],
        'profile' => [
            'title' => 'Il mio profilo',
            'text' => "I tuoi dati, la foto (la vedono i colleghi) e la password.",
        ],
        'leads-duplicates' => [
            'title' => 'Contatti doppi',
            'text' => "Contatti che sembrano la stessa persona: uniscili per non scrivere due volte.",
        ],
        'assistant-action' => [
            'title' => "Un messaggio preparato dall'assistente",
            'text' => "L'assistente ha preparato un messaggio (es. un sollecito al cliente): rileggilo, cambialo se serve, poi mandalo o scartalo.",
            'steps' => ['Il testo si puo\' modificare prima dell\'invio.', '"Manda" lo spedisce; "Non serve" lo scarta.'],
            'tip' => 'In modalita\' prova le email ai clienti non partono davvero.',
        ],
        'users-invite' => [
            'title' => 'Invita un collega',
            'text' => "Il collega riceve un'email per scegliere la password. Il profilo decide cosa puo' vedere e fare.",
        ],
    ],

    'entities' => [
        'customers' => ['name' => 'cliente', 'plural' => 'Clienti', 'what' => "I clienti dell'agenzia, con lo stato (potenziale, in trattativa, cliente), la temperatura e chi li segue.", 'tip' => "Caldo o freddo, importante o difficile: si segna nella scheda, in Rapporto.", 'new' => "Ragione sociale (o nome e cognome per un privato) ed email. Il resto si completa dopo."],
        'leads' => ['name' => 'contatto', 'plural' => 'Contatti', 'what' => "Le persone che si sono fatte vive (sito, telefono, eventi) e non sono ancora clienti.", 'tip' => 'Un contatto interessato diventa cliente con "Converti in cliente".'],
        'customer_contacts' => ['name' => 'referente', 'plural' => 'Referenti', 'what' => "Le persone del cliente con cui si parla: ruolo, email, telefono, chi decide."],
        'customer_team_members' => ['name' => 'persona della squadra del cliente', 'plural' => 'Squadra del cliente', 'what' => "Chi segue il cliente in agenzia e per cosa (grafica, social...): contenuti e fasi si assegnano da soli a queste persone."],
        'customer_touchpoints' => ['name' => 'contatto con il cliente', 'plural' => 'Contatti con i clienti', 'what' => "Telefonate, incontri, email: com'e' andata e cosa fare dopo. Tengono viva la temperatura del cliente."],
        'customer_notes' => ['name' => 'nota del diario', 'plural' => 'Diario', 'what' => "Idee, decisioni, preferenze del cliente. Un'idea \"nella roadmap\" si segue fino a diventare un progetto.", 'tip' => 'Menziona un collega per avvisarlo.'],
        'customer_profiles' => ['name' => 'profilo del cliente', 'plural' => 'Profili dei clienti', 'what' => "Storia, attivita', mission, stile, pubblico: quello che l'assistente usa per scrivere e proporre. Si riempie anche dal briefing."],
        'customer_services' => ['name' => 'servizio del cliente', 'plural' => 'Servizi dei clienti', 'what' => "I servizi attivi di ogni cliente, con prezzo, rinnovo e pagamenti.", 'tip' => 'I servizi ricorrenti si rinnovano da soli e creano le rate.'],
        'installments' => ['name' => 'pagamento', 'plural' => 'Pagamenti', 'what' => "Le rate e gli incassi dei clienti."],
        'quotes' => ['name' => 'preventivo', 'plural' => 'Preventivi', 'what' => "Le proposte economiche ai clienti, dalla bozza all'accettazione."],
        'quote_lines' => ['name' => 'riga del preventivo', 'plural' => 'Righe del preventivo', 'what' => "Una voce del preventivo: dal listino (il prezzo parte da quello indicativo) o scritta a mano.", 'tip' => 'Il prezzo vero si decide qui, cliente per cliente.'],
        'invoices' => ['name' => 'fattura', 'plural' => 'Fatture', 'what' => "Le fatture emesse, con l'invio allo SDI e l'incasso."],
        'company_profiles' => ['name' => 'dati dell\'agenzia', 'plural' => 'Dati dell\'agenzia', 'what' => "Ragione sociale, indirizzo, partita IVA, contatti: finiscono su fatture e preventivi."],
        'services' => ['name' => 'servizio', 'plural' => 'Servizi', 'what' => "Il listino: cosa vende l'agenzia e, per ogni servizio, il suo processo di lavoro.", 'tip' => 'Il prezzo e\' indicativo: quello vero si decide nel preventivo.'],
        'catalog' => ['name' => 'prodotto', 'plural' => 'Prodotti', 'what' => "I prodotti a catalogo, con prezzo e disponibilita'."],
        'work_orders' => ['name' => 'progetto', 'plural' => 'Progetti', 'what' => "I lavori per i clienti, con le fasi del servizio, la squadra e le date.", 'new' => "Cliente, servizio (il processo) e titolo: fasi, squadra e cartelle arrivano da sole."],
        'work_order_types' => ['name' => 'processo', 'plural' => 'Processi', 'what' => "Le ricette dei lavori: fasi, ruoli, date chiave, consegne. Di solito si vedono dalla pagina del servizio."],
        'work_order_type_steps' => ['name' => 'fase del processo', 'plural' => 'Fasi del processo', 'what' => "Una cosa da fare nei progetti di questo servizio: chi la fa (ruolo), che tipo e', dove vanno i suoi file.", 'tip' => 'Il tipo decide cosa si fa nella fase: una data, un\'approvazione, le risposte del cliente...'],
        'work_order_type_roles' => ['name' => 'ruolo', 'plural' => 'Ruoli', 'what' => "Chi lavora ai progetti di questo servizio (es. Coordinatore creativo) e chi lo fa di solito."],
        'work_order_type_milestones' => ['name' => 'data chiave', 'plural' => 'Date chiave', 'what' => "Una data da fissare in ogni progetto (briefing, presentazione), con l'eventuale email al cliente."],
        'work_order_type_deliverables' => ['name' => 'cosa si consegna', 'plural' => 'Cose da consegnare', 'what' => "Cosa riceve il cliente alla fine: sempre, o solo se venduto."],
        'work_order_members' => ['name' => 'persona della squadra', 'plural' => 'Squadra del progetto', 'what' => "Chi lavora al progetto e con che ruolo: le fasi del ruolo passano a lui e riceve l'avviso."],
        'work_order_milestones' => ['name' => 'data chiave del progetto', 'plural' => 'Date chiave del progetto', 'what' => "La data (e l'email al cliente, se c'e'): fissandola, la fase si chiude e l'appuntamento va in calendario.", 'tip' => '"A" e\' gia\' l\'email del cliente: rileggi l\'email prima di salvare.'],
        'work_order_deliverables' => ['name' => 'consegna', 'plural' => 'Consegne', 'what' => "Una cosa da consegnare in questo progetto: si spunta quando e' pronta."],
        'work_order_change_requests' => ['name' => 'modifica chiesta', 'plural' => 'Modifiche chieste', 'what' => "Una modifica che il cliente chiede dopo la presentazione: compresa o extra (da quotare)."],
        'tasks' => ['name' => "attivita'", 'plural' => "Attivita'", 'what' => "Le cose da fare, anche le fasi dei progetti."],
        'appointments' => ['name' => 'appuntamento', 'plural' => 'Appuntamenti', 'what' => "Incontri, shooting, chiamate: con chi, dove, quando. Vanno sul Google Calendar.", 'tip' => '"Confermato", "Fatto" o "Annullato" si segnano con un clic.'],
        'client_requests' => ['name' => 'richiesta', 'plural' => 'Richieste', 'what' => "Una richiesta del cliente: cosa vuole, da chi e' arrivata, chi la segue."],
        'editorial_posts' => ['name' => 'contenuto', 'plural' => 'Contenuti', 'what' => "Un post del piano editoriale: testo, immagini, quando esce, stato."],
        'editorial_quotas' => ['name' => 'contratto social', 'plural' => 'Contratti social', 'what' => "Quanti contenuti al mese prevede il contratto di ogni cliente."],
        'editorial_strategies' => ['name' => 'strategia', 'plural' => 'Strategie', 'what' => "La strategia social del cliente: obiettivi, tono, rubriche."],
        'media_assets' => ['name' => 'materiale', 'plural' => 'Materiali', 'what' => "Foto e video dei clienti, con descrizione (anche scritta dall'IA) per ritrovarli."],
        'documents-file' => ['name' => 'file', 'plural' => 'File', 'what' => "Un file del Drive: cosa contiene e parole chiave servono per ritrovarlo."],
        'seasonal_events' => ['name' => 'periodo forte', 'plural' => 'Periodi forti', 'what' => "Natale, saldi, ricorrenze: Leo li usa per proporre in tempo idee ai clienti giusti."],
        'expenses' => ['name' => 'spesa', 'plural' => 'Spese', 'what' => "Una spesa: quanto, per cosa, per quale progetto, se si rifattura al cliente."],
        'leave_requests' => ['name' => 'richiesta di ferie', 'plural' => 'Ferie e permessi', 'what' => "Ferie o permesso: da quando a quando, e lo stato della richiesta."],
        'work_entries' => ['name' => 'ore lavorate', 'plural' => 'Ore lavorate', 'what' => "Le ore segnate, per giorno e progetto."],
        'work_schedules' => ['name' => 'orario', 'plural' => 'Orari', 'what' => "L'orario di lavoro di ognuno: serve al calendario e alle ore."],
        'interventions' => ['name' => 'intervento', 'plural' => 'Interventi', 'what' => "Gli interventi fatti per i clienti."],
        'blog' => ['name' => 'articolo', 'plural' => 'Blog', 'what' => "Gli articoli del blog."],
        'campaigns' => ['name' => 'campagna', 'plural' => 'Campagne', 'what' => "Le campagne di comunicazione, con periodo e risultati."],
        'cases' => ['name' => 'pratica', 'plural' => 'Pratiche', 'what' => "Le pratiche aperte, con lo stato e cosa manca."],
        'document_requests' => ['name' => 'documento richiesto', 'plural' => 'Documenti richiesti', 'what' => "I documenti da farsi mandare dai clienti, con i solleciti."],
        'communications' => ['name' => 'comunicazione', 'plural' => 'Comunicazioni', 'what' => "Le comunicazioni mandate ai clienti."],
        'refunds' => ['name' => 'rimborso', 'plural' => 'Rimborsi', 'what' => "I rimborsi: per chi, quanto, lo stato."],
        'message_templates' => ['name' => 'modello di messaggio', 'plural' => 'Modelli di messaggio', 'what' => "Testi pronti per email e WhatsApp, da riusare."],
        'users' => ['name' => 'utente', 'plural' => 'Utenti', 'what' => "Le persone che usano il gestionale e il loro profilo (cosa possono vedere e fare).", 'tip' => 'Un collega nuovo si invita con "Invita": sceglie lui la password.'],
        'profiles' => ['name' => 'profilo', 'plural' => 'Profili', 'what' => "I profili di accesso (es. Amministratore, Grafico): ognuno con i suoi permessi."],
        'permissions' => ['name' => 'permesso', 'plural' => 'Permessi', 'what' => "Cosa permette ogni permesso e quali profili ce l'hanno."],
    ],
    /*
     * 'boxes': la "i" dei riquadri dei form, per entita' e titolo del
     * riquadro (come nel manifest del pacchetto). Una guida scritta nel
     * manifest ('guide' sul riquadro) vale di piu' di questa.
     */
    'boxes' => [
        'appointments' => [
            'Appuntamento' => "Con chi, cosa e quando. Lo stato dice se e' da confermare, confermato o fatto.",
            'Dove' => "Il posto e il tempo di viaggio (serve al calendario di chi va). Se e' online, metti il link della call.",
            'Da portare e note' => "Una cosa per riga: diventa una lista da spuntare prima di uscire.",
            'Chi' => "Chi lo segue e chi partecipa: lo vedono nel loro calendario e ricevono il promemoria.",
            'Promemoria' => "Quanti minuti prima avvisare chi partecipa.",
        ],
        'assistant_rules' => [
            'Regola' => "Cosa controlla l'assistente e quanto puo' fare da solo: solo avvisare, preparare per l'approvazione, oppure fare.",
        ],
        'assistant_actions' => [
            'Messaggio per il cliente' => "Il testo che l'assistente ha preparato. Correggilo come vuoi: parte solo quando lo approvi.",
        ],
        'seasonal_events' => [
            'Periodo' => "Il periodo forte (es. Natale, saldi) e l'idea da proporre ai clienti che c'entrano.",
            'Quando' => "La data e quanti giorni prima avvisare. Per Pasqua e le feste mobili usa lo scostamento da Pasqua.",
        ],
        'customer_touchpoints' => [
            'Contatto' => "Di cosa si e' parlato e il prossimo passo: con una data, l'assistente te lo ricorda.",
            'Quando e come' => "Giorno, canale e com'era il cliente: l'umore aiuta a capire chi rischia di andare via.",
            'Call registrata' => "Il resoconto della call o il link alla registrazione.",
        ],
        'customer_profiles' => [
            'Chi sono' => "La storia, cosa vendono, a chi e contro chi: la base per ogni proposta. Lo usa anche l'assistente.",
            'Stile' => "Cosa piace e cosa no, il tono, come usano il marchio: evita bozze da rifare.",
            'Quando' => "I periodi forti del cliente: l'assistente li usa per proporre in anticipo.",
        ],
        'customer_notes' => [
            'Cosa' => "Un'idea, una richiesta o una cosa da ricordare sul cliente, anche legata a un progetto.",
            'Roadmap' => "Spunta \"In roadmap\" per metterla tra le cose da proporre o fare, con stato e data.",
            'Chi e quando' => "Quando e da chi e' arrivata. Chi menzioni riceve un avviso.",
        ],
        'client_requests' => [
            'La richiesta' => "Cosa chiede il cliente, con le sue parole.",
            'Chi e quando' => "Chi se ne occupa, entro quando e quanto ci vuole a occhio.",
            'Da dove' => "Il canale (email, WhatsApp, telefono) e chi l'ha ricevuta.",
            'Extra' => "Se non e' compresa nel contratto: segnala l'importo, cosi' finisce in fattura.",
        ],
        'customer_services' => [
            'Servizio' => "Quale servizio ha il cliente e a che punto e'.",
            'Durata e rinnovo' => "Una tantum o ricorrente, quando si rinnova e con quanto preavviso: l'assistente avvisa prima della scadenza.",
            'Importi' => "Prezzo al cliente e costo (se c'e' un fornitore): servono a calcolare il margine.",
            'Pagamento' => "Come paga: tutto subito, acconto e saldo, a rate. Le scadenze si creano da qui.",
            'Risultati' => "Cosa ha ottenuto il cliente: utile per il rinnovo.",
            'Note' => "Tutto quello che non sta negli altri campi.",
        ],
        'customers' => [
            'Identita' => "Nome e cognome per una persona, ragione sociale per un'azienda: ne basta uno.",
            'Indirizzo' => "Serve per preventivi e fatture.",
            'Contatto' => "L'email e' quella a cui vanno preventivi e avvisi.",
            'Archivi' => "Le cartelle del cliente su Drive o pCloud: si aprono dalla sua scheda.",
            'Rapporto' => "Com'e' il cliente: caldo o freddo, importante o no, il carattere. Lo vede tutta la squadra.",
            'Quando risentirlo' => "Una data e il motivo: quel giorno l'assistente te lo ricorda.",
            'Chi lo segue' => "Il responsabile del cliente e chi ce lo ha presentato.",
            'Accordo da intermediario' => "Se chi l'ha presentato prende una percentuale: quanto e a quali condizioni.",
            'Dati fiscali' => "Partita IVA, codice SDI e PEC: servono per fatturare.",
            'Note' => "Tutto quello che non sta negli altri campi.",
            'Pagamento' => "Come preferisce pagare e in quanti giorni.",
        ],
        'customer_contacts' => [
            'Referente' => "Una persona del cliente con cui si parla, con il suo ruolo.",
            'Contatto' => "\"Decide lui\": e' chi approva preventivi e lavori.",
        ],
        'customer_team_members' => [
            'Nella squadra del cliente' => "Un collega che segue questo cliente e con che ruolo: i nuovi progetti lo propongono gia' nella squadra.",
        ],
        'document_folders' => [
            'Cartella' => "Il nome della cartella.",
        ],
        'documents' => [
            'File' => "Nome, descrizione ed etichette: servono a ritrovarlo con la ricerca.",
        ],
        'editorial_posts' => [
            'Contenuto' => "Il testo del post, cosa serve come foto o video e dove trovare il materiale.",
            'Dove e quando' => "Canale, formato e data di uscita: finisce nel piano editoriale.",
            'Chi e stato' => "Chi lo scrive, chi lo pubblica e a che punto e'.",
        ],
        'editorial_quotas' => [
            "Quantita' da contratto" => "Quanti post a settimana prevede il contratto, per canale: il piano avvisa se si resta indietro.",
        ],
        'editorial_strategies' => [
            'Strategia social' => "Obiettivi, pubblico e temi principali del cliente sui social.",
            'Come' => "Il tono e cosa fare o evitare: vale per tutti i post del cliente.",
        ],
        'expenses' => [
            'La spesa' => "Cosa, quanto e quando. Allega il link alla ricevuta.",
            'Per chi' => "Il cliente o il progetto: \"Da rifatturare\" la fa finire nella fattura del cliente.",
            'Chi' => "Chi l'ha pagata e se va rimborsata.",
        ],
        'installments' => [
            'Incasso' => "Di quale cliente e servizio, e che tipo di incasso (acconto, saldo, rata).",
            'Periodo' => "Il periodo che copre, per i servizi ricorrenti.",
            'Importo e stato' => "Quanto, entro quando, e se e' stato pagato.",
        ],
        'invoices' => [
            'Fattura' => "Tipo di documento, cliente e numero.",
            'Date e stato' => "Data, scadenza e se e' pagata.",
            'Importi' => "Imponibile e IVA: il totale si calcola da solo.",
        ],
        'company_profiles' => [
            'Azienda' => "I dati dell'agenzia: compaiono su preventivi e fatture.",
            'Sede' => "L'indirizzo della sede legale.",
            'Contatti e pagamento' => "Contatti e IBAN stampati sui documenti, e l'IVA di partenza.",
        ],
        'leave_requests' => [
            'Cosa e quando' => "Ferie, permesso o malattia, da quando a quando. Per un permesso di qualche ora metti anche gli orari.",
            'Stato' => "Chi approva risponde qui; chi ha chiesto riceve un avviso.",
        ],
        'media_assets' => [
            'Cosa contiene' => "Cosa si vede e in che occasione: serve a ritrovarla con la ricerca.",
            'Dove' => "Il cliente, quante persone e l'ambiente.",
        ],
        'notifications' => [
            'Avviso' => "Il titolo e il testo dell'avviso.",
        ],
        'notification_preferences' => [
            'Preferenza' => "Per ogni tipo di avviso: se vederlo nel gestionale, riceverlo per email, o tutti e due.",
        ],
        'work_schedules' => [
            'Orario' => "Inizio, fine, pausa e giorni di lavoro: servono a calendario, carico di lavoro e ore.",
            'Pianificazione' => "Quanta parte della giornata va ai progetti (il resto e' per email, riunioni, imprevisti).",
        ],
        'quotes' => [
            'Preventivo' => "Per chi, l'oggetto, il testo d'apertura e le condizioni: compaiono nel PDF.",
            'Date e pagamento' => "Data, validita', pagamento, revisioni comprese e sconto. Il responsabile e' chi lo segue.",
        ],
        'quote_lines' => [
            'Riga' => "Una voce del preventivo: scegli il servizio (porta descrizione e prezzo) o scrivila a mano.",
            'Prezzo' => "Quantita' e prezzo; se e' ricorrente, ogni quanto.",
            'Pagamento' => "Come si paga questa voce: tutto, acconto e saldo, a rate.",
        ],
        'tasks' => [
            'Attivita' => "Cosa fare. Nella lista una cosa per riga: diventano caselle da spuntare.",
            'Quando' => "Il giorno in cui farla e la scadenza. Se si ripete, ogni quanto.",
            'Chi e come' => "A chi tocca, lo stato e la priorita'. \"Aspetta il cliente\" la toglie dalle cose in ritardo.",
            'Tempo' => "Quanto si pensa e quanto ci e' voluto: serve a fare i preventivi giusti.",
            'Collegamento' => "A cosa si riferisce (progetto, cliente, preventivo).",
        ],
        'work_order_types' => [
            'Tipo di commessa' => "Il nome del processo e la struttura delle cartelle che crea su Drive.",
            'Uso' => "Per quale servizio si usa e quanti giorni dura di solito.",
        ],
        'work_order_type_steps' => [
            'Fase' => "Il nome della fase, cosa si fa e la lista delle cose da spuntare (una per riga).",
            'Chi e come' => "Il tipo di fase (lavoro, approvazione, appuntamento...), il ruolo a cui tocca e quando si chiude da sola.",
            'Quando e quanto' => "L'ordine, quanti giorni dopo l'inizio e quanto tempo ci vuole.",
        ],
        'work_orders' => [
            'Commessa' => "Per chi, che tipo di lavoro (porta le sue fasi) e da quale preventivo o servizio nasce.",
            'Tempi' => "Inizio e consegna prevista: le fasi si mettono in calendario da qui.",
            'Attesa del cliente' => "Se si aspetta il cliente, perche' e da quando: il progetto non risulta in ritardo per colpa nostra.",
            'Chi la segue' => "Il responsabile, l'eventuale fornitore, lo stato e la priorita'.",
            'Note' => "Tutto quello che non sta negli altri campi.",
        ],
        'work_order_type_roles' => [
            'Ruolo' => "Un ruolo del processo (es. Grafico): le fasi si assegnano ai ruoli, non alle persone.",
            'Chi' => "Chi lo fa di solito e se puo' essere piu' di una persona.",
        ],
        'work_order_type_milestones' => [
            'Data chiave' => "Una data importante del processo e l'email da mandare al cliente quando si fissa.",
            'Chi e quando' => "Chi la fissa e dopo quale fase. Puo' creare l'appuntamento in calendario.",
        ],
        'work_order_type_deliverables' => [
            'Cosa si consegna' => "Una cosa che riceve il cliente alla fine.",
            'Uso' => "Se c'e' sempre o solo quando la si aggiunge al progetto.",
        ],
        'work_order_members' => [
            'Nella squadra' => "Un collega nel progetto e il suo ruolo: le fasi di quel ruolo diventano sue.",
        ],
        'work_order_milestones' => [
            'Data' => "Il giorno fissato.",
            'Email al cliente' => "Se mandarla e a chi (di solito l'email del cliente). Prima di partire la puoi rileggere e cambiare.",
        ],
        'work_order_deliverables' => [
            'Cosa si consegna' => "Una cosa che riceve il cliente.",
            'Stato' => "Spuntala quando e' pronta: alla consegna devono esserci tutte.",
        ],
        'work_order_change_requests' => [
            'Modifica' => "Cosa chiede il cliente e quando.",
            'Chi e come' => "Chi la fa e se e' compresa o extra: un extra va nel preventivo.",
        ],
        'work_entries' => [
            'Lavoro' => "Su cosa e per quale cliente.",
            'Quando e quanto' => "Il giorno e i minuti.",
        ],
        'posts' => [
            'Articolo' => "Titolo, indirizzo (slug), riassunto e testo.",
            'Pubblicazione' => "Stato, data di uscita, autore e immagine di copertina.",
        ],
        'campaigns' => [
            'Campagna' => "Il nome, l'oggetto e il testo.",
            'Programmazione' => "Quando parte e a chi.",
        ],
        'products' => [
            'Prodotto' => "Nome, codice, categoria e descrizione.",
            'Prezzi' => "Costo, prezzo di vendita e IVA: il margine si calcola da qui.",
            'Magazzino' => "Quanti ce ne sono e sotto quale quantita' avvisare.",
        ],
        'document_requests' => [
            'Documento' => "Quale documento serve e se e' completo.",
            'File' => "Il file ricevuto.",
            'Collegamento e date' => "La pratica, quando e' stato chiesto e quando controllato.",
        ],
        'interventions' => [
            'Intervento' => "Cosa, per chi e quando.",
            'Assegnazione' => "Chi lo fa: lo vede nel suo calendario.",
            'Costi' => "Uscita e manodopera: il totale si calcola da solo.",
        ],
        'leads' => [
            'Informazioni principali' => "Chi e', e per quale azienda.",
            'Indirizzo' => "Dove si trova.",
            'Contatto' => "Come raggiungerlo.",
            'Stato della trattativa' => "A che punto e', quanto vale e quando risentirlo: l'assistente lo ricorda.",
            'Note' => "Tutto quello che non sta negli altri campi.",
            'Attribuzione' => "Da dove e' arrivato (canale, campagna): serve a capire cosa funziona.",
            'Dettagli del volo' => "I dati del volo e del disservizio.",
            'Qualita del dato' => "Quanto e' affidabile e se e' un doppione di un altro contatto.",
        ],
        'message_templates' => [
            'Modello' => "Il nome, il canale e l'oggetto.",
            'Testo' => "Il testo, con i segnaposto che si riempiono da soli.",
        ],
        'refunds' => [
            'Importi' => "Quanto si chiede e quanto e' stato accettato.",
            'Pagamento' => "Se e' stato pagato, quando e con quale fattura.",
            'Collegamento' => "La pratica a cui si riferisce.",
        ],
    ],
];
