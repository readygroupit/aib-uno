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
 *
 * Il CAP e il comune si compilano a vicenda: nel campo Comune si puo'
 * scrivere anche il CAP, e nel campo CAP i suggerimenti portano il comune
 * (e, dove il form ha comune e provincia a testo, anche quelli).
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

        if (ctype_digit($query)) {
            $this->json(array_map(function (array $row) {
                $option = $this->toOption($row, $row['postal_code']);
                $option['label'] .= " \u{b7} {$row['postal_code']}";

                return $option;
            }, $repo->searchByPostalCode($query, 10)));

            return;
        }

        // Il CAP si compila da solo solo se il comune ne ha uno (Roma ne ha decine).
        $this->json(array_map(function (array $row) use ($repo) {
            $codes = $repo->postalCodesOf((int) $row['id']);

            return $this->toOption($row, count($codes) === 1 ? $codes[0] : null);
        }, $repo->search($query, 10)));
    }

    /** CAP a testo libero: i suggerimenti scrivono il CAP e compilano il comune. */
    public function searchPostalCodesAction(): void
    {
        $query = trim((string) $this->param('q', ''));
        if (mb_strlen($query) < 2 || !ctype_digit($query)) {
            $this->json([]);

            return;
        }

        $rows = $this->container->get(MunicipalityRepository::class)->searchByPostalCode($query, 10);
        $this->json(array_map(static fn (array $row) => [
            'value' => $row['postal_code'],
            'label' => "{$row['postal_code']} \u{b7} {$row['name']} ({$row['province_code']})",
            'fill' => ['municipality_id' => (string) $row['id'], 'city' => $row['name'], 'province' => $row['province_code']],
        ], $rows));
    }

    /** @param array{id:int,name:string,province_code:string} $row */
    private function toOption(array $row, ?string $postalCode = null): array
    {
        $option = ['value' => $row['id'], 'label' => "{$row['name']} ({$row['province_code']})"];
        if ($postalCode !== null) {
            $option['fill'] = ['postal_code' => $postalCode];
        }

        return $option;
    }
}
