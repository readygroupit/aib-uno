<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Base per le entita' che mappano una riga di tabella. Ogni tabella del
 * framework porta gli stessi campi di servizio (status + audit di
 * creazione/modifica): questa classe li dichiara una volta sola cosi'
 * le entita' concrete devono solo aggiungere le proprie colonne.
 */
abstract class AbstractModel
{
    /**
     * Campi di servizio nascosti di default da toDisplayArray() (liste/griglie).
     * Una sottoclasse con stati oltre 0/1 puo' ridefinire questa costante
     * togliendo 'status' per renderlo visibile.
     */
    protected const HIDDEN_FIELDS = ['status', 'createdAt', 'createdBy', 'updatedAt', 'updatedBy'];

    public ?int $id = null;

    /** 0 = eliminato (logicamente), 1 = attivo. Altri valori possibili per tabella. */
    public int $status = 1;

    public ?string $createdAt = null;
    public ?int $createdBy = null;
    public ?string $updatedAt = null;
    public ?int $updatedBy = null;

    public static function fromArray(array $row): static
    {
        $model = new static();
        foreach ($row as $column => $value) {
            $property = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $column))));
            if (property_exists($model, $property)) {
                $model->$property = $value;
            }
        }

        return $model;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Come toArray() ma senza i campi di servizio: quello che si mostra
     * di norma in una lista/griglia.
     */
    public function toDisplayArray(): array
    {
        return array_diff_key($this->toArray(), array_flip(static::HIDDEN_FIELDS));
    }
}
