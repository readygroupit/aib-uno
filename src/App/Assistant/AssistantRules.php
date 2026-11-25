<?php

declare(strict_types=1);

namespace App\Assistant;

/**
 * Le regole dell'assistente: cosa guarda e cosa dice. La direzione ne
 * cambia solo accesa/spenta, i giorni e (per quelle che scrivono al
 * cliente) l'autonomia - tabella assistant_rules, valori di qui finche'
 * nessuno li tocca.
 *
 * 'section' e' dove compare nella giornata di ciascuno; 'notify' se,
 * oltre a comparire, manda anche un avviso (una volta per episodio).
 */
final class AssistantRules
{
    public const AUTONOMY_NOTICE = 'solo avviso';
    public const AUTONOMY_CONFIRM = 'chiede conferma';
    public const AUTONOMY_AUTO = 'in automatico';

    /** @var array<string, array{label:string, help:string, days:?int, daysLabel:?string, autonomy:?string, section:string, notify:bool}> */
    public const ALL = [
        'approval_waiting' => [
            'label' => 'Approvazioni che ti aspettano',
            'help' => "Bozze, preventivi o altre fasi che aspettano il tuo ok. L'avviso parte se aspettano da qualche giorno.",
            'days' => 2, 'daysLabel' => 'avviso dopo giorni di attesa', 'autonomy' => null, 'section' => 'decide', 'notify' => true,
        ],
        'step_ready' => [
            'label' => 'Tocca a te',
            'help' => 'La fase di un progetto che adesso e\' tua.',
            'days' => null, 'daysLabel' => null, 'autonomy' => null, 'section' => 'today', 'notify' => false,
        ],
        'task_late' => [
            'label' => 'Attivita\' in ritardo',
            'help' => 'Le tue attivita\' con la data gia\' passata.',
            'days' => null, 'daysLabel' => null, 'autonomy' => null, 'section' => 'today', 'notify' => false,
        ],
        'milestone_to_set' => [
            'label' => 'Date chiave da fissare',
            'help' => 'Briefing, inizio lavoro, presentazioni: quando tocca fissarle, a chi le fissa.',
            'days' => null, 'daysLabel' => null, 'autonomy' => null, 'section' => 'schedule', 'notify' => true,
        ],
        'role_missing' => [
            'label' => 'Ruoli scoperti',
            'help' => 'Un progetto senza nessuno in un ruolo che serve (es. il coordinatore creativo).',
            'days' => null, 'daysLabel' => null, 'autonomy' => null, 'section' => 'schedule', 'notify' => true,
        ],
        'milestone_soon' => [
            'label' => 'Date chiave in arrivo',
            'help' => 'Il giorno prima di un briefing o di una presentazione: cosa preparare e cosa sappiamo gia\' del cliente. Arriva a chi tocca e al responsabile del progetto.',
            'days' => 1, 'daysLabel' => 'giorni prima', 'autonomy' => null, 'section' => 'soon', 'notify' => true,
        ],
        'work_missing' => [
            'label' => 'Lavoro non pronto per una data',
            'help' => 'Manca poco a una presentazione e le fasi prima (es. le bozze) non sono fatte.',
            'days' => 2, 'daysLabel' => 'giorni prima', 'autonomy' => null, 'section' => 'soon', 'notify' => true,
        ],
        'new_contact' => [
            'label' => 'Contatto senza appuntamento',
            'help' => 'Un cliente nuovo ci ha scritto e non e\' ancora fissato l\'incontro.',
            'days' => 2, 'daysLabel' => 'giorni dal contatto', 'autonomy' => null, 'section' => 'followup', 'notify' => true,
        ],
        'quote_no_answer' => [
            'label' => 'Preventivo senza risposta',
            'help' => 'Preventivo inviato e nessuna risposta: prepara il messaggio per risentire il cliente.',
            'days' => 7, 'daysLabel' => 'giorni dall\'invio', 'autonomy' => self::AUTONOMY_CONFIRM, 'section' => 'followup', 'notify' => true,
        ],
        'client_no_answer' => [
            'label' => 'Il cliente non risponde',
            'help' => 'Il progetto aspetta il cliente (materiale, approvazione): prepara il sollecito.',
            'days' => 5, 'daysLabel' => 'giorni di attesa', 'autonomy' => self::AUTONOMY_CONFIRM, 'section' => 'followup', 'notify' => true,
        ],
        'project_stale' => [
            'label' => 'Progetto fermo',
            'help' => 'Nessun movimento da qualche giorno: a chi lo segue e a chi tocca.',
            'days' => 7, 'daysLabel' => 'giorni senza movimenti', 'autonomy' => null, 'section' => 'followup', 'notify' => true,
        ],
        'contact_due' => [
            'label' => 'Da risentire oggi',
            'help' => 'E\' arrivata la data "Risentirlo il" del cliente: prepara il messaggio, o rimanda.',
            'days' => null, 'daysLabel' => null, 'autonomy' => self::AUTONOMY_CONFIRM, 'section' => 'followup', 'notify' => true,
        ],
        'customer_silent' => [
            'label' => 'Cliente che non senti da un po\'',
            'help' => 'Nessun contatto registrato da qualche giorno (per i clienti chiave la meta\'): un saluto, un aggiornamento, una proposta.',
            'days' => 30, 'daysLabel' => 'giorni senza contatti', 'autonomy' => self::AUTONOMY_CONFIRM, 'section' => 'followup', 'notify' => true,
        ],
        'contact_hot' => [
            'label' => 'Cliente caldo: risentirlo dopo',
            'help' => 'Propone la data "Risentirlo il" per i clienti caldi, e dopo ogni contatto.',
            'days' => 3, 'daysLabel' => 'giorni', 'autonomy' => null, 'section' => 'settings', 'notify' => false,
        ],
        'contact_warm' => [
            'label' => 'Cliente tiepido: risentirlo dopo',
            'help' => 'Come sopra, per i clienti tiepidi (e per chi non ha una temperatura).',
            'days' => 10, 'daysLabel' => 'giorni', 'autonomy' => null, 'section' => 'settings', 'notify' => false,
        ],
        'contact_cold' => [
            'label' => 'Cliente freddo: risentirlo dopo',
            'help' => 'Come sopra, per i clienti freddi.',
            'days' => 30, 'daysLabel' => 'giorni', 'autonomy' => null, 'section' => 'settings', 'notify' => false,
        ],
        'seasonal' => [
            'label' => 'Periodi forti in arrivo',
            'help' => 'Natale, San Valentino, vendemmia...: in anticipo, i clienti per cui contano, con lo spunto per proporre un piano (calendario in /assistente/stagioni).',
            'days' => null, 'daysLabel' => null, 'autonomy' => self::AUTONOMY_CONFIRM, 'section' => 'ideas', 'notify' => true,
        ],
        'service_idea' => [
            'label' => 'Un servizio che al cliente manca',
            'help' => 'Cosa comprano di solito, insieme, i clienti che hanno gli stessi servizi (dal listino e dai servizi venduti). Si ripropone dopo qualche giorno.',
            'days' => 90, 'daysLabel' => 'giorni tra una proposta e l\'altra', 'autonomy' => null, 'section' => 'ideas', 'notify' => false,
        ],
        'portfolio' => [
            'label' => 'Portfolio',
            'help' => 'Un progetto appena consegnato: proposta di un\'immagine per il portfolio nello stile del cliente.',
            'days' => 14, 'daysLabel' => 'giorni dalla consegna', 'autonomy' => null, 'section' => 'ideas', 'notify' => true,
        ],
    ];

    /**
     * I "colleghi digitali" della home: ognuno parla delle sue regole. Nomi,
     * ruoli e colori si cambiano per progetto con 'assistant.colleagues' in
     * config (stesse chiavi); le regole restano queste.
     */
    public const COLLEAGUES = [
        'projects' => ['name' => 'Nora', 'role' => 'segue i progetti', 'color' => '#1f5566', 'more' => 'Tutto dei progetti',
            'ask' => 'a che punto e\' il progetto di...?', 'notices' => ['project_member_added', 'task_assigned', 'step_ready', 'approval_requested', 'milestone_set', 'change_request'],
            'rules' => ['approval_waiting', 'step_ready', 'milestone_to_set', 'role_missing', 'milestone_soon', 'work_missing', 'client_no_answer', 'project_stale', 'task_late']],
        'customers' => ['name' => 'Teo', 'role' => 'tiene vivi i clienti', 'color' => '#4f7b5c', 'more' => 'Tutti i clienti da seguire',
            'ask' => 'chi non sento da piu\' di un mese?', 'notices' => ['mention'],
            'rules' => ['contact_due', 'customer_silent', 'contact_hot', 'contact_warm', 'contact_cold']],
        'sales' => ['name' => 'Ada', 'role' => 'preventivi e contatti', 'color' => '#8b83ad', 'more' => 'Tutte le trattative',
            'ask' => 'quanti preventivi sono fermi?', 'notices' => [],
            'rules' => ['new_contact', 'quote_no_answer']],
        'ideas' => ['name' => 'Leo', 'role' => 'periodi forti e idee', 'color' => '#b99333', 'more' => 'Tutte le idee',
            'ask' => 'per quali clienti conta San Valentino?', 'notices' => [],
            'rules' => ['seasonal', 'service_idea', 'portfolio']],
    ];

    /** Quanto preme una regola: decide l'ordine dentro un collega e il colore dell'etichetta. */
    public const URGENCY = [
        'approval_waiting' => 'oggi', 'step_ready' => 'oggi', 'milestone_to_set' => 'oggi', 'role_missing' => 'oggi',
        'task_late' => 'oggi', 'contact_due' => 'oggi', 'new_contact' => 'oggi',
        'milestone_soon' => 'presto', 'work_missing' => 'presto', 'quote_no_answer' => 'presto', 'client_no_answer' => 'presto',
        'customer_silent' => 'importante', 'project_stale' => 'importante',
        'seasonal' => 'idea', 'service_idea' => 'idea', 'portfolio' => 'idea',
    ];

    public const SECTIONS = [
        'decide' => ['title' => 'Da decidere', 'tone' => 'warning'],
        'today' => ['title' => 'Da fare', 'tone' => 'info'],
        'schedule' => ['title' => 'Da fissare e organizzare', 'tone' => 'neutral'],
        'soon' => ['title' => 'In arrivo', 'tone' => 'info'],
        'followup' => ['title' => 'Da risentire', 'tone' => 'warning'],
        'ideas' => ['title' => 'Idee', 'tone' => 'success'],
    ];
}
