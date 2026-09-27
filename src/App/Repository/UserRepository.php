<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\User;

final class UserRepository extends AbstractRepository
{
    protected string $table = 'users';
    protected string $modelClass = User::class;

    /**
     * Candidati per il login: utenti attivi il cui username o la cui email
     * corrispondono all'identificativo digitato. La password va verificata
     * su ciascun candidato con password_verify(): l'hash e' salato per
     * utente, quindi non c'e' rischio di ambiguita' anche se piu' utenti
     * condividono la stessa email.
     *
     * @return User[]
     */
    public function findLoginCandidates(string $identifier): array
    {
        $sql = 'SELECT * FROM users WHERE status != 0 AND (username = :username OR email = :email)';

        return array_map(
            static fn (array $row) => User::fromArray($row),
            $this->db->fetchAll($sql, ['username' => $identifier, 'email' => $identifier])
        );
    }

    /**
     * Ricerca libera per il tool "modifica utente" del prompt: username,
     * nome, cognome o email che CONTENGONO il termine (non solo uguali,
     * a differenza di findLoginCandidates - qui l'operatore sta cercando
     * "chi assomiglia a questo nome", non autenticandosi). Se la ricerca
     * esatta non trova nulla, prova una ricerca fonetica di riserva (vedi
     * searchByNamePhonetic()) - utile soprattutto per la dettatura vocale,
     * dove un cognome puo' essere trascritto con una grafia diversa da
     * quella vera ma dallo stesso suono (es. "Pensa" per "Penza").
     *
     * @return User[]
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        $exact = $this->searchByNameExact($query, $limit);

        return $exact !== [] ? $exact : $this->searchByNamePhonetic($query, $limit);
    }

    private function searchByNameExact(string $query, int $limit): array
    {
        // Placeholder distinti (non lo stesso ':query' ripetuto 4 volte):
        // con PDO::ATTR_EMULATE_PREPARES a false su MySQL riusare lo stesso
        // nome di parametro piu' volte nella stessa query non e' affidabile.
        $sql = 'SELECT * FROM users WHERE status != 0 AND ('
            . 'username LIKE :q1 OR first_name LIKE :q2 OR last_name LIKE :q3 OR email LIKE :q4'
            . ') ORDER BY id ASC LIMIT ' . max(1, $limit);
        $like = '%' . $query . '%';

        return array_map(
            static fn (array $row) => User::fromArray($row),
            $this->db->fetchAll($sql, ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like])
        );
    }

    /**
     * SOUNDEX e' una funzione nativa di MySQL (nessuna estensione da
     * installare): riduce una parola al suo "scheletro fonetico" -
     * ignora punteggiatura/spazi e raggruppa insieme consonanti dal
     * suono simile (s e z nello stesso gruppo, per esempio), cosi'
     * "Pensa"/"Penza" o "Abisoft"/"A.B. Soft" finiscono sullo stesso
     * codice. Confrontata parola per parola della query (fino a 3, come
     * un nome+cognome) contro username/nome/cognome - non contro
     * l'email, che non e' un "nome" da leggere foneticamente. Scatta
     * solo come riserva quando la ricerca esatta non trova nulla: fa una
     * scansione completa (SOUNDEX su una colonna non puo' usare un
     * indice), va bene solo perche' non e' il percorso comune.
     */
    private function searchByNamePhonetic(string $query, int $limit): array
    {
        $words = array_slice(preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY), 0, 3);
        if ($words === []) {
            return [];
        }

        $conditions = [];
        $params = [];
        foreach ($words as $i => $word) {
            foreach (['username', 'first_name', 'last_name'] as $j => $field) {
                $key = "w{$i}_{$j}";
                $conditions[] = "SOUNDEX({$field}) = SOUNDEX(:{$key})";
                $params[$key] = $word;
            }
        }

        $sql = 'SELECT * FROM users WHERE status != 0 AND (' . implode(' OR ', $conditions) . ')'
            . ' ORDER BY id ASC LIMIT ' . max(1, $limit);

        return array_map(
            static fn (array $row) => User::fromArray($row),
            $this->db->fetchAll($sql, $params)
        );
    }

    /**
     * Controllo esplicito prima di un UPDATE: la tabella ha gia' un vincolo
     * di unicita' scoped-to-active su username (vedi schema.sql), ma
     * lasciarlo semplicemente fallire produrrebbe un errore SQL crudo
     * invece di un messaggio comprensibile nel form.
     */
    public function usernameTakenByOther(string $username, int $exceptId): bool
    {
        $sql = 'SELECT 1 FROM users WHERE status != 0 AND username = :username AND id != :id LIMIT 1';

        return $this->db->fetchRow($sql, ['username' => $username, 'id' => $exceptId]) !== null;
    }
}
