<?php

namespace App\Documentation\RuleTransformers;

use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Illuminate\Validation\Rules\Password;

class PasswordRuleTransformer implements RuleTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        $rules = $rule->is(Password::class) ? $rule->getRule()->appliedRules() : [];
        if ($rules === []) {
            return false;
        }

        // dd($rules);
        return match (true) {
            $rules['min'] !== 8 => false,
            $rules['mixedCase'] !== true => false,
            $rules['numbers'] !== true => false,
            $rules['symbols'] !== true => false,
            $rules['uncompromised'] !== true => false,
            default => true,
        };
    }

    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type
    {
        return $previous
            ->setDescription('Password must be at least 8 characters long, contain at least one uppercase letter, one lowercase letter, one number, one symbol, and not be compromised.')
            ->examples(['My5ecureP@ssw0rd'])
            ->format($context->field);
    }
}
