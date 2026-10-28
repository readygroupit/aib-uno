<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Model\AbstractModel;
use App\Support\ReferenceResolver;

/**
 * Endpoint di ricerca generico per i campi autocomplete che puntano a
 * un'altra entita' (vedi 'autocomplete' sul campo nel manifest e
 * public/js/components/form.js, buildAutocompleteControl) - stesso schema
 * di Index\Controller\GeoController ma parametrico su QUALE entita' cercare
 * invece di un endpoint dedicato per ciascuna. Le entita' possibili e i
 * loro permessi sono in App\Support\ReferenceResolver::SOURCES (le usa
 * anche la chat per compilare un form da un nome).
 */
final class EntityReferenceController extends AuthController
{
    public function searchAction(): void
    {
        $resolver = $this->container->get(ReferenceResolver::class);
        $source = $resolver->source((string) $this->param('source'));
        if ($source === null) {
            $this->json([]);

            return;
        }

        $repo = $this->container->get($source['repository']);

        $id = trim((string) $this->param('id', ''));
        if ($id !== '') {
            $model = $repo->find((int) $id);
            $this->json($model === null ? [] : $this->toOption($model, $source, $resolver));

            return;
        }

        $query = trim((string) $this->param('q', ''));
        if (mb_strlen($query) < 2) {
            $this->json([]);

            return;
        }

        $columns = $source['columns'];
        if (isset($source['fallback'])) {
            $columns[] = $source['fallback'];
        }

        $matches = $repo->searchFreeText($columns, $query, 10, $source['columns']);
        $this->json(array_map(fn (AbstractModel $model) => $this->toOption($model, $source, $resolver), $matches));
    }

    private function toOption(AbstractModel $model, array $source, ReferenceResolver $resolver): array
    {
        $option = ['value' => $model->id, 'label' => $resolver->label($model, $source)];

        if (isset($source['fill'])) {
            $fill = [];
            foreach ($source['fill'] as $field => $column) {
                $value = $model->{AbstractModel::propertyFromColumn($column)} ?? null;
                if ($value !== null && $value !== '') {
                    $fill[$field] = (string) $value;
                }
            }
            $option['fill'] = $fill;
        }

        return $option;
    }
}
