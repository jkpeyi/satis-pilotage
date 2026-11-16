<?php

namespace App\Actions\BusinessRules;

use App\Enums\RuleSource;
use App\Enums\RuleStatus;
use App\Models\BusinessRule;

final class SaveBusinessRule
{
    /**
     * @param  array{implementation_id: int, title: string, description: string, source: RuleSource, status: RuleStatus}  $data
     */
    public function handle(array $data, ?BusinessRule $rule = null): BusinessRule
    {
        $rule ??= new BusinessRule;
        $rule->fill($data)->save();

        return $rule;
    }
}
