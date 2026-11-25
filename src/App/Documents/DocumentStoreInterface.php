<?php

declare(strict_types=1);

namespace App\Documents;

/**
 * Dove stanno davvero i file (oggi Google Drive). Il gestionale tiene nel
 * database cartelle e file con le loro informazioni (DocumentService); qui
 * solo le operazioni sul sistema esterno.
 *
 * Un elemento e' un array: id, name, mime, isFolder, parents (id),
 * size, webLink, modifiedAt (Y-m-d H:i:s), md5, trashed.
 */
interface DocumentStoreInterface
{
    /** Collegato e con una cartella principale. */
    public function isReady(): bool;

    /** Puo' anche creare, caricare, rinominare e mettere nel cestino (non solo leggere). */
    public function canWrite(): bool;

    public function rootFolderId(): ?string;

    /** @return array<string,mixed> l'elemento creato */
    public function createFolder(string $name, string $parentId): array;

    /** @return array<string,mixed> l'elemento caricato */
    public function uploadFile(string $localPath, string $name, string $mime, string $parentId): array;

    public function rename(string $id, string $name): void;

    /** Nel cestino: si recupera dal Drive per 30 giorni, mai cancellato per sempre da qui. */
    public function trash(string $id): void;

    /** @return iterable<array<string,mixed>> cartelle e file dentro la cartella (non nel cestino) */
    public function listChildren(string $folderId): iterable;

    /** Il punto da cui leggere le modifiche future. */
    public function startPageToken(): string;

    /**
     * Le modifiche dal punto dato: elementi cambiati (anche nel cestino o
     * tolti: 'removed' => true) e il punto da cui ripartire.
     *
     * @return array{items: list<array<string,mixed>>, next: string}
     */
    public function changes(string $pageToken): array;
}
