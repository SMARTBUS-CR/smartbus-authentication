<?php

namespace App\Documentation\RuleTransformers;

use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;

class ConfirmedRule implements AllRulesSchemasTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('confirmed');
    }

    public function transformAll(SchemaBag $schemaBag, NormalizedRule $rule, RuleTransformerContext $context): void
    {
        $schema = clone $schemaBag->getOrFail($context->field);

        $schemaBag->set(
            "{$context->field}_confirmation",
            $schema->setDescription("The value of this field must match the value of `{$context->field}`"),
        );
    }
}
