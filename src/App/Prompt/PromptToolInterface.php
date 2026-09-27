<?php

declare(strict_types=1);

namespace App\Prompt;

/**
 * Una capacita' che il prompt puo' invocare (Claude la sceglie via tool
 * use in base alla richiesta). execute() torna una lista di dati
 * componente (stesso formato di AbstractComponent::toData() - '{type:...}'),
 * mai HTML: il client li disegna con lo stesso registro JS gia' in uso
 * per le pagine normali.
 */
interface PromptToolInterface
{
    public function name(): string;

    public function description(): string;

    /** JSON Schema per il parametro 'input_schema' del tool Claude. */
    public function inputSchema(): array;

    /** Etichetta mostrata da 'mostra menu'; null = capacita' non elencata nel menu. */
    public function menuLabel(): ?string;

    /**
     * Chiave (non l'etichetta - vedi ShowMenuTool::SECTIONS per label/colore)
     * della sezione di navigazione a cui appartiene questa voce nel menu a
     * griglia; null se non compare nel menu (stesso significato di
     * menuLabel() nullo - infatti quando menuLabel() e' null anche questo
     * lo e' sempre). Dichiarata per singola capacita', non in una mappa
     * esterna tenuta a mano altrove: una nuova voce di menu senza questo
     * metodo e' un errore di compilazione, non una voce silenziosamente
     * fuori posto.
     */
    public function menuSection(): ?string;

    /**
     * Numero (o "installati/totali" per i package) gia' formattato per la
     * card del menu; null se la voce non ha un conteggio sensato o non
     * compare nel menu. Stringa e non int perche' non tutte le voci sono
     * un conteggio puro - vedi ListPackagesTool.
     */
    public function menuCount(): ?string;

    /** Codice permesso richiesto per eseguire questa capacita'; null = nessuno (solo autenticazione). */
    public function requiredPermission(): ?string;

    /**
     * Frasi scorciatoia (minuscolo) che eseguono la capacita' senza
     * passare da Claude - vedi PromptController::matchLocalTool(). Non e'
     * un resolver a parole chiave generico (quello e' stato scartato
     * apposta in Fase 2): ogni capacita' dichiara solo le proprie 2-3
     * frasi note, non tenta di interpretare linguaggio libero.
     *
     * @return string[]
     */
    public function triggers(): array;

    public function execute(array $input): array;
}
