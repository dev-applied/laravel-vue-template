<?php

declare(strict_types=1);

namespace App\Exceptions;

use App;
use Sentry\Tracing\SamplingContext;

class Sentry
{
    public static function tracesSampler(SamplingContext $context): float
    {
        // Disable performance monitoring except in production
        if (! App::environment('production')) {
            return 0;
        }

        $transaction = $context->getTransactionContext();

        // 1) Always sample if a parent trace was already sampled
        if ($context->getParentSampled()) {
            return 1.0;
        }

        // 2) Detect queue jobs
        if ($transaction && $transaction->getOp() === 'queue.process') {
            return 0.05;
        }

        // 3) Default: 10%
        return 0.1;
    }
}
