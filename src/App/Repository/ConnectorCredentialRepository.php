<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ConnectorCredential;

final class ConnectorCredentialRepository extends AbstractRepository
{
    protected string $table = 'connector_credentials';
    protected string $modelClass = ConnectorCredential::class;

    /**
     * Una riga per connettore, per convenzione (vedi package.php - niente
     * vincolo di unicita' a livello di schema): se per errore ce ne
     * fossero piu' d'una, prende la piu' recente.
     */
    public function findByConnectorCode(string $code): ?ConnectorCredential
    {
        $rows = $this->findAll(['connector_code' => $code], 'id DESC');

        return $rows[0] ?? null;
    }
}
