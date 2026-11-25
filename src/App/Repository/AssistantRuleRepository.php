<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\AssistantRule;

final class AssistantRuleRepository extends AbstractRepository
{
    protected string $table = 'assistant_rules';
    protected string $modelClass = AssistantRule::class;

    /** @return array<string, AssistantRule> codice => regola */
    public function byCode(): array
    {
        $result = [];
        foreach ($this->findAll([], 'id') as $rule) {
            $result[(string) $rule->code] = $rule;
        }

        return $result;
    }
}
