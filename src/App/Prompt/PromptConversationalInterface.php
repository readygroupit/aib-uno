<?php

declare(strict_types=1);

namespace App\Prompt;

/**
 * Capacita' OPZIONALE (non parte di PromptToolInterface - implementarla
 * costringerebbe le 16+ classi esistenti ad aggiungere due metodi anche
 * quando non hanno niente di utile da dire): un tool che la implementa
 * puo' arricchire la risposta del prompt con una frase reale sul
 * risultato appena calcolato e qualche domanda di follow-up plausibile,
 * mostrate come bolla di chat + chip cliccabili (vedi PromptController,
 * hero.js). Mai testo generato da Claude qui: costerebbe una chiamata
 * API a ogni singola risposta anche per un match locale gratuito -
 * frasi/domande scritte da chi conosce il dominio di quel tool, come
 * gia' avviene per triggers().
 */
interface PromptConversationalInterface
{
    /**
     * Frase reale che descrive il risultato di execute($input) appena
     * calcolato - null se non c'e' niente di significativo da aggiungere
     * oltre ai componenti mostrati (il chiamante non mostra una bolla
     * vuota in quel caso).
     */
    public function replySummary(array $input): ?string;

    /** @return list<string> 0-4 domande di follow-up, ciascuna eseguibile com'e' come nuovo prompt. */
    public function followUpSuggestions(): array;
}
