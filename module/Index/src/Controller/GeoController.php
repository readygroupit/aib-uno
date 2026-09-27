<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Repository\MunicipalityRepository;

/**
 * Endpoint di ricerca per il campo autocomplete "Comune" (vedi
 * public/js/components/form.js, buildAutocompleteField) - stesso
 * endpoint serve sia "cerca per testo" (?q=) sia "risolvi l'etichetta di
 * un id gia' salvato" (?id=, usato al caricamento del form per mostrare
 * il nome invece del numero). Nessun permesso dedicato: dati di
 * riferimento, non sensibili, solo login richiesto.
 */
final class GeoController extends AuthController
{
    public function searchMunicipalitiesAction(): void
    {
        $repo = $this->container->get(MunicipalityRepository::class);

        $id = trim((string) $this->param('id', ''));
        if ($id !== '') {
            $row = $repo->findWithProvince((int) $id);
            $this->json($row === null ? [] : $this->toOption($row));

            return;
        }

        $query = trim((string) $this->param('q', ''));
        if (mb_strlen($query) < 2) {
            $this->json([]);

            return;
        }

        $rows = $repo->search($query, 10);
        $this->json(array_map($this->toOption(...), $rows));
    }

    /** @param array{id:int,name:string,province_code:string} $row */
    private function toOption(array $row): array
    {
        return ['value' => $row['id'], 'label' => "{$row['name']} ({$row['province_code']})"];
    }
}
