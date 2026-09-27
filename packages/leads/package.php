<?php

declare(strict_types=1);

/**
 * Contatto non ancora confermato come cliente - a monte di 'customers'.
 * 'stage' e' il ciclo di vita del lead (nuovo/contattato/qualificato/
 * proposta/vinto/perso...): i codici sono vocabolario del progetto, per
 * questo e' un semplice VARCHAR e non un ENUM fisso a livello DB -
 * aggiungere uno stage nuovo non deve mai richiedere una ALTER.
 *
 * Campo di catalogo completo (identita', contatto, indirizzo libero,
 * attribuzione marketing, gestione della trattativa): tutti negoziabili
 * tranne email/stage, pensato per essere installato per intero su un
 * primo progetto reale e poi ridotto caso per caso, non per essere
 * negoziato campo per campo fin dal primo utilizzo.
 *
 * 'format' e' letto da App\Validation\FieldValidator (non da
 * PackageInstaller, che lo ignora): stesso manifest usato sia per
 * generare lo schema SQL sia per sapere come validare/rendere il campo
 * nel form - un solo punto che descrive un campo, non due da tenere
 * allineati a mano.
 *
 * converted_customer_id collega il lead al cliente quando si converte:
 * negoziabile, con dipendenza da 'customers' derivata dalla selezione
 * (vedi la nota su 'references' in App\Package\PackageInstaller).
 * assigned_user_id referenzia 'users', tabella del framework sempre
 * presente - non e' un pacchetto, non serve alcuna dipendenza.
 */
return [
    'name' => 'leads',
    'label' => 'Contatti',
    'category' => 'crm',
    'description' => 'Contatti non ancora confermati come clienti.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'leads' => [
            'table' => 'leads',
            'fields' => [
                // --- identita' ---
                'first_name' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Nome',
                    'defaultRequired' => true,
                ],
                'last_name' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Cognome',
                    'defaultRequired' => true,
                ],
                'company_name' => [
                    'sql' => 'VARCHAR(150)',
                    'label' => 'Azienda',
                    'defaultRequired' => true,
                ],
                'job_title' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Ruolo',
                    'defaultRequired' => true,
                ],

                // --- contatto ---
                'email' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Email',
                    'defaultRequired' => true,
                    'base' => true,
                    'format' => 'email',
                ],
                'phone' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Telefono',
                    'defaultRequired' => true,
                ],
                'mobile_phone' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Cellulare',
                    'defaultRequired' => true,
                ],
                'website' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Sito web',
                    'defaultRequired' => true,
                ],

                // --- indirizzo (testo libero, non un riferimento a 'geo':
                // vedi App\Package\PackageInstaller, un pacchetto negoziabile
                // in piu' e' un costo che qui non serve pagare subito) ---
                'address' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Indirizzo',
                    'defaultRequired' => true,
                ],
                'city' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Citta',
                    'defaultRequired' => true,
                ],
                'postal_code' => [
                    'sql' => 'VARCHAR(10)',
                    'label' => 'CAP',
                    'defaultRequired' => true,
                ],

                // --- trattativa ---
                'stage' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Stato del contatto',
                    'base' => true,
                    'help' => "Vocabolario libero, non un elenco fisso: valori tipici sono nuovo, contattato, "
                        . "qualificato, proposta, convertito, perso. Aggiungerne uno nuovo non richiede modifiche al database.",
                ],
                'priority' => [
                    'sql' => 'TINYINT UNSIGNED',
                    'label' => 'Priorita (1-10)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => 'Scala libera da 1 a 10: piu\' alto significa piu\' urgente da seguire, non un punteggio calcolato automaticamente.',
                ],
                'estimated_value' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Valore stimato',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                    'help' => 'Valore economico potenziale della trattativa se andra\' a buon fine, in euro - una stima dell\'operatore, non un prezzo di listino.',
                ],
                'assigned_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Operatore assegnato',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'help' => "Id numerico dell'utente responsabile del contatto (non ancora un elenco da cui scegliere il nome - limite noto, da migliorare).",
                ],
                'next_follow_up_at' => [
                    'sql' => 'DATE',
                    'label' => 'Prossimo richiamo',
                    'defaultRequired' => true,
                    'format' => 'date',
                ],
                'last_contacted_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Ultimo contatto',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'lost_reason' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Motivo perdita',
                    'defaultRequired' => true,
                    'help' => "Ha senso solo quando lo stato e' 'perso' - il form non lo nasconde negli altri stati, ma resta ignorato finche' non serve.",
                ],
                'converted_customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente convertito',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                ],
                'converted_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Data conversione',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'notes' => [
                    'sql' => 'TEXT',
                    'label' => 'Note',
                    'defaultRequired' => true,
                ],

                // --- attribuzione marketing (vedi nota sopra sul perche'
                // resta qui e non in un pacchetto a se') ---
                'source_channel' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Canale di provenienza',
                    'defaultRequired' => true,
                    'help' => "Da dove arriva il contatto (es. sito web, passaparola, fiera, telefono) - insieme a campagna/parola "
                        . "chiave/pagina di atterraggio serve a capire quali canali portano davvero risultati, non e' obbligatorio compilarli tutti.",
                ],
                'campaign' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Campagna',
                    'defaultRequired' => true,
                ],
                'keyword' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Parola chiave',
                    'defaultRequired' => true,
                ],
                'landing_page' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Pagina di atterraggio',
                    'defaultRequired' => true,
                ],

                // --- qualita' del dato / deduplica (negoziabili: un lead
                // generico B2B non ne ha bisogno, un progetto che riceve
                // contatti da moduli esterni ripetuti si', vedi il caso
                // Assilevi - preservare l'id originale e collegare senza
                // MAI cancellare e' un requisito esplicito li') ---
                'original_submission_id' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Id invio originale',
                    'defaultRequired' => true,
                    'help' => "Identificativo del modulo/invio esterno (es. Jotform) che ha generato questo contatto - "
                        . "non va mai sovrascritto, nemmeno unendo un duplicato: e' la prova di dove e quando e' arrivato.",
                ],
                'related_lead_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Collegato a',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'leads', 'column' => 'id'],
                    'help' => "Punta a un altro contatto quando questo e' un probabile duplicato o fa parte della stessa "
                        . "pratica familiare/di gruppo - un collegamento, non una fusione: entrambe le righe restano, nessun dato si perde.",
                ],
                'reliability_level' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Affidabilita',
                    'defaultRequired' => true,
                    'help' => "Quanto la richiesta sembra completa e coerente (es. alta, media, bassa) - una valutazione "
                        . "di supporto per l'operatore, mai il criterio che decide da solo se accettare la pratica.",
                ],

                // --- dettagli del disservizio (negoziabili - un pacchetto
                // generico di contatti non li usa, un progetto per reclami
                // di viaggio si') ---
                'disservice_type' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Tipo di disservizio',
                    'defaultRequired' => true,
                    'help' => "Vocabolario libero come 'stage' sopra - valori tipici: cancellazione, ritardo, negato "
                        . "imbarco, bagaglio, spese documentate.",
                ],
                'airline' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Compagnia aerea',
                    'defaultRequired' => true,
                ],
                'flight_route' => [
                    'sql' => 'VARCHAR(150)',
                    'label' => 'Tratta',
                    'defaultRequired' => true,
                ],
                'flight_date' => [
                    'sql' => 'DATE',
                    'label' => 'Data del volo',
                    'defaultRequired' => true,
                    'format' => 'date',
                ],
                'passenger_count' => [
                    'sql' => 'TINYINT UNSIGNED',
                    'label' => 'Numero passeggeri',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
            ],

            /**
             * Organizzazione a sezioni(tab)/box per il form di modifica -
             * letta da App\View\Component\FormComponent, che incrocia
             * ogni box con i campi DAVVERO disponibili per questo
             * progetto: un box i cui campi non sono installati sparisce
             * da solo, non lascia un vuoto (vedi il commento li' per il
             * meccanismo). Un campo qui elencato ma non installato viene
             * semplicemente ignorato in quel box.
             *
             * 'area' posiziona il box dentro la sezione: 'main' (colonna
             * larga) o 'sidebar' (colonna stretta accanto) - stesso
             * schema "principale + sidebar" della scheda cliente di
             * Istrumed. Piu' box con la stessa area si impilano nella
             * loro colonna, nell'ordine in cui compaiono qui.
             */
            'layout' => [
                'sections' => [
                    [
                        'label' => 'Anagrafica',
                        'boxes' => [
                            // 'main' e 'sidebar' finiscono affiancati nella
                            // stessa riga di griglia, alta quanto la
                            // colonna piu' piena (vedi .form-section--split
                            // in kit.css): un solo box corto in 'main'
                            // contro due box in 'sidebar' lascia un vuoto
                            // enorme sotto - qui i box vanno bilanciati per
                            // quantita' di campi, non solo per categoria
                            // logica (Indirizzo e' finito in 'main' proprio
                            // per questo, anche se concettualmente sarebbe
                            // stato naturale in sidebar).
                            [
                                'title' => 'Informazioni principali',
                                'area' => 'main',
                                'fields' => ['first_name', 'last_name', 'company_name', 'job_title'],
                            ],
                            [
                                'title' => 'Indirizzo',
                                'area' => 'main',
                                'fields' => ['address', 'city', 'postal_code'],
                            ],
                            [
                                'title' => 'Contatto',
                                'area' => 'sidebar',
                                'fields' => ['email', 'phone', 'mobile_phone', 'website'],
                            ],
                        ],
                    ],
                    [
                        'label' => 'Trattativa',
                        'boxes' => [
                            [
                                'title' => 'Stato della trattativa',
                                'area' => 'main',
                                'fields' => [
                                    'stage', 'priority', 'estimated_value', 'assigned_user_id',
                                    'next_follow_up_at', 'last_contacted_at', 'lost_reason',
                                ],
                            ],
                            [
                                'title' => 'Note',
                                'area' => 'main',
                                'fields' => ['notes'],
                            ],
                        ],
                    ],
                    [
                        'label' => 'Marketing',
                        'boxes' => [
                            [
                                'title' => 'Attribuzione',
                                'area' => 'main',
                                'fields' => ['source_channel', 'campaign', 'keyword', 'landing_page'],
                            ],
                        ],
                    ],
                    [
                        'label' => 'Disservizio',
                        'boxes' => [
                            [
                                'title' => 'Dettagli del volo',
                                'area' => 'main',
                                'fields' => ['disservice_type', 'airline', 'flight_route', 'flight_date', 'passenger_count'],
                            ],
                            [
                                'title' => 'Qualita del dato',
                                'area' => 'sidebar',
                                'fields' => ['original_submission_id', 'reliability_level', 'related_lead_id'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
