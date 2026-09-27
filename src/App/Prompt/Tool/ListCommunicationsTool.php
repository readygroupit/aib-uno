<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\CommunicationRepository;

/**
 * Solo le colonne di 'communications' (mai 'communication_contents' - vedi
 * il commento nel manifest sul perche' sono due tabelle: una lista non
 * deve mai leggere il corpo del messaggio). Oggetto/corpo si vedono aprendo
 * la singola comunicazione - vedi EditCommunicationTool, bespoke apposta
 * per unire le due tabelle in un solo form (AbstractEditEntityTool
 * assume un solo repository, qui ce ne sono due collegati 1:1).
 */
final class ListCommunicationsTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return CommunicationRepository::class;
    }

    protected function entityTag(): string
    {
        return 'communication';
    }

    protected function baseUrl(): string
    {
        return '/comunicazioni';
    }

    protected function title(): string
    {
        return 'Comunicazioni';
    }

    protected function statLabel(): string
    {
        return 'Totale comunicazioni';
    }

    protected function createLabel(): ?string
    {
        return 'Nuova comunicazione';
    }

    protected function createPermission(): ?string
    {
        return 'communications.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'channel', 'label' => 'Canale'],
            ['key' => 'direction', 'label' => 'Direzione'],
            ['key' => 'deliveryStatus', 'label' => 'Stato invio', 'badge' => true],
            ['key' => 'sentAt', 'label' => 'Inviata il'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Apri', 'href' => '/comunicazioni/{id}', 'permission' => 'communications.edit'],
        ];
    }

    public function name(): string
    {
        return 'list_communications';
    }

    public function description(): string
    {
        return "Mostra lo storico delle comunicazioni (email, WhatsApp, telefono...) con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Comunicazioni';
    }

    public function menuSection(): ?string
    {
        return 'crm';
    }

    public function requiredPermission(): ?string
    {
        return 'communications.view';
    }

    public function triggers(): array
    {
        return ['comunicazioni', 'lista comunicazioni', 'elenco comunicazioni', 'storico comunicazioni'];
    }
}
