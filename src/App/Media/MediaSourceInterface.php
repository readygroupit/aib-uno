<?php

declare(strict_types=1);

namespace App\Media;

use App\Model\MediaAsset;

/**
 * Da dove arrivano foto e video dell'archivio: Google Drive o una cartella
 * del server (anche un NAS montato). L'archivio tiene solo l'indice; i
 * file restano li'.
 */
interface MediaSourceInterface
{
    /** Valore di media_assets.source. */
    public function sourceCode(): string;

    public function isReady(): bool;

    /**
     * Tutte le foto e i video sotto la cartella d'archivio.
     *
     * @return iterable<array{id:string, name:string, mime:string, folder:string, webLink:?string, size:?int, modifiedAt:?string, width:?int, height:?int, duration:?int}>
     */
    public function listFiles(): iterable;

    /**
     * Il file scaricato in $target (per l'analisi), solo se non supera
     * $maxBytes. Falso se troppo grande o non scaricabile.
     */
    public function download(MediaAsset $asset, string $target, int $maxBytes): bool;

    /** Un'anteprima gia' pronta dell'origine (es. il fotogramma di Google per un video grande), in binario. */
    public function previewImage(MediaAsset $asset): ?string;
}
