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
        $intProperties = self::intProperties();
        foreach ($row as $column => $value) {
            $property = self::propertyFromColumn($column);
            if (!property_exists($model, $property)) {
                continue;
            }
            // Dal database gli interi arrivano interi, da un form o da una
            // query string arrivano stringhe ("5"): stessa cosa, si converte.
            if (isset($intProperties[$property]) && is_string($value)) {
                $value = $value === '' ? null : (is_numeric($value) ? (int) $value : null);
            }
            $model->$property = $value;
        }

        return $model;
    }

    /** @return array<string,true> proprieta' di tipo int di questa classe */
    private static function intProperties(): array
    {
        static $cache = [];
        if (isset($cache[static::class])) {
            return $cache[static::class];
        }

        $result = [];
        foreach ((new \ReflectionClass(static::class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === 'int') {
                $result[$property->getName()] = true;
            }
        }

        return $cache[static::class] = $result;
    }

    /** Nome colonna snake_case (es. 'company_name') -> proprieta' camelCase (es. 'companyName'). */
    public static function propertyFromColumn(string $column): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $column))));
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
