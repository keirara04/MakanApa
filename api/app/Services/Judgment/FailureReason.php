<?php

namespace App\Services\Judgment;

enum FailureReason: string
{
    case Timeout = 'timeout';
    case RateLimited = 'rate_limited';
    case ProviderError = 'provider_error';
    case InvalidJson = 'invalid_json';
    case SchemaValidation = 'schema_validation';
    case BudgetExhausted = 'budget_exhausted';
    case Disabled = 'disabled';
    case StateTooLarge = 'state_too_large';

    public function isRetryable(): bool
    {
        return in_array($this, [self::Timeout, self::RateLimited, self::ProviderError], true);
    }
}
