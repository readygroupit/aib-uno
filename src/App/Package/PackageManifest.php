<?php

declare(strict_types=1);

namespace App\Package;

final class PackageManifest
{
    private array $data;

    public function __construct(string $packageName)
    {
        $file = ROOT_PATH . "/packages/{$packageName}/package.php";

        if (!is_file($file)) {
            throw new \RuntimeException("Manifest del pacchetto non trovato: {$packageName}");
        }

        $this->data = require $file;
    }

    public function name(): string
    {
        return $this->data['name'];
    }

    public function label(): string
    {
        return $this->data['label'] ?? $this->data['name'];
    }

    public function dependsOn(): array
    {
        return $this->data['dependsOn'] ?? [];
    }

    /** Chiave della sezione (stesso vocabolario di ShowMenuTool::SECTIONS) - null per i manifest piu' vecchi che non la dichiarano ancora. */
    public function category(): ?string
    {
        return $this->data['category'] ?? null;
    }

    public function description(): string
    {
        return $this->data['description'] ?? '';
    }

    /**
     * Selezione "ragionevole di default" per un'installazione senza
     * negoziazione interattiva campo per campo (quella e' un pezzo di UI a
     * parte, non ancora costruito - vedi "Cosa manca"): i campi 'base'
     * sono gia' sempre inclusi da PackageInstaller::build() a prescindere
     * dalla selezione, quindi qui basta includere quelli negoziabili
     * marcati 'defaultRequired' => true (il suggerimento che il manifest
     * gia' porta con se' per un'eventuale UI) - tutti gli altri restano
     * fuori e finiscono tra i pending, pronti se servono dopo.
     */
    public function defaultSelection(): array
    {
        $selection = [];

        foreach ($this->entities() as $entityKey => $entity) {
            foreach ($entity['fields'] as $fieldKey => $field) {
                if (($field['base'] ?? false) || !($field['defaultRequired'] ?? false)) {
                    continue;
                }
                $selection[$entityKey][$fieldKey] = 'required';
            }
        }

        return $selection;
    }

    public function selectableDirectly(): bool
    {
        return $this->data['selectableDirectly'] ?? true;
    }

    public function entities(): array
    {
        return $this->data['entities'] ?? [];
    }

    /**
     * Struttura sezioni(tab)/box per l'edit di questa entity, se il
     * manifest la definisce - vedi App\View\Component\FormComponent, che
     * la incrocia con i campi davvero disponibili (un box i cui campi
     * non sono installati per questo progetto sparisce da solo, invece
     * di lasciare un vuoto). Null se il manifest non la definisce: chi
     * chiama ricade su un form a campo singolo, non strutturato.
     */
    public function layout(string $entityKey): ?array
    {
        return $this->data['entities'][$entityKey]['layout'] ?? null;
    }
}
