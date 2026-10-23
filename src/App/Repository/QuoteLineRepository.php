<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\QuoteLine;

final class QuoteLineRepository extends AbstractRepository
{
    protected string $table = 'quote_lines';
    protected string $modelClass = QuoteLine::class;

    /** @return QuoteLine[] nell'ordine */
    public function findByQuote(int $quoteId): array
    {
        return $this->findAll(['quote_id' => $quoteId], 'position, id');
    }
}
